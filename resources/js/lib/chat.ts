import { useSyncExternalStore } from 'react';
import { withBase } from '@/lib/utils';

/**
 * Team chat store shared by the floating widget, the sidebar badge and the Messenger page.
 * Shared hosting has no websocket server, so it polls /chat/sync: faster while a thread is
 * open and the tab is visible, slower otherwise.
 */

export interface ChatPerson { id: number; name: string; job_title: string | null; online: boolean; last_seen_at: string | null }
export interface ChatAttachment { type: string; id: number; title: string; subtitle: string; url: string; external: boolean }
export interface ChatMessage { id: number; conversation_id: number; user_id: number; user_name: string | null; body: string | null; attachment: ChatAttachment | null; created_at: string }
export interface ChatConversation {
    id: number;
    kind: 'team' | 'direct';
    name: string;
    user: { id: number; name: string } | null;
    last_message: { user_id: number; user_name: string | null; preview: string; created_at: string } | null;
    unread: number;
}
export interface ChatReader { id: number; name: string; last_read_message_id: number | null }
export interface ChatThread {
    conversationId: number;
    kind: 'team' | 'direct';
    name: string;
    user: { id: number; name: string } | null;
    messages: ChatMessage[];
    hasMore: boolean;
    readers: ChatReader[];
    loaded: boolean;
}
export interface ChatState {
    ready: boolean;
    meId: number | null;
    teamId: number | null;
    people: ChatPerson[];
    conversations: ChatConversation[];
    unreadTotal: number;
    attachmentTypes: Record<string, string>;
    thread: ChatThread | null;
}

interface SyncResponse {
    me_id: number;
    team_id: number;
    users: ChatPerson[];
    conversations: ChatConversation[];
    unread_total: number;
    attachment_types: Record<string, string> | [];
    thread: null | {
        conversation_id: number;
        kind: ChatThread['kind'];
        name: string;
        user: ChatThread['user'];
        messages: ChatMessage[];
        has_more: boolean | null;
        readers: ChatReader[];
    };
}

let state: ChatState = { ready: false, meId: null, teamId: null, people: [], conversations: [], unreadTotal: 0, attachmentTypes: {}, thread: null };
let basePath = '';
let timer: ReturnType<typeof setTimeout> | null = null;
let inFlight = false;
let queued = false;
const listeners = new Set<() => void>();

function setState(patch: Partial<ChatState>) {
    state = { ...state, ...patch };
    listeners.forEach((listener) => listener());
}

function visible() {
    return typeof document === 'undefined' || document.visibilityState === 'visible';
}

function xsrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
    const response = await fetch(withBase(path, basePath), {
        credentials: 'same-origin',
        ...init,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(init.headers ?? {}),
        },
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const errors = (data as { errors?: Record<string, string[]> }).errors;
        const first = errors ? Object.values(errors)[0]?.[0] : undefined;
        throw new Error(first ?? (data as { message?: string }).message ?? `Request failed (${response.status})`);
    }
    return data as T;
}

function schedule() {
    if (timer) clearTimeout(timer);
    if (listeners.size === 0) return;
    const delay = !visible() ? 30000 : state.thread ? 3000 : 10000;
    timer = setTimeout(() => void sync(), delay);
}

