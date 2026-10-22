import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Button, Field, Input, PageHeader, Select, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import type { SharedProps } from '@/types';

interface Report {
    id: number;
    source: string;
    period_start: string;
    period_end: string;
    reach: number | null;
    impressions: number | null;
    likes: number | null;
    comments_count: number | null;
    shares: number | null;
    saves: number | null;
    link_clicks: number | null;
    leads: number | null;
    conversions: number | null;
    revenue: string | null;
    ad_spend: string | null;
    notes: string | null;
    metrics: Record<string, number | null>;
    content: { id: number | null; title: string | null; reference_number: string | null };
    platform: string | null;
}

export default function Performance({ reports, summary, versions, canManage }: {
    reports: { data: Report[] };
    summary: { demo_only: boolean; report_count: number; best: { title: string; platform: string; objective: string | null; metrics: { engagement_rate: number | null } }[]; by_objective: { objective: string; pieces: number; avg_engagement_rate: number | null }[] };
    versions: { id: number; label: string }[];
    filters: Record<string, string>;
    canManage: boolean;
}) {
    const formulas = usePage<SharedProps>().props.lookups?.formulas ?? {};
    const form = useForm({
        content_platform_version_id: versions[0] ? String(versions[0].id) : '',
        period_start: '', period_end: '', reach: '', impressions: '', views: '', video_views_3s: '', watch_time_seconds: '',
        likes: '', comments_count: '', shares: '', saves: '', link_clicks: '', leads: '', conversions: '', revenue: '', ad_spend: '', notes: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/performance');
    }

    return (
        <AppLayout>
            <Head title="Performance" />
            <PageHeader title="Performance reports" description="Enter results manually. Nothing here is synchronized from Facebook, Instagram, TikTok, or any other platform." />
            {summary.demo_only && <p className="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950">The current results are demo seed data for local exploration.</p>}
            <section className="mb-4 rounded-xl border border-zinc-200 bg-white p-4">
                <h2 className="font-semibold">How calculated metrics work</h2>
                <ul className="mt-2 space-y-2 text-sm text-zinc-700">
                    {Object.entries(formulas).map(([key, text]) => <li key={key}>{text}</li>)}
                </ul>
            </section>
            <section className="mb-4 rounded-xl border border-zinc-200 bg-white p-4">
                <h2 className="font-semibold">High performers by objective</h2>
                {summary.by_objective.length === 0 ? <p className="mt-2 text-sm text-zinc-500">No reports in the selected period.</p> : (
                    <ul className="mt-2 text-sm">{summary.by_objective.map((row) => <li key={row.objective}>{row.objective}: {row.pieces} versions, average engagement rate {row.avg_engagement_rate ?? '—'}%</li>)}</ul>
                )}
            </section>
            <div className="overflow-x-auto rounded-xl border border-zinc-200 bg-white">
                <table className="w-full min-w-[60rem] text-left text-sm">
                    <thead className="text-zinc-500"><tr>{['Content', 'Platform', 'Period', 'Source', 'Impressions', 'Reach', 'Engagement', 'CTR', 'Conv.', 'CPL', 'ROAS'].map((heading) => <th key={heading} className="px-3 py-2">{heading}</th>)}</tr></thead>
                    <tbody>
                        {reports.data.map((report) => (
                            <tr key={report.id} className="border-t border-zinc-100">
                                <td className="px-3 py-2">{report.content.reference_number} {report.content.title}</td>
                                <td className="px-3 py-2">{report.platform}</td>
                                <td className="px-3 py-2">{report.period_start} – {report.period_end}</td>
                                <td className="px-3 py-2">{report.source === 'demo_seed' ? 'Demo seed' : 'Manual'}</td>
                                <td className="px-3 py-2">{report.impressions ?? '—'}</td>
                                <td className="px-3 py-2">{report.reach ?? '—'}</td>
                                <td className="px-3 py-2">{report.metrics.engagement_rate ?? '—'}</td>
                                <td className="px-3 py-2">{report.metrics.click_through_rate ?? '—'}</td>
                                <td className="px-3 py-2">{report.metrics.conversion_rate ?? '—'}</td>
                                <td className="px-3 py-2">{report.metrics.cost_per_lead ?? '—'}</td>
                                <td className="px-3 py-2">{report.metrics.return_on_ad_spend ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {canManage && (
                <form onSubmit={submit} className="mt-4 grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 md:grid-cols-3">
                    <h2 className="md:col-span-3 font-semibold">Record a manual report</h2>
                    <div className="md:col-span-3"><Field label="Platform version" error={form.errors.content_platform_version_id}><Select value={form.data.content_platform_version_id} onChange={(event) => form.setData('content_platform_version_id', event.target.value)}>{versions.map((version) => <option key={version.id} value={version.id}>{version.label}</option>)}</Select></Field></div>
                    <Field label="Period start" error={form.errors.period_start}><Input type="date" value={form.data.period_start} onChange={(event) => form.setData('period_start', event.target.value)} required /></Field>
                    <Field label="Period end" error={form.errors.period_end}><Input type="date" value={form.data.period_end} onChange={(event) => form.setData('period_end', event.target.value)} required /></Field>
                    {(['reach', 'impressions', 'views', 'video_views_3s', 'watch_time_seconds', 'likes', 'comments_count', 'shares', 'saves', 'link_clicks', 'leads', 'conversions', 'revenue', 'ad_spend'] as const).map((field) => (
                        <Field key={field} label={field.replaceAll('_', ' ')}><Input value={form.data[field]} onChange={(event) => form.setData(field, event.target.value)} /></Field>
                    ))}
                    <div className="md:col-span-3"><Field label="Notes"><Textarea value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></Field></div>
                    <Button type="submit" disabled={form.processing}>Save report</Button>
                </form>
            )}
        </AppLayout>
    );
}
