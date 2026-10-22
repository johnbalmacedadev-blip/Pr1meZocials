import { Head, Link } from '@inertiajs/react';
import { PendingNotes, SendNote, type TaskNoteItem } from '@/Components/TaskNotes';
import { PageHeader, StatusBadge } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';
import type { ContentSummary } from '@/types';

export default function TeamShow({ member, content, notes, canSendNote }: { member: { id: number; name: string; email: string; job_title: string | null; roles: { name: string }[] }; content: ContentSummary[]; notes: TaskNoteItem[]; canSendNote: boolean }) {
    return (
        <AppLayout>
            <Head title={member.name} />
            <PageHeader title={member.name} description={`${member.job_title ?? 'Team member'} · ${member.roles.map((role) => role.name).join(', ')} · ${member.email}`} />
            <section className="mb-4 rounded-xl border border-zinc-200 bg-white p-4">
                <h2 className="font-semibold">Pending notes</h2>
                <p className="mb-3 text-sm text-zinc-500">Notes sent to {member.name} show here and on their production board until they are done.</p>
                <PendingNotes notes={notes} empty="No pending notes." />
                {canSendNote && <div className="mt-4 border-t border-zinc-100 pt-4"><SendNote recipientId={member.id} /></div>}
            </section>
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
