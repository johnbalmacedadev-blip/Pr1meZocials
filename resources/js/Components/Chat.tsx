import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft, CalendarHeart, ExternalLink, FolderOpen, Lightbulb, Megaphone, MessageCircle, Paperclip, Search, SendHorizontal, Sparkles, Users, X,
} from 'lucide-react';
import { FormEvent, KeyboardEvent, ReactNode, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import {
    ChatAttachment, ChatConversation, ChatPerson, ChatThread, loadOlder, openChatWith, openConversation, searchAttachables, sendMessage, useChat,
} from '@/lib/chat';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

const typeIcons: Record<string, typeof Lightbulb> = {
    content: Lightbulb,
    event: CalendarHeart,
    inspiration: Sparkles,
    resource: FolderOpen,
    campaign: Megaphone,
};

function initials(name: string) {
    return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('');
}

function timeLabel(value?: string | null) {
    if (!value) return '';
    const date = new Date(value);
    const now = new Date();
    return date.toDateString() === now.toDateString()
        ? date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
        : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function lastSeenLabel(person?: ChatPerson) {
    if (!person) return '';
    if (person.online) return 'Online';
    if (!person.last_seen_at) return 'Offline';
    return `Last seen ${timeLabel(person.last_seen_at)}`;
}

function seenLabel(thread: ChatThread, messageId: number) {
    const readers = thread.readers.filter((reader) => (reader.last_read_message_id ?? 0) >= messageId);
    if (readers.length === 0) return 'Sent';
    if (thread.kind === 'direct') return 'Seen';
    if (readers.length === thread.readers.length && readers.length > 1) return 'Seen by everyone';
    const names = readers.map((reader) => reader.name.split(' ')[0]);
    return `Seen by ${names.slice(0, 3).join(', ')}${names.length > 3 ? ` +${names.length - 3}` : ''}`;
}

export function Avatar({ name, online, className }: { name: string; online?: boolean; className?: string }) {
    return (
        <span className={cn('relative grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700', className)}>
            {initials(name)}
            {online !== undefined && (
                <span className={cn('absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full ring-2 ring-white', online ? 'bg-emerald-500' : 'bg-zinc-300')} />
            )}
        </span>
    );
}

export function UnreadBadge({ count, className }: { count: number; className?: string }) {
    if (count <= 0) return null;
    return (
        <span className={cn('grid h-5 min-w-5 place-items-center rounded-full bg-rose-600 px-1.5 text-[11px] font-semibold text-white', className)}>
            {count > 99 ? '99+' : count}
        </span>
    );
}

/** Overlapping avatars of whoever is online right now. */
export function OnlineStack({ max = 4 }: { max?: number }) {
    const { people } = useChat();
    const online = people.filter((person) => person.online);
    if (online.length === 0) return null;
    return (
        <span className="flex -space-x-2" title={online.map((person) => person.name).join(', ')}>
            {online.slice(0, max).map((person) => (
                <Avatar key={person.id} name={person.name} online className="h-7 w-7 text-[10px] ring-2 ring-white" />
            ))}
            {online.length > max && (
                <span className="grid h-7 w-7 place-items-center rounded-full bg-zinc-100 text-[10px] font-semibold text-zinc-600 ring-2 ring-white">+{online.length - max}</span>
            )}
        </span>
    );
}

export function ConversationList({ onOpen, activeId }: { onOpen: (id: number) => void; activeId?: number | null }) {
    const { conversations, people, meId, ready } = useChat();
    const online = useMemo(() => new Map(people.map((person) => [person.id, person.online])), [people]);
    const direct = conversations.filter((conversation) => conversation.kind === 'direct');

    if (ready && direct.length === 0) {
        return <p className="px-4 py-8 text-center text-sm text-zinc-500">No direct messages yet. Pick someone under People to start one.</p>;
    }

    return (
        <ul className="divide-y divide-zinc-100">
            {direct.map((conversation: ChatConversation) => {
                const unread = conversation.unread > 0;
                return (
                    <li key={conversation.id}>
                        <button
                            type="button"
                            onClick={() => onOpen(conversation.id)}
                            className={cn('flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-zinc-50', activeId === conversation.id && 'bg-brand-50/60')}
                        >
                            <Avatar name={conversation.name} online={conversation.user ? online.get(conversation.user.id) ?? false : undefined} />
                            <span className="min-w-0 flex-1">
                                <span className="flex items-baseline justify-between gap-2">
                                    <span className={cn('truncate text-sm', unread ? 'font-semibold text-zinc-950' : 'font-medium text-zinc-900')}>{conversation.name}</span>
                                    <span className="shrink-0 text-[11px] text-zinc-400">{timeLabel(conversation.last_message?.created_at)}</span>
                                </span>
                                <span className="flex items-center justify-between gap-2">
                                    <span className={cn('truncate text-xs', unread ? 'font-medium text-zinc-800' : 'text-zinc-500')}>
                                        {conversation.last_message?.user_id === meId ? 'You: ' : ''}{conversation.last_message?.preview}
                                    </span>
                                    <UnreadBadge count={conversation.unread} />
                                </span>
                            </span>
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}

export function PeopleList({ onOpen }: { onOpen: (conversationId: number) => void }) {
    const { people, ready } = useChat();
    const [query, setQuery] = useState('');
    const [busy, setBusy] = useState<number | null>(null);
    const filtered = people.filter((person) => person.name.toLowerCase().includes(query.trim().toLowerCase()));
    const onlineCount = people.filter((person) => person.online).length;

    async function start(person: ChatPerson) {
        setBusy(person.id);
        try {
            onOpen(await openChatWith(person.id));
        } finally {
            setBusy(null);
        }
    }

    return (
        <div>
            <div className="sticky top-0 z-10 bg-white px-4 py-2">
                <div className="relative">
                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-zinc-400" />
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Find a teammate"
                        className="w-full rounded-full border border-zinc-200 bg-zinc-50 py-1.5 pl-8 pr-3 text-sm outline-none focus:border-brand-700 focus:bg-white"
                    />
                </div>
                <p className="mt-1.5 text-[11px] font-medium uppercase tracking-wide text-zinc-400">{onlineCount} online · tap someone to message them</p>
            </div>
            {ready && filtered.length === 0 && <p className="px-4 py-6 text-center text-sm text-zinc-500">No one matches.</p>}
            <ul>
                {filtered.map((person) => (
                    <li key={person.id}>
                        <button type="button" disabled={busy === person.id} onClick={() => void start(person)} className="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-zinc-50 disabled:opacity-60">
                            <Avatar name={person.name} online={person.online} />
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-medium text-zinc-900">{person.name}</span>
                                <span className={cn('block truncate text-xs', person.online ? 'text-emerald-600' : 'text-zinc-500')}>
                                    {person.online ? 'Online' : person.job_title || lastSeenLabel(person)}
                                </span>
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function AttachmentCard({ attachment, mine }: { attachment: ChatAttachment; mine: boolean }) {
    const Icon = typeIcons[attachment.type] ?? Paperclip;
    const inner = (
        <>
            <span className={cn('grid h-9 w-9 shrink-0 place-items-center rounded-lg', mine ? 'bg-white/15 text-white' : 'bg-brand-50 text-brand-700')}>
                <Icon className="h-4 w-4" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-semibold">{attachment.title}</span>
                <span className={cn('block truncate text-xs', mine ? 'text-white/75' : 'text-zinc-500')}>{attachment.subtitle}</span>
            </span>
            {attachment.external && <ExternalLink className="h-3.5 w-3.5 shrink-0 opacity-70" />}
        </>
    );
    const className = cn('mt-1 flex min-w-0 items-center gap-2.5 rounded-xl border p-2 transition', mine ? 'border-white/20 hover:bg-white/10' : 'border-zinc-200 bg-white hover:bg-zinc-50');

    return attachment.external
        ? <a href={attachment.url} target="_blank" rel="noreferrer noopener" className={className}>{inner}</a>
        : <Link href={attachment.url} className={className}>{inner}</Link>;
}

function AttachmentPicker({ types, onPick, onClose }: { types: Record<string, string>; onPick: (item: ChatAttachment) => void; onClose: () => void }) {
    const keys = Object.keys(types);
    const [type, setType] = useState(keys[0] ?? '');
    const [query, setQuery] = useState('');
    const [items, setItems] = useState<ChatAttachment[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!type) return;
        let cancelled = false;
        setLoading(true);
        const handle = setTimeout(() => {
            searchAttachables(type, query)
                .then((result) => { if (!cancelled) setItems(result); })
                .catch(() => { if (!cancelled) setItems([]); })
                .finally(() => { if (!cancelled) setLoading(false); });
        }, 250);
        return () => { cancelled = true; clearTimeout(handle); };
    }, [type, query]);

    return (
        <div className="absolute inset-x-2 bottom-full z-20 mb-2 flex h-80 max-h-[60vh] flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xl">
            <div className="flex items-center justify-between border-b border-zinc-100 px-3 py-2">
                <span className="text-sm font-semibold text-zinc-900">Share an item</span>
                <button type="button" onClick={onClose} className="rounded-full p-1 text-zinc-500 hover:bg-zinc-100" aria-label="Close"><X className="h-4 w-4" /></button>
            </div>
            {keys.length === 0 ? (
                <p className="px-3 py-6 text-center text-sm text-zinc-500">You don't have access to any items you can share.</p>
            ) : (
                <>
                    <div className="flex gap-1 overflow-x-auto px-3 pt-2">
                        {keys.map((key) => (
                            <button key={key} type="button" onClick={() => setType(key)} className={cn('shrink-0 rounded-full px-2.5 py-1 text-xs', type === key ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200')}>
                                {types[key]}
                            </button>
                        ))}
                    </div>
                    <div className="px-3 py-2">
                        <input autoFocus value={query} onChange={(event) => setQuery(event.target.value)} placeholder={`Search ${types[type]?.toLowerCase() ?? ''}s`} className="w-full rounded-lg border border-zinc-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand-700" />
                    </div>
                    <ul className="min-h-0 flex-1 overflow-auto pb-2">
                        {loading && items.length === 0 && <li className="px-3 py-4 text-center text-xs text-zinc-500">Searching…</li>}
                        {!loading && items.length === 0 && <li className="px-3 py-4 text-center text-xs text-zinc-500">Nothing found.</li>}
                        {items.map((item) => {
                            const Icon = typeIcons[item.type] ?? Paperclip;
                            return (
                                <li key={`${item.type}-${item.id}`}>
                                    <button type="button" onClick={() => onPick(item)} className="flex w-full items-center gap-2.5 px-3 py-2 text-left hover:bg-zinc-50">
                                        <Icon className="h-4 w-4 shrink-0 text-brand-700" />
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm text-zinc-900">{item.title}</span>
                                            <span className="block truncate text-xs text-zinc-500">{item.subtitle}</span>
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </>
            )}
        </div>
    );
}

function escapeRegExp(text: string) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/** Highlights @everyone and @Name in a message body. */
function withMentions(body: string, names: string[], mine: boolean): ReactNode {
    const tokens = ['everyone', 'all', ...names].sort((a, b) => b.length - a.length).map(escapeRegExp);
    const pattern = new RegExp(`(^|\\s)(@(?:${tokens.join('|')}))(?![\\p{L}\\p{N}_])`, 'giu');
    const parts: ReactNode[] = [];
    let last = 0;
    for (const match of body.matchAll(pattern)) {
        const start = (match.index ?? 0) + match[1].length;
        parts.push(body.slice(last, start));
        parts.push(<span key={start} className={cn('rounded px-0.5 font-semibold', mine ? 'bg-white/20' : 'bg-brand-50 text-brand-800')}>{match[2]}</span>);
        last = start + match[2].length;
    }
    parts.push(body.slice(last));

    return parts;
}

type MentionOption = { key: string; label: string; hint: string };

function Composer() {
    const { attachmentTypes, thread, people } = useChat();
    const [body, setBody] = useState('');
    const [attachment, setAttachment] = useState<ChatAttachment | null>(null);
    const [picking, setPicking] = useState(false);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState('');
    const field = useRef<HTMLTextAreaElement>(null);
    const [mention, setMention] = useState<{ query: string; start: number } | null>(null);
    const [highlight, setHighlight] = useState(0);

    useEffect(() => {
        setBody('');
        setAttachment(null);
        setPicking(false);
        setError('');
        setMention(null);
    }, [thread?.conversationId]);

    const mentionOptions = useMemo((): MentionOption[] => {
        if (!mention) return [];
        const query = mention.query.toLowerCase();
        const candidates = thread?.kind === 'team' ? people : people.filter((person) => person.id === thread?.user?.id);
        const options: MentionOption[] = [
            ...(thread?.kind === 'team' ? [{ key: 'everyone', label: 'everyone', hint: 'Notify the whole team' }] : []),
            ...candidates.map((person) => ({ key: String(person.id), label: person.name, hint: person.job_title ?? '' })),
        ];

        return options
            .filter((option) => !query || option.label.toLowerCase().split(/\s+/).some((word) => word.startsWith(query)) || option.label.toLowerCase().startsWith(query))
            .slice(0, 8);
    }, [mention, people, thread?.kind, thread?.user?.id]);

    // Looks for "@word" right before the caret.
    function updateMention(value: string, caret: number) {
        const match = /(^|\s)@([^\s@]{0,30})$/.exec(value.slice(0, caret));
        setMention(match ? { query: match[2], start: caret - match[2].length - 1 } : null);
        setHighlight(0);
    }

    function pickMention(option: MentionOption) {
        if (!mention) return;
        const caret = field.current?.selectionStart ?? body.length;
        const inserted = `@${option.label} `;
        const next = body.slice(0, mention.start) + inserted + body.slice(caret);
        setBody(next);
        setMention(null);
        const position = mention.start + inserted.length;
        requestAnimationFrame(() => {
            field.current?.focus();
            field.current?.setSelectionRange(position, position);
        });
    }

    async function submit(event?: FormEvent) {
        event?.preventDefault();
        if (sending || (!body.trim() && !attachment)) return;
        setSending(true);
        setError('');
        try {
            await sendMessage(body.trim(), attachment);
            setBody('');
            setAttachment(null);
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Message not sent.');
        } finally {
            setSending(false);
        }
    }

    function onKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
        if (mentionOptions.length > 0) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const step = event.key === 'ArrowDown' ? 1 : -1;
                setHighlight((index) => (index + step + mentionOptions.length) % mentionOptions.length);
                return;
            }
            if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                pickMention(mentionOptions[Math.min(highlight, mentionOptions.length - 1)]);
                return;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                setMention(null);
                return;
            }
        }
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            void submit();
        }
    }

    return (
        <form onSubmit={submit} className="relative border-t border-zinc-100 bg-white p-2">
            {mentionOptions.length > 0 && (
                <div className="absolute bottom-full left-2 right-2 z-10 mb-1 max-h-60 overflow-y-auto rounded-xl border border-zinc-200 bg-white py-1 shadow-lg" role="listbox" aria-label="Mention someone">
                    {mentionOptions.map((option, index) => (
                        <button
                            key={option.key}
                            type="button"
                            role="option"
                            aria-selected={index === highlight}
                            onMouseDown={(event) => { event.preventDefault(); pickMention(option); }}
                            onMouseEnter={() => setHighlight(index)}
                            className={cn('flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm', index === highlight ? 'bg-brand-50 text-brand-900' : 'text-zinc-800')}
                        >
                            {option.key === 'everyone' ? <Users className="h-4 w-4 shrink-0 text-brand-700" /> : <Avatar name={option.label} className="h-6 w-6 text-[10px]" />}
                            <span className="font-medium">@{option.label}</span>
                            {option.hint && <span className="truncate text-xs text-zinc-500">{option.hint}</span>}
                        </button>
                    ))}
                </div>
            )}
            {picking && <AttachmentPicker types={attachmentTypes} onClose={() => setPicking(false)} onPick={(item) => { setAttachment(item); setPicking(false); }} />}
            {attachment && (
                <div className="mb-2 flex items-center gap-2 rounded-xl bg-brand-50 px-2.5 py-1.5 text-xs text-brand-800">
                    <Paperclip className="h-3.5 w-3.5 shrink-0" />
                    <span className="min-w-0 flex-1 truncate"><span className="font-semibold">{attachmentTypes[attachment.type] ?? 'Item'}:</span> {attachment.title}</span>
                    <button type="button" onClick={() => setAttachment(null)} aria-label="Remove attachment" className="rounded-full p-0.5 hover:bg-brand-700/10"><X className="h-3.5 w-3.5" /></button>
                </div>
            )}
            {error && <p className="mb-1.5 px-1 text-xs text-rose-600">{error}</p>}
            <div className="flex items-end gap-1.5">
                <button type="button" onClick={() => setPicking((open) => !open)} className={cn('rounded-full p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800', picking && 'bg-zinc-100 text-zinc-800')} aria-label="Share an item" title="Share a task, event, inspiration…">
                    <Paperclip className="h-5 w-5" />
                </button>
                <textarea
                    ref={field}
                    value={body}
                    onChange={(event) => {
                        setBody(event.target.value);
                        updateMention(event.target.value, event.target.selectionStart ?? event.target.value.length);
                    }}
                    onKeyDown={onKeyDown}
                    onBlur={() => setMention(null)}
                    rows={1}
                    placeholder={thread?.kind === 'team' ? 'Message the team… type @ to mention' : 'Write a message…'}
                    className="max-h-32 min-h-[2.5rem] flex-1 resize-none rounded-2xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm outline-none focus:border-brand-700 focus:bg-white"
                />
                <button type="submit" disabled={sending || (!body.trim() && !attachment)} className="rounded-full bg-brand-700 p-2.5 text-white hover:bg-brand-800 disabled:opacity-40" aria-label="Send">
                    <SendHorizontal className="h-4 w-4" />
                </button>
            </div>
        </form>
    );
}

export function ChatThreadView({ onBack, actions, banner, className }: { onBack?: () => void; actions?: ReactNode; banner?: ReactNode; className?: string }) {
    const { thread, meId, people } = useChat();
    const scroller = useRef<HTMLDivElement>(null);
    const stickToBottom = useRef(true);
    const [loadingOlder, setLoadingOlder] = useState(false);
    const isTeam = thread?.kind === 'team';
    const person = people.find((item) => item.id === thread?.user?.id);
    const onlineCount = people.filter((item) => item.online).length;
    const messages = thread?.messages ?? [];
    const lastMine = [...messages].reverse().find((message) => message.user_id === meId);
    const myName = usePage<SharedProps>().props.auth.user?.name ?? '';
    const mentionNames = useMemo(() => [...people.map((item) => item.name), myName].filter(Boolean), [people, myName]);

    useLayoutEffect(() => {
        const element = scroller.current;
        if (element && stickToBottom.current) element.scrollTop = element.scrollHeight;
    }, [messages.length, thread?.conversationId]);

    useEffect(() => { stickToBottom.current = true; }, [thread?.conversationId]);

    async function older() {
        const element = scroller.current;
        const before = element?.scrollHeight ?? 0;
        setLoadingOlder(true);
        stickToBottom.current = false;
        try {
            await loadOlder();
        } finally {
            setLoadingOlder(false);
            requestAnimationFrame(() => { if (element) element.scrollTop = element.scrollHeight - before; });
        }
    }

    if (!thread) return null;

    return (
        <div className={cn('flex min-h-0 flex-col', className)}>
            <div className="flex items-center gap-2 border-b border-zinc-100 px-3 py-2.5">
                {onBack && (
                    <button type="button" onClick={onBack} className="rounded-full p-1.5 text-zinc-600 hover:bg-zinc-100" aria-label="Back">
                        <ArrowLeft className="h-4 w-4" />
                    </button>
                )}
                {isTeam ? (
                    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-700 text-white"><Users className="h-4 w-4" /></span>
                ) : (
                    <Avatar name={thread.name || '?'} online={person?.online ?? false} className="h-8 w-8" />
                )}
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-zinc-950">{thread.name || 'Chat'}</p>
                    {isTeam ? (
                        <p className="truncate text-xs text-emerald-600">{onlineCount} online now</p>
                    ) : (
                        <p className={cn('truncate text-xs', person?.online ? 'text-emerald-600' : 'text-zinc-500')}>{lastSeenLabel(person)}</p>
                    )}
                </div>
                {isTeam && <OnlineStack max={3} />}
                {actions}
            </div>
            {banner}
            <div
                ref={scroller}
                onScroll={(event) => {
                    const element = event.currentTarget;
                    stickToBottom.current = element.scrollHeight - element.scrollTop - element.clientHeight < 80;
                }}
                className="min-h-0 flex-1 space-y-1.5 overflow-y-auto bg-zinc-50/60 px-3 py-3"
            >
                {thread.hasMore && (
                    <div className="pb-2 text-center">
                        <button type="button" onClick={() => void older()} disabled={loadingOlder} className="rounded-full bg-white px-3 py-1 text-xs text-zinc-600 shadow-sm ring-1 ring-zinc-200 hover:bg-zinc-50">
                            {loadingOlder ? 'Loading…' : 'Load earlier messages'}
                        </button>
                    </div>
                )}
                {!thread.loaded && <p className="py-8 text-center text-sm text-zinc-500">Loading…</p>}
                {thread.loaded && messages.length === 0 && (
                    <div className="py-10 text-center text-sm text-zinc-500">
                        <MessageCircle className="mx-auto mb-2 h-8 w-8 text-zinc-300" />
                        {isTeam ? 'Say hi to the whole team.' : `Say hi to ${thread.name || 'your teammate'}.`}
                    </div>
                )}
                {messages.map((message, index) => {
                    const mine = message.user_id === meId;
                    const previous = messages[index - 1];
                    const grouped = previous && previous.user_id === message.user_id
                        && new Date(message.created_at).getTime() - new Date(previous.created_at).getTime() < 5 * 60 * 1000;
                    return (
                        <div key={message.id} className={cn('flex flex-col', mine ? 'items-end' : 'items-start', !grouped && index > 0 && 'pt-2')}>
                            {isTeam && !mine && !grouped && (
                                <span className="mb-0.5 px-1 text-[11px] font-medium text-zinc-600">{message.user_name ?? 'Former teammate'}</span>
                            )}
                            <div className={cn('max-w-[85%] rounded-2xl px-3 py-2 text-sm shadow-sm', mine ? 'rounded-br-md bg-brand-700 text-white' : 'rounded-bl-md bg-white text-zinc-900 ring-1 ring-zinc-200')}>
                                {message.body && <p className="whitespace-pre-wrap break-words">{withMentions(message.body, mentionNames, mine)}</p>}
                                {message.attachment && <AttachmentCard attachment={message.attachment} mine={mine} />}
                            </div>
                            {!grouped && <span className="mt-0.5 px-1 text-[10px] text-zinc-400">{timeLabel(message.created_at)}</span>}
                            {lastMine?.id === message.id && (
                                <span className="px-1 text-[10px] font-medium text-zinc-500">{seenLabel(thread, message.id)}</span>
                            )}
                        </div>
                    );
                })}
            </div>
            <Composer />
        </div>
    );
}

const BUBBLE_SIZE = 48;
const BUBBLE_MARGIN = 8;
const BUBBLE_KEY = 'chat-bubble-position';

type Point = { x: number; y: number };

function clampBubble(point: Point): Point {
    return {
        x: Math.min(Math.max(point.x, BUBBLE_MARGIN), window.innerWidth - BUBBLE_SIZE - BUBBLE_MARGIN),
        y: Math.min(Math.max(point.y, BUBBLE_MARGIN), window.innerHeight - BUBBLE_SIZE - BUBBLE_MARGIN),
    };
}

function savedBubble(): Point | null {
    try {
        const saved = JSON.parse(localStorage.getItem(BUBBLE_KEY) ?? 'null');
        return saved && typeof saved.x === 'number' && typeof saved.y === 'number' ? clampBubble(saved) : null;
    } catch {
        return null;
    }
}

/** Opens the panel on whichever side of the moved bubble has room. */
function panelStyle(bubble: Point): React.CSSProperties {
    const above = bubble.y + BUBBLE_SIZE / 2 > window.innerHeight / 2;
    const room = above ? bubble.y - 16 : window.innerHeight - bubble.y - BUBBLE_SIZE - 16;
    const style: React.CSSProperties = { height: Math.min(544, Math.max(240, room)) };
    // Same width as the panel's classes (max-w-sm, 0.75rem gutters), so it never runs off screen.
    const width = Math.min(384, window.innerWidth - 24);
    const maxOffset = Math.max(12, window.innerWidth - width - 12);
    if (bubble.x + BUBBLE_SIZE / 2 > window.innerWidth / 2) {
        style.right = Math.min(maxOffset, Math.max(12, window.innerWidth - bubble.x - BUBBLE_SIZE));
    } else {
        style.left = Math.min(maxOffset, Math.max(12, bubble.x));
    }
    if (above) {
        style.bottom = window.innerHeight - bubble.y + 8;
    } else {
        style.top = bubble.y + BUBBLE_SIZE + 8;
    }

    return style;
}

/** Floating chat bubble (drag it anywhere; the spot is remembered); opens straight into the team group chat. */
export function ChatWidget() {
    const { unreadTotal, conversations, teamId, thread } = useChat();
    const [open, setOpen] = useState(false);
    const [bubble, setBubble] = useState<Point | null>(savedBubble);
    const drag = useRef<{ offsetX: number; offsetY: number; startX: number; startY: number; moved: boolean } | null>(null);
    const justDragged = useRef(false);
    const directUnread = conversations.filter((item) => item.kind === 'direct').reduce((sum, item) => sum + item.unread, 0);

    useEffect(() => {
        const keepOnScreen = () => setBubble((point) => (point ? clampBubble(point) : point));
        window.addEventListener('resize', keepOnScreen);
        return () => window.removeEventListener('resize', keepOnScreen);
    }, []);

    function startDrag(event: React.PointerEvent<HTMLButtonElement>) {
        const rect = event.currentTarget.getBoundingClientRect();
        drag.current = { offsetX: event.clientX - rect.left, offsetY: event.clientY - rect.top, startX: event.clientX, startY: event.clientY, moved: false };
        event.currentTarget.setPointerCapture(event.pointerId);
    }

    function moveDrag(event: React.PointerEvent<HTMLButtonElement>) {
        const current = drag.current;
        if (!current) return;
        // A small wiggle is still a tap.
        if (!current.moved && Math.hypot(event.clientX - current.startX, event.clientY - current.startY) < 6) return;
        current.moved = true;
        setBubble(clampBubble({ x: event.clientX - current.offsetX, y: event.clientY - current.offsetY }));
    }

    function endDrag() {
        if (drag.current?.moved) {
            // Swallow only the click that ends this drag; some browsers send none.
            justDragged.current = true;
            window.setTimeout(() => { justDragged.current = false; }, 0);
            setBubble((point) => {
                try {
                    if (point) localStorage.setItem(BUBBLE_KEY, JSON.stringify(point));
                } catch {
                    // Private mode: the spot just isn't remembered.
                }
                return point;
            });
        }
        drag.current = null;
    }

    // Only treat the team chat as being read while the panel actually shows it.
    useEffect(() => {
        if (open && teamId && thread?.conversationId !== teamId) openConversation(teamId);
    }, [open, teamId, thread?.conversationId]);
    useEffect(() => () => openConversation(null), []);

    function close() {
        setOpen(false);
        openConversation(null);
    }

    return (
        <>
            {open && (
                <div
                    className={cn(
                        'fixed z-50 flex w-[calc(100vw-1.5rem)] max-w-sm flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl',
                        !bubble && 'bottom-[calc(9rem+env(safe-area-inset-bottom))] right-3 h-[min(34rem,calc(100vh-11rem))] lg:bottom-20 lg:right-5',
                    )}
                    style={bubble ? panelStyle(bubble) : undefined}
                >
                    {thread && thread.conversationId === teamId ? (
                        <ChatThreadView
                            className="h-full"
                            actions={(
                                <button type="button" onClick={close} className="rounded-full p-1.5 text-zinc-500 hover:bg-zinc-100" aria-label="Close chat"><X className="h-4 w-4" /></button>
                            )}
                            banner={(
                                <Link href="/messenger?tab=direct" onClick={close} className={cn('flex items-center justify-between gap-2 border-b border-zinc-100 px-3 py-1.5 text-xs', directUnread > 0 ? 'bg-brand-50 font-medium text-brand-800' : 'text-zinc-500 hover:bg-zinc-50')}>
                                    <span>{directUnread > 0 ? `${directUnread} new direct message${directUnread === 1 ? '' : 's'}` : 'Direct messages and people'}</span>
                                    <span className="font-medium text-brand-700">Open Messenger →</span>
                                </Link>
                            )}
                        />
                    ) : (
                        <p className="grid h-full place-items-center text-sm text-zinc-500">Loading team chat…</p>
                    )}
                </div>
            )}
            <button
                type="button"
                onPointerDown={startDrag}
                onPointerMove={moveDrag}
                onPointerUp={endDrag}
                onPointerCancel={endDrag}
                onClick={() => {
                    if (justDragged.current) {
                        justDragged.current = false;
                        return;
                    }
                    if (open) close(); else setOpen(true);
                }}
                className={cn(
                    'fixed z-50 grid h-12 w-12 cursor-grab touch-none select-none place-items-center rounded-full bg-brand-700 text-white shadow-lg shadow-brand-700/30 transition-colors hover:bg-brand-800 active:cursor-grabbing',
                    !bubble && 'bottom-[calc(5.5rem+env(safe-area-inset-bottom))] right-3 lg:bottom-5 lg:right-5',
                )}
                style={bubble ? { left: bubble.x, top: bubble.y } : undefined}
                title="Drag to move"
                aria-label={open ? 'Close team chat' : 'Open team chat'}
            >
                {open ? <X className="h-5 w-5" /> : <MessageCircle className="h-5 w-5" />}
                {!open && <UnreadBadge count={unreadTotal} className="absolute -right-1 -top-1 ring-2 ring-white" />}
            </button>
        </>
    );
}
