import { Head, router } from '@inertiajs/react';
import { PageHeader } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';

interface Note { id: number; title: string; body: string; read_at: string | null; created_at: string }

export default function Notifications({ notifications }: { notifications: { data: Note[] } }) {
    return (
        <AppLayout>
            <Head title="Notifications" />
            <PageHeader title="Notifications" description="In-app alerts for assignments, reviews, deadlines, and mentions." actions={<button className="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm" onClick={() => router.post('/notifications/read-all')}>Mark all read</button>} />
            <div className="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white">
                {notifications.data.length === 0 && <p className="p-6 text-sm text-zinc-500">You are caught up.</p>}
                {notifications.data.map((note) => (
                    <button key={note.id} className={`block w-full px-4 py-3 text-left ${note.read_at ? 'bg-white' : 'bg-teal-50'}`} onClick={() => router.post(`/notifications/${note.id}/read`)}>
                        <p className="font-medium">{note.title}</p>
                        <p className="text-sm text-zinc-600">{note.body}</p>
                        <p className="text-xs text-zinc-500">{formatDate(note.created_at)}</p>
                    </button>
                ))}
            </div>
        </AppLayout>
    );
}
