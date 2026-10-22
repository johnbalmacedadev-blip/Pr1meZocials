import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Button, Field, Modal, Select, Textarea } from '@/Components/ui';
import { formatDate } from '@/lib/utils';

export interface TaskNoteItem {
    id: number;
    body: string;
    created_at: string | null;
    sender: { id: number; name: string } | null;
    can_complete: boolean;
}

export function SendNote({ recipientId, recipients }: { recipientId?: number; recipients?: { id: number; name: string }[] }) {
    const form = useForm({ recipient_id: recipientId ? String(recipientId) : '', body: '' });

    function send(event: FormEvent) {
        event.preventDefault();
        form.post('/task-notes', { preserveScroll: true, onSuccess: () => form.reset('body') });
    }

    return (
        <form onSubmit={send} className={`grid gap-3 md:items-start ${recipientId ? 'md:grid-cols-[minmax(0,1fr)_auto]' : 'md:grid-cols-[16rem_minmax(0,1fr)_auto]'}`}>
            {recipientId ? null : (
                <Field label="Send to" error={form.errors.recipient_id}>
                    <Select className="h-10" value={form.data.recipient_id} onChange={(event) => form.setData('recipient_id', event.target.value)} required>
                        <option value="">Choose a person</option>
                        {recipients?.map((person) => <option key={person.id} value={person.id}>{person.name}</option>)}
                    </Select>
                </Field>
            )}
            <Field label="Note" error={form.errors.body}>
                <Textarea value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} placeholder="What should they do?" required className="min-h-10" />
            </Field>
            <Button type="submit" className="h-10 md:mt-6" disabled={form.processing}>{form.processing ? 'Sending…' : 'Send note'}</Button>
        </form>
    );
}

function completeNote(note: TaskNoteItem) {
    router.post(`/task-notes/${note.id}/complete`, {}, { preserveScroll: true });
}

export function PendingNotes({ notes, empty }: { notes: TaskNoteItem[]; empty: string }) {
    const [open, setOpen] = useState<TaskNoteItem | null>(null);

    if (notes.length === 0) {
        return <p className="text-sm text-zinc-500">{empty}</p>;
    }

    return (
        <>
            <div className="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white">
                {notes.map((note) => (
                    <article key={note.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <button type="button" className="min-w-0 flex-1 rounded-lg px-1 py-1 text-left hover:bg-zinc-50" onClick={() => setOpen(note)}>
                            <p className="truncate text-sm text-zinc-900">{note.body}</p>
                            <p className="mt-1 text-xs text-zinc-500">From {note.sender?.name ?? 'Someone'} · {formatDate(note.created_at)}</p>
                        </button>
                        {note.can_complete && (
                            <Button type="button" variant="secondary" size="sm" onClick={() => completeNote(note)}>Done</Button>
                        )}
                    </article>
                ))}
            </div>
            <Modal open={open !== null} title="Note" onOpenChange={(next) => { if (!next) setOpen(null); }}>
                {open && (
                    <>
                        <p className="text-sm text-zinc-500">From {open.sender?.name ?? 'Someone'} · {formatDate(open.created_at)}</p>
                        <p className="mt-3 whitespace-pre-wrap text-sm text-zinc-900">{open.body}</p>
                        <div className="mt-5 flex justify-end gap-2">
                            <Button type="button" variant="secondary" onClick={() => setOpen(null)}>Close</Button>
                            {open.can_complete && (
                                <Button type="button" onClick={() => { completeNote(open); setOpen(null); }}>Done</Button>
                            )}
                        </div>
                    </>
                )}
            </Modal>
        </>
    );
}
