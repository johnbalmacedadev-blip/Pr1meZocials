import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Button, Field, Input, PageHeader, Select, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';

interface EventPayload {
    id: number;
    title: string;
    description: string | null;
    location: string | null;
    starts_on: string;
    ends_on: string | null;
    start_time: string | null;
    end_time: string | null;
    recurrence_label: string;
    has_default_poster: boolean;
    has_default_video: boolean;
    brand?: { name: string } | null;
    creator?: { name: string } | null;
}

interface OccurrencePayload {
    id: number;
    occurs_on: string;
    start_time: string | null;
    end_time: string | null;
    title: string;
    title_override: string | null;
    notes: string | null;
    status: string;
    poster_name: string | null;
    video_name: string | null;
    has_poster: boolean;
    has_video: boolean;
    has_custom_poster: boolean;
    has_custom_video: boolean;
    using_default_poster: boolean;
    using_default_video: boolean;
    poster_url: string | null;
    video_url: string | null;
}

export default function EventShow({ event, occurrences, canManage }: { event: EventPayload; occurrences: OccurrencePayload[]; canManage: boolean }) {
    const [openId, setOpenId] = useState<number | null>(occurrences.find((row) => row.occurs_on >= new Date().toISOString().slice(0, 10))?.id ?? occurrences[0]?.id ?? null);

    return (
        <AppLayout>
            <Head title={event.title} />
            <PageHeader
                title={event.title}
                description={`${event.brand?.name ?? 'Brand'} · ${event.recurrence_label}`}
                actions={(
                    <div className="flex flex-wrap gap-2">
                        <Link href="/events" className="inline-flex h-10 items-center rounded-lg border border-zinc-300 bg-white px-3 text-sm">Back</Link>
                        {canManage && <Link href={`/events/${event.id}/edit`} className="inline-flex h-10 items-center rounded-lg bg-brand-700 px-3 text-sm font-medium text-white">Edit series</Link>}
                        {canManage && (
                            <Button
                                variant="danger"
                                type="button"
                                onClick={() => {
                                    if (confirm('Remove this event and all of its scheduled dates?')) {
                                        router.delete(`/events/${event.id}`);
                                    }
                                }}
                            >
                                Delete
                            </Button>
                        )}
                    </div>
                )}
            />

            <div className="mb-6 grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 text-sm text-zinc-700 md:grid-cols-2">
                <p><span className="font-medium text-zinc-900">When:</span> {event.starts_on}{event.ends_on ? ` → ${event.ends_on}` : ''}{event.start_time ? ` · ${event.start_time}` : ''}{event.end_time ? `–${event.end_time}` : ''}</p>
                <p><span className="font-medium text-zinc-900">Location:</span> {event.location || '—'}</p>
                <p className="md:col-span-2"><span className="font-medium text-zinc-900">About:</span> {event.description || '—'}</p>
                <p className="md:col-span-2 text-zinc-500">
                    Defaults: {event.has_default_poster ? 'poster' : 'no poster'}
                    {' · '}
                    {event.has_default_video ? 'video invitation' : 'no video'}
                    . Each date below can override with its own files.
                </p>
            </div>

            <h2 className="mb-3 text-sm font-semibold text-zinc-900">Scheduled dates</h2>
            <div className="space-y-3">
                {occurrences.length === 0 && <p className="rounded-xl border border-zinc-200 bg-white p-6 text-sm text-zinc-500">No dates generated yet.</p>}
                {occurrences.map((occurrence) => (
                    <div key={occurrence.id} className="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                        <button
                            type="button"
                            className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-zinc-50"
                            onClick={() => setOpenId(openId === occurrence.id ? null : occurrence.id)}
                        >
                            <div>
                                <p className="font-medium text-zinc-950">{formatDate(occurrence.occurs_on)}{occurrence.start_time ? ` · ${occurrence.start_time}` : ''}</p>
                                <p className="text-sm text-zinc-500">
                                    {occurrence.status}
                                    {occurrence.has_custom_poster || occurrence.has_custom_video ? ' · custom media' : occurrence.has_poster || occurrence.has_video ? ' · using defaults' : ' · no media yet'}
                                </p>
                            </div>
                            <span className="text-xs text-brand-700">{openId === occurrence.id ? 'Hide' : 'Update media'}</span>
                        </button>
                        {openId === occurrence.id && (
                            <OccurrenceEditor occurrence={occurrence} canManage={canManage} />
                        )}
                    </div>
                ))}
            </div>
        </AppLayout>
    );
}

