import { Head, Link, usePage } from '@inertiajs/react';
import { addMonths, eachDayOfInterval, endOfWeek, format, isSameMonth, isToday, parseISO, startOfWeek } from 'date-fns';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Button, EmptyState, Modal, PageHeader, StatusBadge } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatDate, visitFilters } from '@/lib/utils';
import type { FormOptions, SharedProps } from '@/types';

interface Metrics {
    period: { from: string; to: string };
    kpis: Record<string, number>;
    on_time_rate: number | null;
    on_time_sample: number;
    average_turnaround_days: number | null;
    stale: { id: number; title: string; reference_number: string; updated_at: string | null; status: { name: string; slug: string; color: string } | null; owner: { name: string } | null; brand: { name: string } | null }[];
    tasks: { id: number; title: string; reference_number: string; deadline: string | null; role: string; status: { name: string; slug: string; color: string } | null; brand: { name: string } | null }[];
    performance: {
        demo_only: boolean;
        report_count: number;
        best: { content_id: number; title: string; platform: string; objective: string | null; metrics: { engagement_rate: number | null } }[];
        by_objective: { objective: string; pieces: number; avg_engagement_rate: number | null }[];
    };
    has_content: boolean;
}

interface ScheduleEvent {
    id: string | number;
    content_id: number | null;
    href?: string | null;
    title: string;
    date: string;
    time: string;
    platform: string | null;
    brand: string | null;
    kind?: 'publish' | 'facebook';
}

interface Schedule {
    month: string;
    label: string;
    events: ScheduleEvent[];
}

const cards = [
    ['active', 'Active ideas'],
    ['awaiting_development', 'New ideas'],
    ['ready_for_production', 'Ready for production'],
    ['in_production', 'In progress'],
    ['awaiting_review', 'For review'],
    ['overdue', 'Overdue'],
    ['approved_ready', 'Approved to publish'],
    ['scheduled_this_week', 'Posts this week'],
    ['published_in_period', 'Published in period'],
] as const;

