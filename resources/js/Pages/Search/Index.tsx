import { Head, Link } from '@inertiajs/react';
import { EmptyState, PageHeader, StatusBadge } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import type { ContentSummary } from '@/types';

export default function Search({ q, results }: { q: string; results: ContentSummary[] }) {
    return (
        <AppLayout>
            <Head title="Search" />
            <PageHeader title="Search" description={q ? `Results for “${q}”` : 'Search titles, descriptions, reference numbers, campaigns, and tags.'} />
            {q && results.length === 0 && <EmptyState title="No matches" body="Try a reference number, a campaign name, or a shorter phrase." />}
            <div className="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white">
                {results.map((item) => (
                    <Link key={item.id} href={`/content/${item.id}`} className="block px-4 py-3 hover:bg-zinc-50">
                        <div className="flex flex-wrap items-center gap-2"><span className="font-medium">{item.title}</span><StatusBadge status={item.status} /></div>
                        <p className="text-sm text-zinc-500">{item.reference_number} · {item.brand?.name} · {item.campaign?.name ?? 'No campaign'}</p>
                    </Link>
                ))}
            </div>
        </AppLayout>
    );
}
