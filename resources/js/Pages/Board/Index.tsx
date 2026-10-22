import { DndContext, DragEndEvent, PointerSensor, useDraggable, useDroppable, useSensor, useSensors } from '@dnd-kit/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Button, Field, Modal, PageHeader, PriorityBadge, Textarea } from '@/Components/ui';
import { PendingNotes, SendNote, type TaskNoteItem } from '@/Components/TaskNotes';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, visitFilters } from '@/lib/utils';
import { tone, type ContentSummary, type SharedProps } from '@/types';

export default function Board({ cards, filters, truncated, notes: pendingNotes, recipients }: { cards: ContentSummary[]; filters: Record<string, string>; selected: unknown; truncated: boolean; notes: TaskNoteItem[]; recipients: { id: number; name: string }[] }) {
    const lookups = usePage<SharedProps>().props.lookups;
    const permissions = usePage<SharedProps>().props.auth.user?.permissions ?? [];
    const [error, setError] = useState('');
    const [pending, setPending] = useState<{ id: number; status: string } | null>(null);
    const [notes, setNotes] = useState('');
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));
    const statuses = lookups?.statuses ?? [];
    const noteQuery = (filters.q ?? '').trim().toLowerCase();
    const noteCards = pendingNotes.filter((note) => noteQuery === '' || note.body.toLowerCase().includes(noteQuery) || (note.sender?.name ?? '').toLowerCase().includes(noteQuery));
    const [openNote, setOpenNote] = useState<TaskNoteItem | null>(null);

    function move(id: number, status: string, revisionNotes?: string) {
        router.post(`/content/${id}/transition`, { status, notes: revisionNotes }, {
            preserveScroll: true,
            onError: (errors) => setError(errors.status || errors.notes || 'That move is not allowed.'),
            onSuccess: () => setError(''),
        });
    }

    function onDragEnd(event: DragEndEvent) {
        const id = Number(String(event.active.id).replace('card-', ''));
        const status = String(event.over?.id ?? '');
        const card = cards.find((item) => item.id === id);
        if (!status || !card || card.status?.slug === status) return;
        if (status === 'revision_required') {
            setPending({ id, status });
            return;
        }
        move(id, status);
    }

    return (
        <AppLayout>
            <Head title="Production board" />
            <PageHeader title="Production board" description="Drag a card into New Idea, Ready for Production, In Progress, For Review, Approved, Published, or Archived." />
            {error && <p className="mb-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-900">{error}</p>}
            {truncated && <p className="mb-3 text-sm text-zinc-500">Showing the 200 most recently updated items. Filter to narrow the board.</p>}
            <form className="mb-4 flex flex-wrap gap-2" onSubmit={(event: FormEvent<HTMLFormElement>) => { event.preventDefault(); visitFilters('/board', Object.fromEntries(new FormData(event.currentTarget).entries()) as Record<string, string>); }}>
                <input name="q" defaultValue={filters.q ?? ''} placeholder="Search" className="rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
                <select name="platform_id" defaultValue={filters.platform_id ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm"><option value="">All platforms</option>{lookups?.platforms.map((platform) => <option key={platform.id} value={platform.id}>{platform.name}</option>)}</select>
                <select name="format" defaultValue={filters.format ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm"><option value="">All formats</option>{lookups?.formats.map((format) => <option key={format.value} value={format.value}>{format.label}</option>)}</select>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="overdue" value="1" defaultChecked={filters.overdue === '1'} /> Overdue</label>
                <button className="rounded-lg bg-zinc-900 px-3 text-sm text-white" type="submit">Filter</button>
            </form>
            <section className="mb-4 rounded-xl border border-zinc-200 bg-white p-4">
                <h2 className="font-semibold">Pending notes</h2>
                <p className="mb-3 text-sm text-zinc-500">Pending notes also sit in the New Idea column until you mark them done.</p>
                <PendingNotes notes={pendingNotes} empty="No pending notes." />
                <div className="mt-4 border-t border-zinc-100 pt-4">
                    <SendNote recipients={recipients} />
                </div>
            </section>
            <DndContext sensors={sensors} onDragEnd={onDragEnd}>
                <div className="flex gap-3 overflow-x-auto pb-4">
                    {statuses.map((status) => (
                        <Column key={status.slug} slug={status.slug} name={status.name} color={status.color} cards={cards.filter((card) => card.status?.slug === status.slug)} notes={status.slug === 'inbox' ? noteCards : []} canDrag={permissions.includes('content.transition')} onOpenNote={setOpenNote} />
                    ))}
                </div>
            </DndContext>
            <Modal open={openNote !== null} title="Note" onOpenChange={(open) => { if (!open) setOpenNote(null); }}>
                {openNote && (
                    <>
                        <p className="text-sm text-zinc-500">From {openNote.sender?.name ?? 'Someone'} · {formatDate(openNote.created_at)}</p>
                        <p className="mt-3 whitespace-pre-wrap text-sm text-zinc-900">{openNote.body}</p>
                        <div className="mt-5 flex justify-end gap-2">
                            <Button type="button" variant="secondary" onClick={() => setOpenNote(null)}>Close</Button>
                            {openNote.can_complete && (
                                <Button type="button" onClick={() => { router.post(`/task-notes/${openNote.id}/complete`, {}, { preserveScroll: true }); setOpenNote(null); }}>Done</Button>
                            )}
                        </div>
                    </>
                )}
            </Modal>
            <Modal open={pending !== null} title="What needs to change?" onOpenChange={(open) => { if (!open) setPending(null); }}>
                <Field label="Revision note" hint="The owner will see this explanation.">
                    <Textarea value={notes} onChange={(event) => setNotes(event.target.value)} />
                </Field>
                <div className="mt-4 flex justify-end gap-2">
                    <Button variant="secondary" onClick={() => setPending(null)}>Cancel</Button>
                    <Button onClick={() => { if (pending && notes.trim()) { move(pending.id, pending.status, notes.trim()); setPending(null); setNotes(''); } }}>Request revisions</Button>
                </div>
            </Modal>
        </AppLayout>
    );
}

function Column({ slug, name, color, cards, notes = [], canDrag, onOpenNote }: { slug: string; name: string; color: string; cards: ContentSummary[]; notes?: TaskNoteItem[]; canDrag: boolean; onOpenNote: (note: TaskNoteItem) => void }) {
    const { setNodeRef, isOver } = useDroppable({ id: slug });
    return (
        <section ref={setNodeRef} className={`w-72 shrink-0 rounded-xl bg-zinc-100 p-2 ${isOver ? 'ring-2 ring-brand-700' : ''}`}>
            <header className="mb-2 flex items-center justify-between px-1">
                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${tone[color] ?? tone.zinc}`}>{name}</span>
                <span className="text-xs text-zinc-500">{cards.length + notes.length}</span>
            </header>
            <div className="space-y-2">
                {notes.map((note) => <NoteCard key={`note-${note.id}`} note={note} onOpen={onOpenNote} />)}
                {cards.map((card) => <BoardCard key={card.id} card={card} disabled={!canDrag} />)}
            </div>
        </section>
    );
}

function NoteCard({ note, onOpen }: { note: TaskNoteItem; onOpen: (note: TaskNoteItem) => void }) {
    return (
        <article className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm shadow-sm">
            <button type="button" className="w-full text-left" onClick={() => onOpen(note)}>
                <p className="line-clamp-3 font-medium text-zinc-900">{note.body}</p>
                <p className="mt-2 text-xs text-zinc-600">Note · From {note.sender?.name ?? 'Someone'} · {formatDate(note.created_at)}</p>
            </button>
            {note.can_complete && (
                <Button type="button" variant="secondary" size="sm" className="mt-2" onClick={() => router.post(`/task-notes/${note.id}/complete`, {}, { preserveScroll: true })}>Done</Button>
            )}
        </article>
    );
}

function BoardCard({ card, disabled }: { card: ContentSummary; disabled: boolean }) {
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({ id: `card-${card.id}`, disabled });
    const style = transform ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)` } : undefined;
    return (
        <article ref={setNodeRef} style={style} {...listeners} {...attributes} className={`rounded-lg border border-zinc-200 bg-white p-3 text-sm shadow-sm ${isDragging ? 'opacity-70' : ''} ${card.is_overdue ? 'ring-1 ring-rose-400' : ''}`}>
            <Link href={`/content/${card.id}`} className="font-medium hover:underline" onPointerDown={(event) => event.stopPropagation()}>{card.title}</Link>
            <p className="mt-1 text-xs text-zinc-500">{card.reference_number} · {card.brand?.name}</p>
            <div className="mt-2 flex flex-wrap items-center gap-1">
                <PriorityBadge priority={card.priority} />
                <span className="text-xs text-zinc-500">{card.format_label}</span>
            </div>
            <p className="mt-2 text-xs text-zinc-600">{card.owner?.name ?? 'No owner'} · {formatDate(card.deadline)}</p>
        </article>
    );
}