export default function Dashboard({ metrics, filters, options, schedule }: { metrics: Metrics; filters: Record<string, string>; options: FormOptions; schedule: Schedule }) {
    const canCreate = usePage<SharedProps>().props.auth.user?.permissions.includes('content.create');
    const [scheduleOpen, setScheduleOpen] = useState(false);
    const [filtersOpen, setFiltersOpen] = useState(false);
    const scheduleFilters = { ...filters, from: metrics.period.from, to: metrics.period.to };

    return (
        <AppLayout>
            <Head title="Dashboard" />
            <PageHeader title="Operations dashboard" description="Current pipeline counts are a snapshot. Output, turnaround, and performance use the selected period." />
            <section className="mb-5 rounded-xl border border-zinc-200 bg-white">
                <button
                    type="button"
                    className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left md:hidden"
                    aria-expanded={filtersOpen}
                    onClick={() => setFiltersOpen((open) => !open)}
                >
                    <span>
                        <span className="block text-sm font-semibold text-zinc-900">Date filter</span>
                        <span className="block text-xs text-zinc-500">{formatDate(metrics.period.from)} – {formatDate(metrics.period.to)}</span>
                    </span>
                    <ChevronDown className={cn('h-4 w-4 shrink-0 text-zinc-500 transition-transform', filtersOpen && 'rotate-180')} />
                </button>
                <form
                    className={cn(
                        'grid gap-3 border-t border-zinc-100 p-4 md:grid-cols-6 md:border-0',
                        filtersOpen ? 'grid' : 'hidden md:grid',
                    )}
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        visitFilters('/dashboard', Object.fromEntries(data.entries()) as Record<string, string>);
                    }}
                >
                    <p className="hidden text-sm font-semibold text-zinc-900 md:col-span-6 md:block">Date filter</p>
                    <label className="text-sm">From<input className="mt-1 w-full rounded-lg border border-zinc-300 px-2 py-1.5" type="date" name="from" defaultValue={metrics.period.from} /></label>
                    <label className="text-sm">To<input className="mt-1 w-full rounded-lg border border-zinc-300 px-2 py-1.5" type="date" name="to" defaultValue={metrics.period.to} /></label>
                    <label className="text-sm">Brand<select className="mt-1 w-full rounded-lg border border-zinc-300 px-2 py-1.5" name="brand_id" defaultValue={filters.brand_id ?? ''}><option value="">All</option>{options.brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}</select></label>
                    <label className="text-sm">Campaign<select className="mt-1 w-full rounded-lg border border-zinc-300 px-2 py-1.5" name="campaign_id" defaultValue={filters.campaign_id ?? ''}><option value="">All</option>{options.campaigns.map((campaign) => <option key={campaign.id} value={campaign.id}>{campaign.name}</option>)}</select></label>
                    <label className="text-sm">Owner<select className="mt-1 w-full rounded-lg border border-zinc-300 px-2 py-1.5" name="user_id" defaultValue={filters.user_id ?? ''}><option value="">All</option>{options.people.map((person) => <option key={person.id} value={person.id}>{person.name}</option>)}</select></label>
                    <div className="flex items-end"><button className="h-9 rounded-lg bg-zinc-900 px-3 text-sm text-white" type="submit">Apply</button></div>
                    <input type="hidden" name="month" value={schedule.month} />
                </form>
            </section>
            <section className="mb-5 rounded-xl border border-zinc-200 bg-white p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="font-semibold">Your tasks</h2>
                        <p className="text-sm text-zinc-500">New and recent tasks assigned to you.</p>
                    </div>
                    <Link href="/board" className="text-sm font-medium text-brand-700">View all</Link>
                </div>
                <div className="mt-3 divide-y divide-zinc-100">
                    {metrics.tasks.length === 0 && <p className="py-3 text-sm text-zinc-500">No new tasks assigned to you.</p>}
                    {metrics.tasks.map((task) => (
                        <Link key={task.id} href={`/content/${task.id}`} className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm hover:bg-zinc-50">
                            <span><span className="font-medium">{task.title}</span><span className="ml-2 text-zinc-500">{task.reference_number} · {task.role} · {task.brand?.name ?? 'No brand'} · Due {formatDate(task.deadline)}</span></span>
                            <StatusBadge status={task.status} />
                        </Link>
                    ))}
                </div>
            </section>
            <div className="mb-5">
                <Button type="button" variant="secondary" onClick={() => setScheduleOpen(true)}>View schedule</Button>
            </div>
            <Modal open={scheduleOpen} onOpenChange={setScheduleOpen} title="Schedule" contentClassName="!w-[min(100%-2rem,72rem)] max-h-[min(100%-2rem,92vh)] overflow-auto">
                <MonthSchedule schedule={schedule} filters={scheduleFilters} />
            </Modal>

            {!metrics.has_content ? (
                <EmptyState title="No content in this view" body="Capture the first idea so the pipeline, calendar, and reports have something real to show." action={canCreate ? <Link className="text-sm font-medium text-brand-700" href="/content/create">Capture an idea</Link> : undefined} />
            ) : (
                <>
                    {/* Phones: one compact 3-column panel so every count fits on screen. */}
                    <div className="grid grid-cols-3 gap-px overflow-hidden rounded-xl border border-zinc-200 bg-zinc-200 sm:hidden">
                        {cards.map(([key, label]) => (
                            <div key={key} className="flex flex-col justify-between bg-white px-2.5 py-2">
                                <p className="text-[11px] leading-tight text-zinc-500">{label}</p>
                                <p className={`text-lg font-semibold leading-snug ${key === 'overdue' && metrics.kpis[key] > 0 ? 'text-rose-700' : 'text-zinc-950'}`}>{metrics.kpis[key]}</p>
                            </div>
                        ))}
                    </div>
                    <div className="hidden gap-3 sm:grid sm:grid-cols-2 xl:grid-cols-3">
                        {cards.map(([key, label]) => (
                            <div key={key} className="rounded-xl border border-zinc-200 bg-white p-4">
                                <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">{label}</p>
                                <p className={`mt-2 text-3xl font-semibold ${key === 'overdue' && metrics.kpis[key] > 0 ? 'text-rose-700' : 'text-zinc-950'}`}>{metrics.kpis[key]}</p>
                            </div>
                        ))}
                    </div>
                    <div className="mt-3 grid grid-cols-2 gap-2 sm:mt-4 sm:gap-3">
                        <div className="rounded-xl border border-zinc-200 bg-white px-2.5 py-2 sm:p-4">
                            <p className="text-[11px] leading-tight text-zinc-500 sm:text-xs sm:font-medium sm:uppercase sm:tracking-wide">On-time completion</p>
                            <p className="text-lg font-semibold sm:mt-2 sm:text-3xl">{metrics.on_time_rate === null ? '—' : `${metrics.on_time_rate}%`}</p>
                            <p className="mt-1 hidden text-sm text-zinc-500 sm:block">{metrics.on_time_sample} published items in this period had a deadline. Published on or before that deadline counts as on time.</p>
                        </div>
                        <div className="rounded-xl border border-zinc-200 bg-white px-2.5 py-2 sm:p-4">
                            <p className="text-[11px] leading-tight text-zinc-500 sm:text-xs sm:font-medium sm:uppercase sm:tracking-wide">Avg. turnaround</p>
                            <p className="text-lg font-semibold sm:mt-2 sm:text-3xl">{metrics.average_turnaround_days === null ? '—' : `${metrics.average_turnaround_days}d`}</p>
                            <p className="mt-1 hidden text-sm text-zinc-500 sm:block">Mean days from idea capture to publication for items published in the selected period.</p>
                        </div>
                    </div>
                    <section className="mt-4 rounded-xl border border-zinc-200 bg-white p-4">
                        <h2 className="font-semibold">Waiting too long</h2>
                        <p className="text-sm text-zinc-500">Open work with no update for at least a week. This is a bottleneck list, not a ranking.</p>
                        <div className="mt-3 divide-y divide-zinc-100">
                            {metrics.stale.length === 0 && <p className="py-3 text-sm text-zinc-500">Nothing in the current filters has gone quiet.</p>}
                            {metrics.stale.map((item) => (
                                <Link key={item.id} href={`/content/${item.id}`} className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm hover:bg-zinc-50">
                                    <span><span className="font-medium">{item.title}</span><span className="ml-2 text-zinc-500">{item.reference_number} · {item.owner?.name ?? 'Unassigned'}</span></span>
                                    <span className="flex items-center gap-2"><StatusBadge status={item.status} /><span className="text-zinc-500">{formatDate(item.updated_at)}</span></span>
                                </Link>
                            ))}
                        </div>
                    </section>
                    <section className="mt-4 rounded-xl border border-zinc-200 bg-white p-4">
                        <h2 className="font-semibold">Published performance</h2>
                        <p className="text-sm text-zinc-500">Marketing outcomes are separate from production counts. Engagement rate uses impressions as the denominator.</p>
                        {metrics.performance.demo_only && <p className="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950">These results are demo seed data. They were not synchronized from a social platform.</p>}
                        {metrics.performance.report_count === 0 ? (
                            <p className="mt-3 text-sm text-zinc-500">No performance reports overlap this period. Record results on a published platform version when you have them.</p>
                        ) : (
                            <div className="mt-3 overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="text-zinc-500"><tr><th className="py-2">Content</th><th>Platform</th><th>Objective</th><th>Engagement rate</th></tr></thead>
                                    <tbody>
                                        {metrics.performance.best.map((row) => (
                                            <tr key={`${row.content_id}-${row.platform}`} className="border-t border-zinc-100">
                                                <td className="py-2"><Link href={`/content/${row.content_id}`} className="font-medium text-brand-800">{row.title}</Link></td>
                                                <td>{row.platform}</td>
                                                <td>{row.objective ?? '—'}</td>
                                                <td>{row.metrics.engagement_rate === null ? '—' : `${row.metrics.engagement_rate}%`}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </>
            )}
        </AppLayout>
    );
}

function MonthSchedule({ schedule, filters }: { schedule: Schedule; filters: Record<string, string> }) {
    const anchor = parseISO(schedule.month);
    const days = eachDayOfInterval({
        start: startOfWeek(anchor, { weekStartsOn: 1 }),
        end: endOfWeek(new Date(anchor.getFullYear(), anchor.getMonth() + 1, 0), { weekStartsOn: 1 }),
    });

    function openMonth(month: string) {
        visitFilters('/dashboard', { ...filters, month });
    }

    return (
        <div>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-zinc-500">Publishing dates and Facebook posts for the month. Same Facebook items as Posting Schedule.</p>
                <div className="flex flex-wrap items-center gap-2">
                    <button type="button" className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm" onClick={() => openMonth(format(addMonths(anchor, -1), 'yyyy-MM-dd'))}>Previous</button>
                    <button type="button" className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm" onClick={() => openMonth(format(new Date(), 'yyyy-MM-dd'))}>Today</button>
                    <button type="button" className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm" onClick={() => openMonth(format(addMonths(anchor, 1), 'yyyy-MM-dd'))}>Next</button>
                    <strong className="px-1 text-sm">{schedule.label}</strong>
                </div>
            </div>
            <div className="overflow-x-auto">
                <div className="min-w-[52rem]">
                    <div className="mb-1 grid grid-cols-7 text-center text-xs font-medium text-zinc-500">
                        {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((day) => <div key={day}>{day}</div>)}
                    </div>
                    <div className="grid grid-cols-7 gap-1">
                        {days.map((day) => {
                            const key = format(day, 'yyyy-MM-dd');
                            const items = schedule.events.filter((event) => event.date === key);
                            const shown = items.slice(0, 3);
                            return (
                                <div key={key} className={`min-h-28 rounded-lg border p-1.5 ${isSameMonth(day, anchor) ? 'border-zinc-200 bg-white' : 'border-zinc-100 bg-zinc-50'} ${isToday(day) ? 'ring-2 ring-brand-700' : ''}`}>
                                    <p className={`text-xs font-medium ${isSameMonth(day, anchor) ? 'text-zinc-700' : 'text-zinc-400'}`}>{format(day, 'd')}</p>
                                    <div className="mt-1 space-y-1">
                                        {shown.map((event) => (
                                            <Link
                                                key={event.id}
                                                href={event.href || (event.content_id ? `/content/${event.content_id}` : '/posting')}
                                                className={`block rounded-md px-1.5 py-1 text-xs leading-4 text-zinc-800 hover:ring-1 hover:ring-brand-700 ${event.kind === 'facebook' ? 'bg-sky-50' : 'bg-brand-50'}`}
                                            >
                                                <span className="block truncate font-medium">{event.time} {event.title}</span>
                                                <span className="block truncate text-zinc-500">{event.platform ?? 'Unscheduled platform'}</span>
                                            </Link>
                                        ))}
                                        {items.length > shown.length && <p className="px-1 text-xs text-zinc-500">+{items.length - shown.length} more</p>}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );
}