function OccurrenceEditor({ occurrence, canManage }: { occurrence: OccurrencePayload; canManage: boolean }) {
    const form = useForm({
        title_override: occurrence.title_override ?? '',
        notes: occurrence.notes ?? '',
        start_time: occurrence.start_time ?? '',
        end_time: occurrence.end_time ?? '',
        status: occurrence.status,
        poster: null as File | null,
        video: null as File | null,
        remove_poster: false,
        remove_video: false,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(`/event-occurrences/${occurrence.id}`, { forceFormData: true, preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="grid gap-4 border-t border-zinc-100 bg-zinc-50/60 p-4 md:grid-cols-2">
            <Field label="Title override" hint="Leave blank to keep the series title.">
                <Input value={form.data.title_override} onChange={(e) => form.setData('title_override', e.target.value)} disabled={!canManage} />
            </Field>
            <Field label="Status">
                <Select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} disabled={!canManage}>
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </Select>
            </Field>
            <Field label="Start time">
                <Input type="time" value={form.data.start_time} onChange={(e) => form.setData('start_time', e.target.value)} disabled={!canManage} />
            </Field>
            <Field label="End time">
                <Input type="time" value={form.data.end_time} onChange={(e) => form.setData('end_time', e.target.value)} disabled={!canManage} />
            </Field>
            <div className="md:col-span-2">
                <Field label="Notes">
                    <Textarea value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} disabled={!canManage} />
                </Field>
            </div>

            <div className="space-y-2">
                <Field label="Poster for this date" hint={occurrence.has_custom_poster ? `Current: ${occurrence.poster_name}` : occurrence.using_default_poster ? 'Using series default until you upload one here.' : 'No poster yet.'} error={form.errors.poster}>
                    <Input type="file" accept="image/*" onChange={(e) => form.setData('poster', e.target.files?.[0] ?? null)} disabled={!canManage} />
                </Field>
                {occurrence.poster_url && (
                    <a href={occurrence.poster_url} target="_blank" rel="noreferrer" className="text-sm font-medium text-brand-700">View poster</a>
                )}
                {canManage && occurrence.has_custom_poster && (
                    <label className="flex items-center gap-2 text-sm text-zinc-600">
                        <input type="checkbox" checked={form.data.remove_poster} onChange={(e) => form.setData('remove_poster', e.target.checked)} />
                        Remove this date’s poster
                    </label>
                )}
            </div>

            <div className="space-y-2">
                <Field label="Video invitation for this date" hint={occurrence.has_custom_video ? `Current: ${occurrence.video_name}` : occurrence.using_default_video ? 'Using series default until you upload one here.' : 'No video yet.'} error={form.errors.video}>
                    <Input type="file" accept="video/mp4,video/quicktime,video/webm" onChange={(e) => form.setData('video', e.target.files?.[0] ?? null)} disabled={!canManage} />
                </Field>
                {occurrence.video_url && (
                    <a href={occurrence.video_url} target="_blank" rel="noreferrer" className="text-sm font-medium text-brand-700">View video</a>
                )}
                {canManage && occurrence.has_custom_video && (
                    <label className="flex items-center gap-2 text-sm text-zinc-600">
                        <input type="checkbox" checked={form.data.remove_video} onChange={(e) => form.setData('remove_video', e.target.checked)} />
                        Remove this date’s video
                    </label>
                )}
            </div>

            {canManage && (
                <div className="md:col-span-2">
                    <Button type="submit" disabled={form.processing}>Save this date</Button>
                </div>
            )}
        </form>
    );
}
