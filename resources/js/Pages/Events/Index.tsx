import { Head, Link, router } from '@inertiajs/react';
import { addMonths, eachDayOfInterval, endOfMonth, endOfWeek, format, isSameMonth, parseISO, startOfMonth, startOfWeek } from 'date-fns';
import { Plus } from 'lucide-react';
import { EmptyState, PageHeader, Select } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import type { FormOptions } from '@/types';

interface OccurrenceChip {
    id: number;
    event_id: number;
    occurs_on: string;
    title: string;
    start_time: string | null;
    status: string;
    has_poster: boolean;
    has_video: boolean;
    brand?: { name: string } | null;
}

interface SeriesRow {
    id: number;
    title: string;
    recurrence_label: string;
    starts_on: string;
    ends_on: string | null;
    upcoming_count: number;
    status: string;
    brand?: { name: string } | null;
}

export default function EventsIndex({
    date,
    occurrences,
    series,
    filters,
    options,
    canCreate,
}: {
    date: string;
    occurrences: OccurrenceChip[];
    series: SeriesRow[];
    filters: { brand_id?: string };
    options: FormOptions;
    canCreate: boolean;
    canManage: boolean;
}) {
    const anchor = parseISO(date);
    const days = eachDayOfInterval({
        start: startOfWeek(startOfMonth(anchor), { weekStartsOn: 1 }),
        end: endOfWeek(endOfMonth(anchor), { weekStartsOn: 1 }),
    });

    function shift(months: number) {
        router.get('/events', { date: format(addMonths(anchor, months), 'yyyy-MM-dd'), brand_id: filters.brand_id || undefined }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout>
            <Head title="Events calendar" />
            <PageHeader
                title="Events calendar"
                description="Repeating events (every Friday, Saturday, and so on). Set a different poster or video invitation on each date."
                actions={canCreate ? <Link href="/events/create" className="inline-flex h-10 items-center rounded-lg bg-brand-700 px-3 text-sm font-medium text-white">New event</Link> : undefined}
            />

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <button type="button" className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm" onClick={() => shift(-1)}>Previous</button>
                <button type="button" className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm" onClick={() => router.get('/events', { brand_id: filters.brand_id || undefined }, { preserveState: true })}>Today</button>
                <button type="button" className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm" onClick={() => shift(1)}>Next</button>
                <strong className="px-2 text-sm">{format(anchor, 'MMMM yyyy')}</strong>
                {options.brands.length > 1 && (
                    <Select
                        className="w-auto"
                        value={filters.brand_id ?? ''}
                        onChange={(event) => router.get('/events', { date, brand_id: event.target.value || undefined }, { preserveState: true, replace: true })}
                    >
                        <option value="">All brands</option>
                        {options.brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}
                    </Select>
                )}
            </div>

            <div className="mb-1 grid grid-cols-7 text-center text-xs font-medium text-zinc-500">
                {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((day) => <div key={day}>{day}</div>)}
            </div>
            <div className="mb-8 grid grid-cols-1 gap-2 sm:grid-cols-7">
                {days.map((day) => {
                    const key = format(day, 'yyyy-MM-dd');
                    const dayItems = occurrences.filter((item) => item.occurs_on === key);
                    return (
                        <div key={key} className={`group relative min-h-28 rounded-xl border p-2 ${isSameMonth(day, anchor) ? 'border-zinc-200 bg-white' : 'border-zinc-100 bg-zinc-50'}`}>
                            <div className="flex items-start justify-between gap-1">
                                <p className="text-xs font-medium text-zinc-500">{format(day, 'd')}</p>
                                {canCreate && (
                                    <Link
                                        href={`/events/create?date=${key}${filters.brand_id ? `&brand_id=${filters.brand_id}` : ''}`}
                                        className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-700 text-white opacity-100 shadow-sm transition hover:bg-brand-800 sm:opacity-0 sm:group-hover:opacity-100"
                                        aria-label={`Add event on ${key}`}
                                        title="Add event"
                                    >
                                        <Plus className="h-3.5 w-3.5" strokeWidth={2.5} />
                                    </Link>
                                )}
                            </div>
                            <div className="mt-1 space-y-1">
                                {dayItems.map((item) => (
                                    <Link
                                        key={item.id}
                                        href={`/events/${item.event_id}`}
                                        className={`block rounded-md px-1.5 py-1 text-left text-[11px] leading-tight ${item.status === 'cancelled' ? 'bg-zinc-100 text-zinc-500 line-through' : 'bg-brand-50 text-brand-900 hover:bg-brand-100'}`}
                                    >
                                        <span className="font-medium">{item.title}</span>
                                        {item.start_time && <span className="block text-[10px] opacity-80">{item.start_time}</span>}
                                        {(item.has_poster || item.has_video) && (
                                            <span className="mt-0.5 block text-[10px] text-brand-700">
                                                {item.has_poster ? 'Poster' : ''}{item.has_poster && item.has_video ? ' · ' : ''}{item.has_video ? 'Video' : ''}
                                            </span>
                                        )}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    );
                })}
            </div>

            <h2 className="mb-3 text-sm font-semibold text-zinc-900">Event series</h2>
            {series.length === 0 ? (
                <EmptyState title="No events yet" body="Create an event and choose repeating weekdays when it runs every week." action={canCreate ? <Link href="/events/create" className="text-sm font-medium text-brand-700">Create an event</Link> : undefined} />
            ) : (
                <div className="grid gap-3 md:grid-cols-2">
                    {series.map((event) => (
                        <Link key={event.id} href={`/events/${event.id}`} className="rounded-xl border border-zinc-200 bg-white p-4 hover:border-zinc-400">
                            <div className="flex items-start justify-between gap-3">
                                <h3 className="font-semibold text-zinc-950">{event.title}</h3>
                                <span className="shrink-0 text-xs uppercase text-zinc-500">{event.status}</span>
                            </div>
                            <p className="mt-1 text-sm text-zinc-500">{event.brand?.name} · {event.recurrence_label}</p>
                            <p className="mt-2 text-sm text-zinc-600">{event.upcoming_count} upcoming · from {event.starts_on}{event.ends_on ? ` to ${event.ends_on}` : ''}</p>
                        </Link>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
