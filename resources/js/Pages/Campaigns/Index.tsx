import { Head, Link } from '@inertiajs/react';
import { EmptyState, PageHeader, Pagination } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';

interface CampaignRow {
    id: number;
    name: string;
    status: string;
    start_date: string | null;
    end_date: string | null;
    content_items_count: number;
    brand?: { name: string };
    owner?: { name: string } | null;
}

export default function CampaignIndex({ campaigns, canManage }: { campaigns: { data: CampaignRow[]; prev_page_url: string | null; next_page_url: string | null; from: number | null; to: number | null; total: number }; canManage: boolean; filters: Record<string, string>; options: unknown }) {
    return (
        <AppLayout>
            <Head title="Campaigns" />
            <PageHeader title="Campaigns" description="Group ideas under the brand and time window they belong to." actions={canManage ? <Link href="/campaigns/create" className="inline-flex h-10 items-center rounded-lg bg-brand-700 px-3 text-sm font-medium text-white">New campaign</Link> : undefined} />
            {campaigns.data.length === 0 ? <EmptyState title="No campaigns yet" body="Create a campaign when a set of ideas shares a goal and a date range." action={canManage ? <Link href="/campaigns/create" className="text-sm font-medium text-brand-700">Create a campaign</Link> : undefined} /> : (
                <div className="grid gap-3 md:grid-cols-2">
                    {campaigns.data.map((campaign) => (
                        <Link key={campaign.id} href={`/campaigns/${campaign.id}`} className="rounded-xl border border-zinc-200 bg-white p-4 hover:border-zinc-400">
                            <div className="flex items-center justify-between gap-3"><h2 className="font-semibold">{campaign.name}</h2><span className="text-xs uppercase text-zinc-500">{campaign.status}</span></div>
                            <p className="mt-1 text-sm text-zinc-500">{campaign.brand?.name} · {campaign.content_items_count} ideas · {campaign.owner?.name ?? 'No owner'}</p>
                            <p className="mt-2 text-sm text-zinc-600">{campaign.start_date ?? 'Open'} to {campaign.end_date ?? 'open'}</p>
                        </Link>
                    ))}
                </div>
            )}
            <Pagination prev={campaigns.prev_page_url} next={campaigns.next_page_url} from={campaigns.from} to={campaigns.to} total={campaigns.total} />
        </AppLayout>
    );
}
