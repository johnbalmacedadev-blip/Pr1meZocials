import { Link, router, usePage } from '@inertiajs/react';
import * as Dropdown from '@radix-ui/react-dropdown-menu';
import {
    BarChart3, Bell, CalendarDays, Columns3, FolderOpen, LayoutDashboard, Lightbulb, LogOut, Megaphone, Send, Sparkles, CalendarRange,
    Menu, Plus, Search, Settings, UserRound, Users, X,
} from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';
import { Logo } from '@/Components/Logo';
import { Button } from '@/Components/ui';
import { cn, formatDate } from '@/lib/utils';
import type { SharedProps } from '@/types';

const nav = [
    { href: '/dashboard', label: 'Dashboard', icon: LayoutDashboard, show: () => true },
    { href: '/content', label: 'Content Ideas', icon: Lightbulb, show: (can: Can) => can('content.view') || can('content.view.any') },
    { href: '/inspiration', label: 'Inspiration', icon: Sparkles, show: (can: Can) => can('content.view') || can('content.view.any') || can('content.create') },
    { href: '/resources', label: 'Creative Assets', icon: FolderOpen, show: (can: Can) => can('content.view') || can('content.view.any') || can('content.create') },
    { href: '/board', label: 'Production Board', icon: Columns3, show: (can: Can) => can('content.view') || can('content.view.any') },
    { href: '/calendar', label: 'Content Calendar', icon: CalendarDays, show: (can: Can) => can('content.view') || can('content.view.any') },
    { href: '/posting-schedule', label: 'Posting Schedule', icon: CalendarRange, show: (can: Can) => can('content.view') || can('content.view.any') },
    { href: '/posting', label: 'Posting', icon: Send, show: (can: Can) => can('content.publish') },
    { href: '/campaigns', label: 'Campaigns', icon: Megaphone, show: (can: Can) => can('campaigns.view') },
    { href: '/team', label: 'Team', icon: Users, show: (can: Can) => can('team.view') },
    { href: '/performance', label: 'Performance', icon: BarChart3, show: (can: Can) => can('performance.view') || can('reports.view') },
    { href: '/settings', label: 'Settings', icon: Settings, show: (can: Can) => can('users.view') || can('users.manage') || can('brands.manage') || can('settings.manage') || can('taxonomy.manage') || can('tags.manage') || can('platforms.manage') || can('workflow.manage') },
];

type Can = (permission: string) => boolean;

