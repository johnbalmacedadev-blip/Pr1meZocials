import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Button, Field, Input, PageHeader, Select, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import type { FormOptions } from '@/types';

interface EventForm {
    id?: number;
    brand_id?: number;
    title?: string;
    description?: string | null;
    location?: string | null;
    starts_on?: string;
    ends_on?: string | null;
    start_time?: string | null;
    end_time?: string | null;
    recurrence?: string;
    recurrence_weekdays?: number[];
    default_poster_name?: string | null;
    default_video_name?: string | null;
}

const WEEKDAYS = [
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
    { value: 7, label: 'Sunday' },
];

export default function EventFormPage({ event, options }: { event: EventForm | null; options: FormOptions }) {
    const form = useForm({
        brand_id: String(event?.brand_id ?? options.brands[0]?.id ?? ''),
        title: event?.title ?? '',
        description: event?.description ?? '',
        location: event?.location ?? '',
        starts_on: event?.starts_on ?? '',
        ends_on: event?.ends_on ?? '',
        start_time: event?.start_time ?? '',
        end_time: event?.end_time ?? '',
        recurrence: event?.recurrence ?? 'none',
        recurrence_weekdays: event?.recurrence_weekdays ?? [],
        default_poster: null as File | null,
        default_video: null as File | null,
    });

    function toggleDay(day: number) {
        const next = form.data.recurrence_weekdays.includes(day)
            ? form.data.recurrence_weekdays.filter((value) => value !== day)
            : [...form.data.recurrence_weekdays, day].sort((a, b) => a - b);
        form.setData('recurrence_weekdays', next);
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        if (event?.id) {
            form.post(`/events/${event.id}`, { forceFormData: true });
        } else {
            form.post('/events', { forceFormData: true });
        }
    }

    return (
        <AppLayout>
            <Head title={event ? 'Edit event' : 'New event'} />
            <PageHeader
                title={event ? 'Edit event' : 'New event'}
                description="Define the series once. Then open each scheduled date to upload that week’s poster or video invitation."
            />
            <form onSubmit={submit} className="grid max-w-3xl gap-4 rounded-xl border border-zinc-200 bg-white p-5">
                <Field label="Brand" error={form.errors.brand_id}>
                    <Select value={form.data.brand_id} onChange={(e) => form.setData('brand_id', e.target.value)}>
                        {options.brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}
                    </Select>
                </Field>
                <Field label="Title" error={form.errors.title}>
                    <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required />
                </Field>
                <Field label="Description" error={form.errors.description}>
                    <Textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </Field>
                <Field label="Location" error={form.errors.location}>
                    <Input value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} />
                </Field>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="First date" error={form.errors.starts_on}>
                        <Input type="date" value={form.data.starts_on} onChange={(e) => form.setData('starts_on', e.target.value)} required />
                    </Field>
                    <Field label="Repeat until" hint="Optional. Defaults to six months ahead for weekly events." error={form.errors.ends_on}>
                        <Input type="date" value={form.data.ends_on ?? ''} onChange={(e) => form.setData('ends_on', e.target.value)} />
                    </Field>
                    <Field label="Start time" error={form.errors.start_time}>
                        <Input type="time" value={form.data.start_time ?? ''} onChange={(e) => form.setData('start_time', e.target.value)} />
                    </Field>
                    <Field label="End time" error={form.errors.end_time}>
                        <Input type="time" value={form.data.end_time ?? ''} onChange={(e) => form.setData('end_time', e.target.value)} />
                    </Field>
                </div>
                <Field label="Repeats" error={form.errors.recurrence}>
                    <Select
                        value={form.data.recurrence}
                        onChange={(e) => form.setData('recurrence', e.target.value)}
                    >
                        <option value="none">Does not repeat</option>
                        <option value="weekly">Weekly on selected days</option>
                    </Select>
                </Field>
                {form.data.recurrence === 'weekly' && (
                    <Field label="Weekdays" error={form.errors.recurrence_weekdays} hint="Example: Friday and Saturday for a weekly night event.">
                        <div className="flex flex-wrap gap-2">
                            {WEEKDAYS.map((day) => {
                                const active = form.data.recurrence_weekdays.includes(day.value);
                                return (
                                    <button
                                        key={day.value}
                                        type="button"
                                        onClick={() => toggleDay(day.value)}
                                        className={`rounded-full px-3 py-1.5 text-sm ${active ? 'bg-brand-700 text-white' : 'border border-zinc-300 bg-white text-zinc-700'}`}
                                    >
                                        {day.label}
                                    </button>
                                );
                            })}
                        </div>
                    </Field>
                )}
                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Default poster" hint={event?.default_poster_name ? `Current: ${event.default_poster_name}` : 'Optional fallback if a date has no poster yet.'} error={form.errors.default_poster}>
                        <Input type="file" accept="image/*" onChange={(e) => form.setData('default_poster', e.target.files?.[0] ?? null)} />
                    </Field>
                    <Field label="Default video invitation" hint={event?.default_video_name ? `Current: ${event.default_video_name}` : 'Optional fallback if a date has no video yet.'} error={form.errors.default_video}>
                        <Input type="file" accept="video/mp4,video/quicktime,video/webm" onChange={(e) => form.setData('default_video', e.target.files?.[0] ?? null)} />
                    </Field>
                </div>
                <Button type="submit" disabled={form.processing}>{event ? 'Save event' : 'Create event'}</Button>
            </form>
        </AppLayout>
    );
}
