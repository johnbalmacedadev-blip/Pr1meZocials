import { Link, router, usePage } from '@inertiajs/react';
import * as Dropdown from '@radix-ui/react-dropdown-menu';
import {
    BarChart3, Bell, CalendarDays, Columns3, FolderOpen, LayoutDashboard, Lightbulb, LogOut, Megaphone, MoreHorizontal, Plus, Search, Send, Settings, Sparkles, CalendarRange,
    UserRound, Users, X,
} from 'lucide-react';
import { FormEvent, ReactNode, useMemo, useState } from 'react';
import { Logo } from '@/Components/Logo';
import { Button } from '@/Components/ui';
import { cn, formatDate } from '@/lib/utils';
import type { SharedProps } from '@/types';

const nav = [
    { href: '/dashboard', label: 'Dashboard', icon: LayoutDashboard, show: () => true },
    { href: '/content', label: 'Content Ideas', icon: Lightbulb, show: (can: Can) => can('content.view') || can('content.view.any') },
    { href: '/inspiration', label: 'Inspiration', icon: Sparkles, show: (can: Can) => can('inspiration.view') || can('content.view') || can('content.view.any') || can('content.create') },
    { href: '/resources', label: 'Creative Assets', icon: FolderOpen, show: (can: Can) => can('resources.view') || can('assets.view') || can('content.view') || can('content.view.any') || can('content.create') },
    { href: '/board', label: 'Production Board', icon: Columns3, show: (can: Can) => can('board.view') || can('content.view') || can('content.view.any') },
    { href: '/calendar', label: 'Content Calendar', icon: CalendarDays, show: (can: Can) => can('calendar.view') || can('content.view') || can('content.view.any') },
    { href: '/posting-schedule', label: 'Posting Schedule', icon: CalendarRange, show: (can: Can) => can('posting_schedule.view') || can('content.view') || can('content.view.any') },
    { href: '/posting', label: 'Posting', icon: Send, show: (can: Can) => can('posting.view') || can('posting.create') || can('content.publish') },
    { href: '/campaigns', label: 'Campaigns', icon: Megaphone, show: (can: Can) => can('campaigns.view') },
    { href: '/team', label: 'Team', icon: Users, show: (can: Can) => can('team.view') },
    { href: '/performance', label: 'Performance', icon: BarChart3, show: (can: Can) => can('performance.view') || can('reports.view') },
    { href: '/settings', label: 'Settings', icon: Settings, show: (can: Can) => can('settings.view') || can('settings.update') || can('settings.manage') || can('brands.manage') || can('users.manage') || can('taxonomy.manage') || can('tags.manage') || can('platforms.manage') || can('workflow.manage') },
];

type Can = (permission: string) => boolean;

type TabItem =
    | { kind: 'link'; href: string; label: string; icon: typeof LayoutDashboard }
    | { kind: 'create'; href: string; label: string }
    | { kind: 'more'; label: string };

function pathIsActive(current: string, href: string): boolean {
    if (href === '/dashboard') {
        return current === '/dashboard' || current === '/';
    }

    return current === href || current.startsWith(`${href}/`);
}

