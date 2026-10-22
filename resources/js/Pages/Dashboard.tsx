import { Head, Link, usePage } from '@inertiajs/react';
import { Bar, BarChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { EmptyState, PageHeader, StatusBadge } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, visitFilters } from '@/lib/utils';
import type { FormOptions, SharedProps } from '@/types';

interface ChartRow { label: string; total: number; slug?: string; color?: string }
interface Metrics {
    period: { from: string; to: string };
    kpis: Record<string, number>;
    on_time_rate: number | null;
    on_time_sample: number;
    average_turnaround_days: number | null;
    by_status: ChartRow[];
    by_platform: ChartRow[];
    by_format: ChartRow[];
    by_campaign: ChartRow[];
    workload: ChartRow[];
    stale: { id: number; title: string; reference_number: string; updated_at: string | null; status: { name: string; slug: string; color: string } | null; owner: { name: string } | null; brand: { name: string } | null }[];
    performance: {
        demo_only: boolean;
        report_count: number;
        best: { content_id: number; title: string; platform: string; objective: string | null; metrics: { engagement_rate: number | null } }[];
        by_objective: { objective: string; pieces: number; avg_engagement_rate: number | null }[];
    };
    has_content: boolean;
}

const cards = [
    ['active', 'Active ideas'],
    ['awaiting_development', 'Awaiting development'],
    ['in_production', 'In production'],
    ['awaiting_review', 'Awaiting review'],
    ['revision_required', 'Needs revision'],
    ['overdue', 'Overdue'],
    ['approved_ready', 'Approved to publish'],
    ['scheduled_this_week', 'Scheduled this week'],
    ['published_in_period', 'Published in period'],
] as const;

export default function Dashboard({ metrics, filters, options }: { metrics: Metrics; filters: Record<string, string>; options: FormOptions }) {
    const canCreate = usePage<SharedProps>().props.auth.user?.permissions.includes('content.create');

    return (
        <AppLayout>
            <Head title="Dashboard" />
            <PageHeader title="Operations dashboard" description="Current pipeline counts are a snapshot. Output, turnaround, and performance use the selected period." />
            <form className="mb-5 grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 md:grid-cols-6" onSubmit={(event) => { event.preventDefault(); const data = new FormData(event.currentTarget); visitFilters('/dashboard', Object.fromEntries(data.entries()) as Record<string, string>); }}>
                <label className="text-sm">From<input className="mt-1 w-full rounded-lg border border-zinc-300 px-2 py-1.5" type="date" name="from" defaultValue={metrics.period.from} /></label>
                <label className="text-sm">To<input className="mt-1 w-full rounded-lg border border-zinc-300 px-2 py-1.5" type="date" name="to" defaultValue={metrics.period.to} /></label>
                <label className="text-sm">Brand<select className="mt-1 w-full rounded-lg border border-zinc-300 px-2 py-1.5" name="brand_id" defaultValue={filters.brand_id ?? ''}><option value="">All</option>{options.brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}</select></label>
                <label className="text-sm">Campaign<select className="mt-1 w-full rounded-lg border border-zinc-300 px-2 py-1.5" name="campaign_id" defaultValue={filters.campaign_id ?? ''}><option value="">All</option>{options.campaigns.map((campaign) => <option key={campaign.id} value={campaign.id}>{campaign.name}</option>)}</select></label>
                <label className="text-sm">Owner<select className="mt-1 w-full rounded-lg border border-zinc-300 px-2 py-1.5" name="user_id" defaultValue={filters.user_id ?? ''}><option value="">All</option>{options.people.map((person) => <option key={person.id} value={person.id}>{person.name}</option>)}</select></label>
                <div className="flex items-end"><button className="h-9 rounded-lg bg-zinc-900 px-3 text-sm text-white" type="submit">Apply</button></div>
            </form>

            {!metrics.has_content ? (
                <EmptyState title="No content in this view" body="Capture the first idea so the pipeline, calendar, and reports have something real to show." action={canCreate ? <Link className="text-sm font-medium text-teal-800" href="/content/create">Capture an idea</Link> : undefined} />
            ) : (
                <>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {cards.map(([key, label]) => (
                            <div key={key} className="rounded-xl border border-zinc-200 bg-white p-4">
                                <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">{label}</p>
                                <p className={`mt-2 text-3xl font-semibold ${key === 'overdue' && metrics.kpis[key] > 0 ? 'text-rose-700' : 'text-zinc-950'}`}>{metrics.kpis[key]}</p>
                            </div>
                        ))}
                    </div>
                    <div className="mt-4 grid gap-3 md:grid-cols-2">
                        <div className="rounded-xl border border-zinc-200 bg-white p-4">
                            <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">On-time completion</p>
                            <p className="mt-2 text-3xl font-semibold">{metrics.on_time_rate === null ? '—' : `${metrics.on_time_rate}%`}</p>
                            <p className="mt-1 text-sm text-zinc-500">{metrics.on_time_sample} published items in this period had a deadline. Published on or before that deadline counts as on time.</p>
                        </div>
                        <div className="rounded-xl border border-zinc-200 bg-white p-4">
                            <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">Average production turnaround</p>
                            <p className="mt-2 text-3xl font-semibold">{metrics.average_turnaround_days === null ? '—' : `${metrics.average_turnaround_days}d`}</p>
                            <p className="mt-1 text-sm text-zinc-500">Mean days from idea capture to publication for items published in the selected period.</p>
                        </div>
                    </div>
                    <div className="mt-4 grid gap-4 xl:grid-cols-2">
                        <Chart title="Current work by status" rows={metrics.by_status} />
                        <Chart title="Active workload by owner" rows={metrics.workload} />
                        <Chart title="Ideas created in period by platform" rows={metrics.by_platform} />
                        <Chart title="Ideas created in period by format" rows={metrics.by_format} />
                        <Chart title="Ideas created in period by campaign" rows={metrics.by_campaign} />
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
                                                <td className="py-2"><Link href={`/content/${row.content_id}`} className="font-medium text-teal-900">{row.title}</Link></td>
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

function Chart({ title, rows }: { title: string; rows: ChartRow[] }) {
    return (
        <section className="rounded-xl border border-zinc-200 bg-white p-4">
            <h2 className="mb-3 font-semibold">{title}</h2>
            {rows.length === 0 ? <p className="text-sm text-zinc-500">No records in this slice.</p> : (
                <div className="h-56">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={rows} layout="vertical" margin={{ left: 16, right: 8 }}>
                            <XAxis type="number" allowDecimals={false} />
                            <YAxis type="category" dataKey="label" width={120} tick={{ fontSize: 12 }} />
                            <Tooltip />
                            <Bar dataKey="total" fill="#0f766e" radius={4} />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            )}
        </section>
    );
}
