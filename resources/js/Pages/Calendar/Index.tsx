import { DndContext, DragEndEvent, PointerSensor, useDraggable, useDroppable, useSensor, useSensors } from '@dnd-kit/core';
import { Head, Link, router } from '@inertiajs/react';
import { addDays, eachDayOfInterval, format, isSameMonth, parseISO, startOfWeek } from 'date-fns';
import { PageHeader, StatusBadge } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';

interface CalendarEvent {
    id: string;
    version_id: number | null;
    content_id: number;
    title: string;
    reference_number: string;
    date: string;
    time: string | null;
    kind: 'publish' | 'deadline';
    format_label: string;
    platform: { name: string } | null;
    status: { name: string; slug: string; color: string } | null;
    brand: string | null;
    owner: { name: string } | null;
    conflict: boolean;
    deadline: string | null;
}

export default function CalendarPage({ events, deadlines, view, focus, date, canReschedule }: {
    events: CalendarEvent[];
    deadlines: CalendarEvent[];
    view: 'month' | 'week' | 'day' | 'list';
    focus: 'publish' | 'deadline';
    date: string;
    range: { start: string; end: string };
    filters: Record<string, string>;
    canReschedule: boolean;
}) {
    const anchor = parseISO(date);
    const visible = focus === 'deadline' ? deadlines : events;
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

    function shift(days: number) {
        const next = addDays(anchor, view === 'month' ? days * 30 : view === 'week' ? days * 7 : days);
        router.get('/calendar', { date: format(next, 'yyyy-MM-dd'), view, focus }, { preserveState: true, replace: true });
    }

    function onDragEnd(event: DragEndEvent) {
        if (!canReschedule || focus !== 'publish') return;
        const versionId = Number(String(event.active.id).replace('version-', ''));
        const day = String(event.over?.id ?? '');
        const item = events.find((entry) => entry.version_id === versionId);
        if (!day || !item || item.date === day) return;
        router.post(`/versions/${versionId}/reschedule`, { scheduled_at: `${day}T${item.time ?? '09:00'}` }, { preserveScroll: true });
    }

    const days = view === 'day'
        ? [anchor]
        : view === 'week'
            ? eachDayOfInterval({ start: startOfWeek(anchor, { weekStartsOn: 1 }), end: addDays(startOfWeek(anchor, { weekStartsOn: 1 }), 6) })
            : eachDayOfInterval({
                start: startOfWeek(new Date(anchor.getFullYear(), anchor.getMonth(), 1), { weekStartsOn: 1 }),
                end: addDays(startOfWeek(new Date(anchor.getFullYear(), anchor.getMonth() + 1, 0), { weekStartsOn: 1 }), 6),
            });

    return (
        <AppLayout>
            <Head title="Content calendar" />
            <PageHeader title="Content calendar" description="Publishing dates and production deadlines are separate. Dragging a publish chip changes only the publishing date." />
            <div className="mb-4 flex flex-wrap items-center gap-2">
                <button className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm" onClick={() => shift(-1)}>Previous</button>
                <button className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm" onClick={() => router.get('/calendar', { view, focus }, { preserveState: true })}>Today</button>
                <button className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm" onClick={() => shift(1)}>Next</button>
                <strong className="px-2 text-sm">{format(anchor, view === 'day' ? 'MMMM d, yyyy' : 'MMMM yyyy')}</strong>
                {(['month', 'week', 'day', 'list'] as const).map((option) => (
                    <button key={option} className={`rounded-full px-3 py-1 text-sm capitalize ${view === option ? 'bg-zinc-900 text-white' : 'bg-white'}`} onClick={() => router.get('/calendar', { view: option, date, focus }, { preserveState: true })}>{option}</button>
                ))}
                <button className={`rounded-full px-3 py-1 text-sm ${focus === 'publish' ? 'bg-brand-700 text-white' : 'bg-white'}`} onClick={() => router.get('/calendar', { view, date, focus: 'publish' }, { preserveState: true })}>Publishing</button>
                <button className={`rounded-full px-3 py-1 text-sm ${focus === 'deadline' ? 'bg-brand-700 text-white' : 'bg-white'}`} onClick={() => router.get('/calendar', { view, date, focus: 'deadline' }, { preserveState: true })}>Production deadlines</button>
            </div>
            {events.some((event) => event.conflict) && focus === 'publish' && <p className="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950">Some days have three or more posts for the same brand and platform.</p>}
            {view === 'list' ? (
                <div className="divide-y divide-zinc-100 rounded-xl border border-zinc-200 bg-white">
                    {visible.length === 0 && <p className="p-6 text-sm text-zinc-500">Nothing is dated in this range.</p>}
                    {visible.map((event) => <EventRow key={event.id} event={event} />)}
                </div>
            ) : (
                <DndContext sensors={sensors} onDragEnd={onDragEnd}>
                    {view !== 'day' && <div className="mb-1 grid grid-cols-7 text-center text-xs font-medium text-zinc-500">{['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((day) => <div key={day}>{day}</div>)}</div>}
                    <div className={view === 'day' ? 'grid gap-3' : 'grid grid-cols-1 gap-2 sm:grid-cols-7'}>
                        {days.map((day) => <DayCell key={day.toISOString()} day={day} inMonth={isSameMonth(day, anchor)} events={visible.filter((event) => event.date === format(day, 'yyyy-MM-dd'))} draggable={canReschedule && focus === 'publish'} />)}
                    </div>
                </DndContext>
            )}
        </AppLayout>
    );
}

function DayCell({ day, inMonth, events, draggable }: { day: Date; inMonth: boolean; events: CalendarEvent[]; draggable: boolean }) {
    const { setNodeRef, isOver } = useDroppable({ id: format(day, 'yyyy-MM-dd') });
    return (
        <div ref={setNodeRef} className={`min-h-28 rounded-xl border bg-white p-2 ${inMonth ? 'border-zinc-200' : 'border-zinc-100 bg-zinc-50'} ${isOver ? 'ring-2 ring-brand-700' : ''}`}>
            <p className="text-xs font-medium text-zinc-500">{format(day, 'd')}</p>
            <div className="mt-1 space-y-1">
                {events.map((event) => <CalendarChip key={event.id} event={event} draggable={draggable && event.kind === 'publish'} />)}
            </div>
        </div>
    );
}

function CalendarChip({ event, draggable }: { event: CalendarEvent; draggable: boolean }) {
    const { attributes, listeners, setNodeRef, transform } = useDraggable({ id: `version-${event.version_id}`, disabled: !draggable });
    const style = transform ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)` } : undefined;
    return (
        <div ref={setNodeRef} style={style} {...listeners} {...attributes}>
            <EventRow event={event} />
        </div>
    );
}

function EventRow({ event }: { event: CalendarEvent }) {
    return (
        <Link href={`/content/${event.content_id}`} className={`block rounded-md px-2 py-1 text-xs ${event.conflict ? 'bg-amber-100' : event.kind === 'deadline' ? 'bg-violet-50' : 'bg-brand-50'}`}>
            <span className="font-medium">{event.title}</span>
            <span className="mt-0.5 block text-zinc-600">{event.kind === 'deadline' ? 'Production deadline' : event.platform?.name} · {event.format_label}</span>
            <StatusBadge status={event.status} />
        </Link>
    );
}
