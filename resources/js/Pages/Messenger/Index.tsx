import { Head, usePage } from '@inertiajs/react';
import { MessageCircle, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Avatar, ChatThreadView, ConversationList, PeopleList, UnreadBadge } from '@/Components/Chat';
import { PageHeader } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { openConversation, useChat } from '@/lib/chat';
import { cn } from '@/lib/utils';

type Tab = 'team' | 'direct' | 'people';

export default function Messenger({ initialConversationId }: { initialConversationId: number | null }) {
    const { thread, teamId, conversations, people } = useChat();
    const requested = new URLSearchParams(usePage().url.split('?')[1] ?? '').get('tab');
    const [tab, setTab] = useState<Tab>(requested === 'direct' || requested === 'people' ? requested : 'team');
    const team = conversations.find((item) => item.kind === 'team');
    const directUnread = conversations.filter((item) => item.kind === 'direct').reduce((sum, item) => sum + item.unread, 0);
    const online = people.filter((person) => person.online);

    // The team tab always shows the group chat; a requested conversation opens once.
    useEffect(() => {
        if (initialConversationId) openConversation(initialConversationId);
    }, [initialConversationId]);

    useEffect(() => {
        if (tab === 'team' && teamId) openConversation(teamId);
        if (tab !== 'team' && thread?.conversationId === teamId) openConversation(null);
    }, [tab, teamId]);

    // Leaving the page must not keep marking a thread as read.
    useEffect(() => () => openConversation(null), []);

    const showingThread = Boolean(thread) && !(tab === 'team' && thread?.conversationId !== teamId);
    const tabs: { key: Tab; label: string; badge: number }[] = [
        { key: 'team', label: 'Team chat', badge: team?.unread ?? 0 },
        { key: 'direct', label: 'Direct messages', badge: directUnread },
        { key: 'people', label: 'People', badge: 0 },
    ];

    return (
        <AppLayout>
            <Head title="Messenger" />
            <PageHeader title="Messenger" description="Chat with the whole team, message people one-to-one, and share tasks, events, inspiration, and more." />
            <div className="mb-3 flex flex-wrap gap-2">
                {tabs.map((item) => (
                    <button
                        key={item.key}
                        type="button"
                        onClick={() => setTab(item.key)}
                        className={cn('flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm', tab === item.key ? 'bg-zinc-900 text-white' : 'bg-white text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-50')}
                    >
                        {item.label}
                        <UnreadBadge count={item.badge} className="h-4 min-w-4 text-[10px]" />
                    </button>
                ))}
            </div>
            <div className="grid h-[calc(100vh-17rem)] min-h-[28rem] overflow-hidden rounded-2xl border border-zinc-200 bg-white lg:h-[calc(100vh-14rem)] lg:grid-cols-[20rem_1fr]">
                <aside className={cn('min-h-0 flex-col border-zinc-100 lg:flex lg:border-r', showingThread && tab !== 'team' ? 'hidden' : tab === 'team' ? 'hidden' : 'flex')}>
                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {tab === 'team' && (
                            <div className="py-2">
                                <p className="flex items-center gap-1.5 px-4 pb-1 text-[11px] font-medium uppercase tracking-wide text-zinc-400">
                                    <Users className="h-3.5 w-3.5" /> Online now · {online.length}
                                </p>
                                {online.length === 0 && <p className="px-4 py-3 text-sm text-zinc-500">No one else is online right now.</p>}
                                <ul>
                                    {online.map((person) => (
                                        <li key={person.id} className="flex items-center gap-3 px-4 py-2">
                                            <Avatar name={person.name} online />
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-medium text-zinc-900">{person.name}</span>
                                                <span className="block truncate text-xs text-zinc-500">{person.job_title || 'Online'}</span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        {tab === 'direct' && <ConversationList activeId={thread?.conversationId} onOpen={(id) => openConversation(id)} />}
                        {tab === 'people' && <PeopleList onOpen={() => setTab('direct')} />}
                    </div>
                </aside>
                <section className={cn('min-h-0 flex-col lg:flex', showingThread ? 'flex' : 'hidden')}>
                    {showingThread ? (
                        <ChatThreadView className="h-full" onBack={tab === 'team' ? undefined : () => openConversation(null)} />
                    ) : (
                        <div className="grid h-full place-items-center p-6 text-center text-sm text-zinc-500">
                            <div>
                                <MessageCircle className="mx-auto mb-3 h-10 w-10 text-zinc-300" />
                                <p className="font-medium text-zinc-700">{tab === 'people' ? 'Pick a teammate to message' : 'Select a conversation'}</p>
                                <p className="mt-1">Use the paperclip to share a task, event, inspiration link, creative asset folder, or campaign.</p>
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
