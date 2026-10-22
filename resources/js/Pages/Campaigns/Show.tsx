import { Head, Link } from '@inertiajs/react';
import { PageHeader, PriorityBadge, StatusBadge } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';
import type { ContentSummary } from '@/types';

export default function CampaignShow({ campaign, content, canManage }: {
    campaign: { id: number; name: string; description: string | null; objective: string | null; status: string; start_date: string | null; end_date: string | null; brand: { name: string } | null; owner: { name: string } | null; members: { id: number; name: string }[] };
    content: ContentSummary[];
    canManage: boolean;
}) {
    return (
        <AppLayout>
            <Head title={campaign.name} />
            <PageHeader title={campaign.name} description={`${campaign.brand?.name ?? ''} · ${campaign.status}`} actions={canManage ? <Link href={`/campaigns/${campaign.id}/edit`} className="inline-flex h-10 items-center rounded-lg border border-zinc-300 bg-white px-3 text-sm">Edit</Link> : undefined} />
            <div className="mb-4 rounded-xl border border-zinc-200 bg-white p-4 text-sm">
                <p>{campaign.objective}</p>
                <p className="mt-2 text-zinc-600">{campaign.description}</p>
                <p className="mt-2 text-zinc-500">{formatDate(campaign.start_date)} – {formatDate(campaign.end_date)} · Owner {campaign.owner?.name ?? 'unassigned'}</p>
                <p className="mt-2">Members: {campaign.members.map((member) => member.name).join(', ') || 'None'}</p>
            </div>
            <div className="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white">
                {content.length === 0 && <p className="p-6 text-sm text-zinc-500">No ideas are linked to this campaign yet.</p>}
                {content.map((item) => (
                    <Link key={item.id} href={`/content/${item.id}`} className="block px-4 py-3 hover:bg-zinc-50">
                        <div className="flex flex-wrap items-center gap-2"><span className="font-medium">{item.title}</span><StatusBadge status={item.status} /><PriorityBadge priority={item.priority} /></div>
                        <p className="text-sm text-zinc-500">{item.reference_number} · {item.owner?.name ?? 'No owner'} · Due {formatDate(item.deadline)}</p>
                    </Link>
                ))}
            </div>
        </AppLayout>
    );
}