export default function AppLayout({ title, children }: { title?: string; children: ReactNode }) {
    const { auth, flash, unreadNotifications, recentNotifications } = usePage<SharedProps>().props;
    const permissions = auth.user?.permissions ?? [];
    const can = (permission: string) => permissions.includes(permission);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const recent = recentNotifications ?? [];
    const items = nav.filter((item) => item.show(can));
    const current = typeof window !== 'undefined' ? window.location.pathname : '';

    function search(event: FormEvent) {
        event.preventDefault();
        router.get('/search', { q: query }, { preserveState: true });
        setOpen(false);
    }

    const links = (
        <nav className="space-y-1">
            {items.map((item) => {
                const active = current === item.href || (item.href !== '/dashboard' && current.startsWith(item.href));
                const Icon = item.icon;
                return (
                    <Link key={item.href} href={item.href} onClick={() => setOpen(false)} className={cn('flex items-center gap-3 rounded-lg px-3 py-2 text-sm', active ? 'bg-white/10 text-white' : 'text-zinc-300 hover:bg-white/5 hover:text-white')}>
                        <Icon className="h-4 w-4" />
                        {item.label}
                    </Link>
                );
            })}
        </nav>
    );

    return (
        <div className="min-h-screen lg:grid lg:grid-cols-[16rem_1fr]">
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
            <div className="min-w-0">
                <header className="sticky top-0 z-30 flex items-center gap-3 border-b border-zinc-200 bg-white/95 px-4 py-3 backdrop-blur">
                    <button className="rounded-lg p-2 text-zinc-700 hover:bg-zinc-100 lg:hidden" onClick={() => setOpen(true)} aria-label="Open navigation">
                        <Menu className="h-5 w-5" />
                    </button>
                    <form onSubmit={search} className="relative min-w-0 flex-1">
                        <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-zinc-400" />
                        <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search ideas, campaigns, tags" className="w-full rounded-lg border border-zinc-200 bg-zinc-50 py-2 pl-9 pr-3 text-sm outline-none focus:border-brand-700 focus:bg-white" aria-label="Search" />
                    </form>
                    <Dropdown.Root>
                        <Dropdown.Trigger className="relative rounded-lg p-2 text-zinc-700 hover:bg-zinc-100" aria-label="Notifications">
                            <Bell className="h-5 w-5" />
                            {unreadNotifications > 0 && <span className="absolute right-1 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-rose-600 px-1 text-[10px] font-semibold text-white">{unreadNotifications}</span>}
                        </Dropdown.Trigger>
                        <Dropdown.Portal>
                            <Dropdown.Content className="z-50 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-zinc-200 bg-white p-0 shadow-xl" align="end" sideOffset={8}>
                                <div className="border-b border-zinc-100 px-4 py-3">
                                    <h2 className="text-base font-semibold text-zinc-950">Notifications</h2>
                                </div>
                                <div className="max-h-96 overflow-auto">
                                    {recent.length === 0 && <p className="px-4 py-8 text-center text-sm text-zinc-500">You are caught up.</p>}
                                    {recent.map((note) => (
                                        <Dropdown.Item key={note.id} className={`block w-full cursor-pointer px-4 py-3 text-left outline-none data-[highlighted]:bg-zinc-50 ${note.read_at ? 'bg-white' : 'bg-brand-50'}`} onSelect={() => router.post(`/notifications/${note.id}/read`)}>
                                            <p className="text-sm font-medium text-zinc-950">{note.title}</p>
                                            <p className="mt-0.5 line-clamp-2 text-sm text-zinc-600">{note.body}</p>
                                            <p className="mt-1 text-xs text-zinc-500">{formatDate(note.created_at)}</p>
                                        </Dropdown.Item>
                                    ))}
                                </div>
                                <Dropdown.Item className="block cursor-pointer border-t border-zinc-100 px-4 py-3 text-center text-sm font-medium text-brand-700 outline-none data-[highlighted]:bg-zinc-50" onSelect={() => router.visit('/notifications')}>
                                    View all
                                </Dropdown.Item>
                            </Dropdown.Content>
                        </Dropdown.Portal>
                    </Dropdown.Root>
                    <Dropdown.Root>
                        <Dropdown.Trigger className="inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-zinc-100">
                            <UserRound className="h-4 w-4" />
                            <span className="hidden max-w-32 truncate sm:inline">{auth.user?.name}</span>
                        </Dropdown.Trigger>
                        <Dropdown.Portal>
                            <Dropdown.Content className="z-50 w-52 rounded-lg border border-zinc-200 bg-white p-1 shadow-lg" align="end">
                                <div className="px-2 py-1.5 text-xs text-zinc-500">{auth.user?.roles.map((role) => role.name).join(', ')}</div>
                                <Dropdown.Item asChild><Link href="/profile" className="block rounded-md px-2 py-1.5 text-sm outline-none hover:bg-zinc-100">Profile</Link></Dropdown.Item>
                                <Dropdown.Item asChild>
                                    <button className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-zinc-100" onClick={() => router.post('/logout')}>
                                        <LogOut className="h-4 w-4" /> Log out
                                    </button>
                                </Dropdown.Item>
                            </Dropdown.Content>
                        </Dropdown.Portal>
                    </Dropdown.Root>
                </header>
                {(flash.success || flash.error) && (
                    <div className={cn('mx-4 mt-4 rounded-lg px-4 py-3 text-sm', flash.error ? 'bg-rose-50 text-rose-900' : 'bg-emerald-50 text-emerald-900')}>
                        {flash.error || flash.success}
                    </div>
                )}
                <main className="mx-auto w-full max-w-7xl px-4 py-6">
                    {title && <h1 className="sr-only">{title}</h1>}
                    {children}
                </main>
            </div>
            {open && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <button className="absolute inset-0 bg-zinc-950/50" aria-label="Close navigation" onClick={() => setOpen(false)} />
                    <div className="absolute inset-y-0 left-0 flex w-72 flex-col bg-zinc-950 p-4 text-zinc-300">
                        <div className="mb-4 flex items-center justify-between">
                            <Logo className="h-8" />
                            <button onClick={() => setOpen(false)} aria-label="Close" className="rounded-md p-1 hover:bg-white/10"><X className="h-4 w-4" /></button>
                        </div>
                        {links}
                        {can('content.create') && <Button className="mt-4" onClick={() => router.visit('/content/create')}>New idea</Button>}
                    </div>
                </div>
            )}
        </div>
    );
}