export default function AppLayout({ title, children }: { title?: string; children: ReactNode }) {
    const page = usePage<SharedProps>();
    const { auth, flash, unreadNotifications, recentNotifications } = page.props;
    const permissions = auth.user?.permissions ?? [];
    const can = (permission: string) => permissions.includes(permission);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const recent = recentNotifications ?? [];
    const items = nav.filter((item) => item.show(can));
    const current = (page.url.split('?')[0] || '/').replace(/\/$/, '') || '/';

    const mobileTabs = useMemo((): TabItem[] => {
        const tabs: TabItem[] = [
            { kind: 'link', href: '/dashboard', label: 'Home', icon: LayoutDashboard },
        ];

        if (can('content.view') || can('content.view.any')) {
            tabs.push({ kind: 'link', href: '/content', label: 'Ideas', icon: Lightbulb });
        } else if (can('campaigns.view')) {
            tabs.push({ kind: 'link', href: '/campaigns', label: 'Campaigns', icon: Megaphone });
        }

        if (can('content.create')) {
            tabs.push({ kind: 'create', href: '/content/create', label: 'New' });
        } else if (can('content.view') || can('content.view.any')) {
            tabs.push({ kind: 'link', href: '/board', label: 'Board', icon: Columns3 });
        }

        if (can('content.publish')) {
            tabs.push({ kind: 'link', href: '/posting', label: 'Post', icon: Send });
        } else if (can('content.view') || can('content.view.any')) {
            tabs.push({ kind: 'link', href: '/calendar', label: 'Calendar', icon: CalendarDays });
        } else if (can('team.view')) {
            tabs.push({ kind: 'link', href: '/team', label: 'Team', icon: Users });
        }

        tabs.push({ kind: 'more', label: 'More' });

        return tabs.slice(0, 5);
    }, [permissions]);

    function search(event: FormEvent) {
        event.preventDefault();
        router.get('/search', { q: query }, { preserveState: true });
        setOpen(false);
    }

    const links = (
        <nav className="space-y-1">
            {items.map((item) => {
                const active = pathIsActive(current, item.href);
                const Icon = item.icon;
                return (
                    <Link
                        key={item.href}
                        href={item.href}
                        onClick={() => setOpen(false)}
                        className={cn(
                            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition',
                            active ? 'bg-white/10 text-white' : 'text-zinc-300 hover:bg-white/5 hover:text-white',
                        )}
                    >
                        <Icon className="h-4 w-4" />
                        {item.label}
                    </Link>
                );
            })}
        </nav>
    );

    return (
        <div className="min-h-screen bg-zinc-50 lg:grid lg:grid-cols-[16rem_1fr]">
            <aside className="hidden bg-zinc-950 text-zinc-300 lg:flex lg:flex-col lg:px-4 lg:py-5">
                <Link href="/dashboard" className="mb-6 block px-2">
                    <Logo />
                    <span className="mt-2 block text-xs text-zinc-400">Creative operations</span>
                </Link>
                {links}
                {can('content.create') && (
                    <Link href="/content/create" className="mt-4 inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-3 py-2 text-sm font-medium text-white hover:bg-brand-800">
                        <Plus className="h-4 w-4" /> New idea
                    </Link>
                )}
            </aside>

            <div className="flex min-h-screen min-w-0 flex-col">
                <header className="sticky top-0 z-30 border-b border-zinc-200/80 bg-white/90 px-3 py-2.5 backdrop-blur-md supports-[backdrop-filter]:bg-white/80 lg:px-4 lg:py-3">
                    <div className="flex items-center gap-2 sm:gap-3">
                        <Link href="/dashboard" className="shrink-0 lg:hidden" aria-label="Home">
                            <Logo variant="mark" className="h-8 w-8" />
                        </Link>
                        <form onSubmit={search} className="relative min-w-0 flex-1">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                            <input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Search"
                                className="w-full rounded-full border border-zinc-200 bg-zinc-100/80 py-2 pl-9 pr-3 text-sm outline-none transition focus:border-brand-700 focus:bg-white focus:ring-2 focus:ring-brand-700/15"
                                aria-label="Search"
                            />
                        </form>
                        <Dropdown.Root>
                            <Dropdown.Trigger className="relative rounded-full p-2 text-zinc-700 hover:bg-zinc-100" aria-label="Notifications">
                                <Bell className="h-5 w-5" />
                                {unreadNotifications > 0 && (
                                    <span className="absolute right-0.5 top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-rose-600 px-1 text-[10px] font-semibold text-white">
                                        {unreadNotifications}
                                    </span>
                                )}
                            </Dropdown.Trigger>
                            <Dropdown.Portal>
                                <Dropdown.Content className="z-50 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-zinc-200 bg-white p-0 shadow-xl" align="end" sideOffset={8}>
                                    <div className="border-b border-zinc-100 px-4 py-3">
                                        <h2 className="text-base font-semibold text-zinc-950">Notifications</h2>
                                    </div>
                                    <div className="max-h-96 overflow-auto">
                                        {recent.length === 0 && <p className="px-4 py-8 text-center text-sm text-zinc-500">You are caught up.</p>}
                                        {recent.map((note) => (
                                            <Dropdown.Item
                                                key={note.id}
                                                className={`block w-full cursor-pointer px-4 py-3 text-left outline-none data-[highlighted]:bg-zinc-50 ${note.read_at ? 'bg-white' : 'bg-brand-50'}`}
                                                onSelect={() => router.post(`/notifications/${note.id}/read`)}
                                            >
                                                <p className="text-sm font-medium text-zinc-950">{note.title}</p>
                                                <p className="mt-0.5 line-clamp-2 text-sm text-zinc-600">{note.body}</p>
                                                <p className="mt-1 text-xs text-zinc-500">{formatDate(note.created_at)}</p>
                                            </Dropdown.Item>
                                        ))}
                                    </div>
                                    <Dropdown.Item
                                        className="block cursor-pointer border-t border-zinc-100 px-4 py-3 text-center text-sm font-medium text-brand-700 outline-none data-[highlighted]:bg-zinc-50"
                                        onSelect={() => router.visit('/notifications')}
                                    >
                                        View all
                                    </Dropdown.Item>
                                </Dropdown.Content>
                            </Dropdown.Portal>
                        </Dropdown.Root>
                        <Dropdown.Root>
                            <Dropdown.Trigger className="inline-flex items-center gap-2 rounded-full px-2 py-1.5 text-sm hover:bg-zinc-100">
                                <span className="grid h-8 w-8 place-items-center rounded-full bg-brand-50 text-brand-700">
                                    <UserRound className="h-4 w-4" />
                                </span>
                                <span className="hidden max-w-32 truncate sm:inline">{auth.user?.name}</span>
                            </Dropdown.Trigger>
                            <Dropdown.Portal>
                                <Dropdown.Content className="z-50 w-52 rounded-2xl border border-zinc-200 bg-white p-1 shadow-lg" align="end">
                                    <div className="px-2 py-1.5 text-xs text-zinc-500">{auth.user?.roles.map((role) => role.name).join(', ')}</div>
                                    <Dropdown.Item asChild>
                                        <Link href="/profile" className="block rounded-xl px-2 py-1.5 text-sm outline-none hover:bg-zinc-100">Profile</Link>
                                    </Dropdown.Item>
                                    <Dropdown.Item asChild>
                                        <button className="flex w-full items-center gap-2 rounded-xl px-2 py-1.5 text-left text-sm hover:bg-zinc-100" onClick={() => router.post('/logout')}>
                                            <LogOut className="h-4 w-4" /> Log out
                                        </button>
                                    </Dropdown.Item>
                                </Dropdown.Content>
                            </Dropdown.Portal>
                        </Dropdown.Root>
                    </div>
                </header>

                {(flash.success || flash.error) && (
                    <div className={cn('mx-3 mt-3 rounded-2xl px-4 py-3 text-sm lg:mx-4 lg:mt-4', flash.error ? 'bg-rose-50 text-rose-900' : 'bg-emerald-50 text-emerald-900')}>
                        {flash.error || flash.success}
                    </div>
                )}

                <main className="mx-auto w-full max-w-7xl flex-1 px-3 py-4 pb-[calc(5.5rem+env(safe-area-inset-bottom))] lg:px-4 lg:py-6 lg:pb-6">
                    {title && <h1 className="sr-only">{title}</h1>}
                    {children}
                </main>
            </div>

            {/* Mobile app-style bottom tabs */}
            <nav
                className="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200/90 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_30px_rgba(15,23,42,0.08)] backdrop-blur-md lg:hidden"
                aria-label="Primary"
            >
                <ul className="mx-auto grid max-w-lg grid-cols-5 items-end px-1 pt-1">
                    {mobileTabs.map((tab) => {
                        if (tab.kind === 'create') {
                            return (
                                <li key="create" className="relative flex justify-center pb-2">
                                    <Link
                                        href={tab.href}
                                        className="-mt-5 flex h-14 w-14 items-center justify-center rounded-full bg-brand-700 text-white shadow-lg shadow-brand-700/30 ring-4 ring-white transition hover:bg-brand-800 active:scale-95"
                                        aria-label="New idea"
                                    >
                                        <Plus className="h-7 w-7" strokeWidth={2.5} />
                                    </Link>
                                </li>
                            );
                        }

                        if (tab.kind === 'more') {
                            const active = open;
                            return (
                                <li key="more" className="flex justify-center">
                                    <button
                                        type="button"
                                        onClick={() => setOpen(true)}
                                        className={cn(
                                            'flex min-w-[3.5rem] flex-col items-center gap-0.5 px-2 py-2 text-[10px] font-medium transition',
                                            active ? 'text-brand-700' : 'text-zinc-500',
                                        )}
                                    >
                                        <MoreHorizontal className={cn('h-6 w-6', active && 'text-brand-700')} strokeWidth={active ? 2.4 : 2} />
                                        {tab.label}
                                    </button>
                                </li>
                            );
                        }

                        const Icon = tab.icon;
                        const active = pathIsActive(current, tab.href);
                        return (
                            <li key={tab.href} className="flex justify-center">
                                <Link
                                    href={tab.href}
                                    className={cn(
                                        'flex min-w-[3.5rem] flex-col items-center gap-0.5 px-2 py-2 text-[10px] font-medium transition',
                                        active ? 'text-brand-700' : 'text-zinc-500 hover:text-zinc-800',
                                    )}
                                >
                                    <Icon className={cn('h-6 w-6', active && 'text-brand-700')} strokeWidth={active ? 2.4 : 2} />
                                    {tab.label}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>

            {open && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <button className="absolute inset-0 bg-zinc-950/50 backdrop-blur-[1px]" aria-label="Close navigation" onClick={() => setOpen(false)} />
                    <div className="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-auto rounded-t-3xl bg-zinc-950 p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] text-zinc-300 shadow-2xl">
                        <div className="mx-auto mb-3 h-1 w-10 rounded-full bg-white/20" />
                        <div className="mb-4 flex items-center justify-between px-1">
                            <div>
                                <Logo className="h-8" />
                                <p className="mt-1 text-xs text-zinc-500">All sections</p>
                            </div>
                            <button type="button" onClick={() => setOpen(false)} aria-label="Close" className="rounded-full p-2 hover:bg-white/10">
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        {links}
                        {can('content.create') && (
                            <Button className="mt-4 w-full" onClick={() => { setOpen(false); router.visit('/content/create'); }}>
                                <Plus className="h-4 w-4" /> New idea
                            </Button>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