export async function sync(): Promise<void> {
    if (inFlight) {
        queued = true;
        return;
    }
    inFlight = true;

    const thread = state.thread;
    const lastId = thread?.loaded ? thread.messages[thread.messages.length - 1]?.id ?? 0 : 0;
    const markRead = Boolean(thread) && visible();

    try {
        const data = await request<SyncResponse>('/chat/sync', {
            method: 'POST',
            body: JSON.stringify({ conversation_id: thread?.conversationId ?? null, after_id: lastId, mark_read: markRead }),
        });

        let nextThread = state.thread;
        if (data.thread && nextThread && nextThread.conversationId === data.thread.conversation_id) {
            const incoming = data.thread.messages;
            const known = new Set(nextThread.messages.map((message) => message.id));
            nextThread = {
                ...nextThread,
                kind: data.thread.kind,
                name: data.thread.name,
                user: data.thread.user,
                messages: nextThread.loaded ? [...nextThread.messages, ...incoming.filter((message) => !known.has(message.id))] : incoming,
                hasMore: nextThread.loaded ? nextThread.hasMore : Boolean(data.thread.has_more),
                readers: data.thread.readers,
                loaded: true,
            };
        }

        // The open thread was just marked read on the server.
        const conversations = markRead && nextThread
            ? data.conversations.map((item) => (item.id === nextThread!.conversationId ? { ...item, unread: 0 } : item))
            : data.conversations;

        setState({
            ready: true,
            meId: data.me_id,
            teamId: data.team_id,
            people: data.users,
            conversations,
            unreadTotal: conversations.reduce((sum, item) => sum + item.unread, 0),
            attachmentTypes: Array.isArray(data.attachment_types) ? {} : data.attachment_types,
            thread: nextThread,
        });
    } catch {
        // Network blips are retried on the next tick.
    } finally {
        inFlight = false;
        if (queued) {
            queued = false;
            void sync();
        } else {
            schedule();
        }
    }
}

function onVisibility() {
    if (visible()) void sync();
}

function subscribe(listener: () => void) {
    listeners.add(listener);
    if (listeners.size === 1) {
        document.addEventListener('visibilitychange', onVisibility);
        void sync();
    }
    return () => {
        listeners.delete(listener);
        if (listeners.size === 0) {
            document.removeEventListener('visibilitychange', onVisibility);
            if (timer) clearTimeout(timer);
        }
    };
}

export function configureChat(base: string) {
    basePath = base;
}

export function useChat(): ChatState {
    return useSyncExternalStore(subscribe, () => state, () => state);
}

/** Opens a thread (marking it read while visible), or closes it with null. */
export function openConversation(conversationId: number | null) {
    if (state.thread?.conversationId === conversationId) return;
    const summary = state.conversations.find((item) => item.id === conversationId);
    setState({
        thread: conversationId
            ? {
                conversationId,
                kind: summary?.kind ?? (conversationId === state.teamId ? 'team' : 'direct'),
                name: summary?.name ?? (conversationId === state.teamId ? 'Team chat' : ''),
                user: summary?.user ?? null,
                messages: [],
                hasMore: false,
                readers: [],
                loaded: false,
            }
            : null,
    });
    if (conversationId) void sync();
}

export async function openChatWith(userId: number): Promise<number> {
    const data = await request<{ conversation_id: number }>(`/chat/direct/${userId}`, { method: 'POST' });
    const person = state.people.find((item) => item.id === userId);
    openConversation(data.conversation_id);
    if (state.thread && !state.thread.user && person) {
        setState({ thread: { ...state.thread, name: person.name, user: { id: person.id, name: person.name } } });
    }
    return data.conversation_id;
}

export async function sendMessage(body: string, attachment: ChatAttachment | null): Promise<void> {
    const thread = state.thread;
    if (!thread) return;
    const data = await request<{ message: ChatMessage }>(`/chat/conversations/${thread.conversationId}/messages`, {
        method: 'POST',
        body: JSON.stringify({ body, attachment_type: attachment?.type ?? null, attachment_id: attachment?.id ?? null }),
    });
    const current = state.thread;
    if (current && current.conversationId === thread.conversationId && !current.messages.some((message) => message.id === data.message.id)) {
        setState({ thread: { ...current, messages: [...current.messages, data.message] } });
    }
    void sync();
}

export async function loadOlder(): Promise<void> {
    const thread = state.thread;
    const first = thread?.messages[0];
    if (!thread || !first) return;
    const data = await request<{ messages: ChatMessage[]; has_more: boolean }>(
        `/chat/conversations/${thread.conversationId}/messages?before_id=${first.id}`,
    );
    const current = state.thread;
    if (current && current.conversationId === thread.conversationId) {
        setState({ thread: { ...current, messages: [...data.messages, ...current.messages], hasMore: data.has_more } });
    }
}

export async function searchAttachables(type: string, query: string): Promise<ChatAttachment[]> {
    const data = await request<{ items: ChatAttachment[] }>(`/chat/attachables?type=${encodeURIComponent(type)}&q=${encodeURIComponent(query)}`);
    return data.items;
}
