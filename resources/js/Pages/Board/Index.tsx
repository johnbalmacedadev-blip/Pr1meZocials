import { DndContext, DragEndEvent, PointerSensor, useDraggable, useDroppable, useSensor, useSensors } from '@dnd-kit/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Button, Field, Modal, PageHeader, PriorityBadge, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, visitFilters } from '@/lib/utils';
import { tone, type ContentSummary, type SharedProps } from '@/types';

export default function Board({ cards, filters, truncated }: { cards: ContentSummary[]; filters: Record<string, string>; selected: unknown; truncated: boolean }) {
    const lookups = usePage<SharedProps>().props.lookups;
    const permissions = usePage<SharedProps>().props.auth.user?.permissions ?? [];
    const [error, setError] = useState('');
    const [pending, setPending] = useState<{ id: number; status: string } | null>(null);
    const [notes, setNotes] = useState('');
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));
    const statuses = lookups?.statuses ?? [];

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
            <PageHeader title="Production board" description="Drag a card only into a stage the workflow and your role allow. Approval and publishing still require their own permissions." />
            {error && <p className="mb-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-900">{error}</p>}
            {truncated && <p className="mb-3 text-sm text-zinc-500">Showing the 200 most recently updated items. Filter to narrow the board.</p>}
            <form className="mb-4 flex flex-wrap gap-2" onSubmit={(event: FormEvent<HTMLFormElement>) => { event.preventDefault(); visitFilters('/board', Object.fromEntries(new FormData(event.currentTarget).entries()) as Record<string, string>); }}>
                <input name="q" defaultValue={filters.q ?? ''} placeholder="Search" className="rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
                <select name="platform_id" defaultValue={filters.platform_id ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm"><option value="">All platforms</option>{lookups?.platforms.map((platform) => <option key={platform.id} value={platform.id}>{platform.name}</option>)}</select>
                <select name="format" defaultValue={filters.format ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm"><option value="">All formats</option>{lookups?.formats.map((format) => <option key={format.value} value={format.value}>{format.label}</option>)}</select>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="overdue" value="1" defaultChecked={filters.overdue === '1'} /> Overdue</label>
                <button className="rounded-lg bg-zinc-900 px-3 text-sm text-white" type="submit">Filter</button>
            </form>
            <DndContext sensors={sensors} onDragEnd={onDragEnd}>
                <div className="flex gap-3 overflow-x-auto pb-4">
                    {statuses.map((status) => (
                        <Column key={status.slug} slug={status.slug} name={status.name} color={status.color} cards={cards.filter((card) => card.status?.slug === status.slug)} canDrag={permissions.includes('content.transition')} />
                    ))}
                </div>
            </DndContext>
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

function Column({ slug, name, color, cards, canDrag }: { slug: string; name: string; color: string; cards: ContentSummary[]; canDrag: boolean }) {
    const { setNodeRef, isOver } = useDroppable({ id: slug });
    return (
        <section ref={setNodeRef} className={`w-72 shrink-0 rounded-xl bg-zinc-100 p-2 ${isOver ? 'ring-2 ring-teal-700' : ''}`}>
            <header className="mb-2 flex items-center justify-between px-1">
                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${tone[color] ?? tone.zinc}`}>{name}</span>
                <span className="text-xs text-zinc-500">{cards.length}</span>
            </header>
            <div className="space-y-2">
                {cards.map((card) => <BoardCard key={card.id} card={card} disabled={!canDrag} />)}
            </div>
        </section>
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
