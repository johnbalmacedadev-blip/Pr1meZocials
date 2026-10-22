import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import { EmptyState, PageHeader, Pagination, PriorityBadge, StatusBadge } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, visitFilters } from '@/lib/utils';
import type { ContentSummary, FormOptions, Paginator, SharedProps } from '@/types';

const presets = [
    ['All content', {}],
    ['My tasks', { owner: 'me' }],
    ['New ideas', { status: 'inbox' }],
    ['Awaiting review', { status: 'internal_review' }],
    ['Revision required', { status: 'revision_required' }],
    ['Overdue', { overdue: '1' }],
    ['Recently published', { status: 'published' }],
    ['Archived', { status: 'archived' }],
    ['Trash', { trashed: '1' }],
] as const;

export default function Index({ content, filters, options, savedViews }: { content: Paginator<ContentSummary>; filters: Record<string, string>; options: FormOptions; savedViews: { id: number; name: string; filters: Record<string, string> }[] }) {
    const permissions = usePage<SharedProps>().props.auth.user?.permissions ?? [];
    const lookups = usePage<SharedProps>().props.lookups;
    const save = useForm({ name: '', module: 'content', filters });

    function submitFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        visitFilters('/content', Object.fromEntries(new FormData(event.currentTarget).entries()) as Record<string, string>);
    }

    return (
        <AppLayout>
            <Head title="Content ideas" />
            <PageHeader title="Content ideas" description="Every idea keeps its owner, brief, platforms, and history in one record." actions={permissions.includes('content.create') ? <Link href="/content/create" className="inline-flex h-10 items-center rounded-lg bg-teal-800 px-3 text-sm font-medium text-white">New idea</Link> : undefined} />
            <div className="mb-4 flex gap-2 overflow-x-auto pb-1">
                {presets.map(([label, query]) => (
                    <Link key={label} href={`/content?${new URLSearchParams(query).toString()}`} className="shrink-0 rounded-full border border-zinc-200 bg-white px-3 py-1 text-sm hover:border-zinc-400">{label}</Link>
                ))}
                <Link href="/board" className="shrink-0 rounded-full border border-zinc-200 bg-white px-3 py-1 text-sm">Production board</Link>
                <Link href="/calendar" className="shrink-0 rounded-full border border-zinc-200 bg-white px-3 py-1 text-sm">Publishing calendar</Link>
            </div>
            <form onSubmit={submitFilters} className="mb-4 grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 md:grid-cols-4">
                <input name="q" defaultValue={filters.q ?? ''} placeholder="Search title or reference" className="rounded-lg border border-zinc-300 px-3 py-2 text-sm md:col-span-2" />
                <select name="brand_id" defaultValue={filters.brand_id ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm"><option value="">All brands</option>{options.brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}</select>
                <select name="status" defaultValue={filters.status ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm"><option value="">All statuses</option>{lookups?.statuses.map((status) => <option key={status.slug} value={status.slug}>{status.name}</option>)}</select>
                <select name="format" defaultValue={filters.format ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm"><option value="">All formats</option>{lookups?.formats.map((format) => <option key={format.value} value={format.value}>{format.label}</option>)}</select>
                <select name="platform_id" defaultValue={filters.platform_id ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm"><option value="">All platforms</option>{lookups?.platforms.map((platform) => <option key={platform.id} value={platform.id}>{platform.name}</option>)}</select>
                <select name="owner_id" defaultValue={filters.owner_id ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm"><option value="">All owners</option>{options.people.map((person) => <option key={person.id} value={person.id}>{person.name}</option>)}</select>
                <select name="priority" defaultValue={filters.priority ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm"><option value="">All priorities</option>{lookups?.priorities.map((priority) => <option key={priority.value} value={priority.value}>{priority.label}</option>)}</select>
                <select name="distribution" defaultValue={filters.distribution ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm"><option value="">Organic or paid</option>{lookups?.distributions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select>
                <select name="funnel_stage" defaultValue={filters.funnel_stage ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm"><option value="">All funnel stages</option>{lookups?.funnel_stages.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select>
                <select name="pillar_id" defaultValue={filters.pillar_id ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm"><option value="">All pillars</option>{options.pillars.map((pillar) => <option key={pillar.id} value={pillar.id}>{pillar.name}</option>)}</select>
                <input type="date" name="deadline_from" defaultValue={filters.deadline_from ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm" aria-label="Deadline from" />
                <div className="flex gap-2 md:col-span-2">
                    <button className="rounded-lg bg-zinc-900 px-3 py-2 text-sm text-white" type="submit">Filter</button>
                    {permissions.includes('content.export') && <a className="rounded-lg border border-zinc-300 px-3 py-2 text-sm" href={`/content/export?${new URLSearchParams(filters).toString()}`}>Export CSV</a>}
                </div>
            </form>
            <form onSubmit={(event) => { event.preventDefault(); save.post('/saved-views'); }} className="mb-4 flex gap-2">
                <input value={save.data.name} onChange={(event) => save.setData('name', event.target.value)} placeholder="Name this filter" className="rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
                <button className="rounded-lg border border-zinc-300 bg-white px-3 text-sm" type="submit">Save view</button>
                {savedViews.map((view) => (
                    <Link key={view.id} href={`/content?${new URLSearchParams(view.filters).toString()}`} className="rounded-full bg-zinc-200 px-3 py-2 text-sm">{view.name}</Link>
                ))}
            </form>
            {content.data.length === 0 ? (
                <EmptyState title="No ideas match these filters" body="Clear a filter or capture a new idea. Quick capture only needs a title, brand, and format." action={permissions.includes('content.create') ? <Link href="/content/create" className="text-sm font-medium text-teal-800">Capture an idea</Link> : undefined} />
            ) : (
                <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                    <div className="divide-y divide-zinc-100">
                        {content.data.map((item) => (
                            <Link key={item.id} href={`/content/${item.id}`} className="grid gap-2 px-4 py-3 hover:bg-zinc-50 md:grid-cols-[1fr_auto] md:items-center">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">{item.title}</span>
                                        <StatusBadge status={item.status} />
                                        <PriorityBadge priority={item.priority} />
                                        {item.is_overdue && <span className="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-900">Overdue</span>}
                                    </div>
                                    <p className="mt-1 text-sm text-zinc-500">{item.reference_number} · {item.brand?.name} · {item.format_label} · {item.owner?.name ?? 'No owner'} · Due {formatDate(item.deadline)}</p>
                                </div>
                                <div className="flex flex-wrap gap-1 text-xs text-zinc-600">
                                    {item.platforms.map((platform, index) => <span key={`${platform.slug}-${index}`} className="rounded-full bg-zinc-100 px-2 py-1">{platform.name}</span>)}
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>
            )}
            <Pagination {...content} />
        </AppLayout>
    );
}
