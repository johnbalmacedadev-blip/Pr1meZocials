import { Head, Link } from '@inertiajs/react';
import { PageHeader, StatusBadge } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';
import type { ContentSummary } from '@/types';

export default function TeamShow({ member, content }: { member: { name: string; email: string; job_title: string | null; roles: { name: string }[] }; content: ContentSummary[] }) {
    return (
        <AppLayout>
            <Head title={member.name} />
            <PageHeader title={member.name} description={`${member.job_title ?? 'Team member'} · ${member.roles.map((role) => role.name).join(', ')} · ${member.email}`} />
            <div className="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white">
                {content.length === 0 && <p className="p-6 text-sm text-zinc-500">No visible assignments.</p>}
                {content.map((item) => (
                    <Link key={item.id} href={`/content/${item.id}`} className="block px-4 py-3 hover:bg-zinc-50">
                        <div className="flex flex-wrap items-center gap-2"><span className="font-medium">{item.title}</span><StatusBadge status={item.status} /></div>
                        <p className="text-sm text-zinc-500">{item.reference_number} · Owner {item.owner?.name ?? 'unassigned'} · Due {formatDate(item.deadline)}</p>
                    </Link>
                ))}
            </div>
        </AppLayout>
    );
}
