import { Head, Link, useForm } from '@inertiajs/react';
import { Check, ExternalLink, ImagePlus, Images, Loader2, Search, X } from 'lucide-react';
import { ChangeEvent, FormEvent, useEffect, useMemo, useState } from 'react';
import { Button, Card, Field, Input, PageHeader, PriorityBadge, Select, StatusBadge, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatDate } from '@/lib/utils';

interface Option {
    value: string;
    label: string;
}

interface Person {
    id: number;
    name: string;
    job_title?: string | null;
}

interface TicketRow {
    id: number;
    reference_number: string;
    title: string;
    priority: string | null;
    deadline: string | null;
    created_at: string | null;
    brand: string | null;
    status: { name: string; slug: string; color: string } | null;
    team: { id: number; name: string }[];
    images: number;
    drive_url: string | null;
}

interface Props {
    brands: { id: number; name: string }[];
    people: Person[];
    formats: Option[];
    priorities: Option[];
    maxImages: number;
    tickets: TicketRow[];
}

interface TicketForm {
    title: string;
    details: string;
    brand_id: string;
    format: string;
    priority: string;
    deadline: string;
    drive_url: string;
    images: File[];
    member_ids: number[];
}

// Phone photos are often 5–15 MB; the live server caps a request at a few MB,
// so large images are resized in the browser before upload.
const SHRINK_ABOVE_BYTES = 1.5 * 1024 * 1024;
const MAX_EDGE = 2000;

async function shrinkImage(file: File): Promise<File> {
    if (file.size <= SHRINK_ABOVE_BYTES || file.type === 'image/gif') return file;
    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d')?.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();
        const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
        if (!blob || blob.size >= file.size) return file;
        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
    } catch {
        return file;
    }
}

export default function Tickets({ brands, people, formats, priorities, maxImages, tickets }: Props) {
    const form = useForm<TicketForm>({
        title: '',
        details: '',
        brand_id: brands[0] ? String(brands[0].id) : '',
        format: 'other',
        priority: 'medium',
        deadline: '',
        drive_url: '',
        images: [],
        member_ids: [],
    });
    const [preparing, setPreparing] = useState(false);
    const [query, setQuery] = useState('');
    const previews = useMemo(() => form.data.images.map((file) => URL.createObjectURL(file)), [form.data.images]);
    useEffect(() => () => previews.forEach((url) => URL.revokeObjectURL(url)), [previews]);

    const errors = form.errors as Record<string, string | undefined>;
    const imageError = errors.images ?? Object.entries(errors).find(([key]) => key.startsWith('images.'))?.[1];
    const memberError = errors.member_ids ?? Object.entries(errors).find(([key]) => key.startsWith('member_ids.'))?.[1];
    const filtered = people.filter((person) => `${person.name} ${person.job_title ?? ''}`.toLowerCase().includes(query.trim().toLowerCase()));
    const selected = form.data.member_ids.map((id) => people.find((person) => person.id === id)).filter((person): person is Person => Boolean(person));

    async function addImages(event: ChangeEvent<HTMLInputElement>) {
        const files = Array.from(event.target.files ?? []).filter((file) => file.type.startsWith('image/'));
        event.target.value = '';
        if (files.length === 0) return;
        setPreparing(true);
        const ready = await Promise.all(files.slice(0, Math.max(0, maxImages - form.data.images.length)).map(shrinkImage));
        form.setData('images', [...form.data.images, ...ready]);
        setPreparing(false);
    }

    function toggleMember(id: number) {
        form.setData('member_ids', form.data.member_ids.includes(id) ? form.data.member_ids.filter((member) => member !== id) : [...form.data.member_ids, id]);
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/tickets', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setQuery('');
            },
        });
    }

    return (
        <AppLayout>
            <Head title="Ticket" />
            <PageHeader title="Ticket" description="Create a task for your team. It goes straight to Ready for Production on the production board, and everyone you add is notified." />

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <Card className="p-4 sm:p-5">
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Task" error={errors.title}>
                            <Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} placeholder="e.g. Poster for Saturday product promo" maxLength={180} required />
                        </Field>

                        <Field label="Notes / details" error={errors.details} hint="What needs to be done, sizes, copy, references…">
                            <Textarea value={form.data.details} onChange={(event) => form.setData('details', event.target.value)} rows={5} maxLength={10000} />
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {brands.length > 1 && (
                                <Field label="Brand" error={errors.brand_id}>
                                    <Select value={form.data.brand_id} onChange={(event) => form.setData('brand_id', event.target.value)}>
                                        {brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}
                                    </Select>
                                </Field>
                            )}
                            <Field label="Type" error={errors.format}>
                                <Select value={form.data.format} onChange={(event) => form.setData('format', event.target.value)}>
                                    {formats.map((format) => <option key={format.value} value={format.value}>{format.label}</option>)}
                                </Select>
                            </Field>
                            <Field label="Priority" error={errors.priority}>
                                <Select value={form.data.priority} onChange={(event) => form.setData('priority', event.target.value)}>
                                    {priorities.map((priority) => <option key={priority.value} value={priority.value}>{priority.label}</option>)}
                                </Select>
                            </Field>
                            <Field label="Deadline" error={errors.deadline}>
                                <Input type="date" value={form.data.deadline} onChange={(event) => form.setData('deadline', event.target.value)} />
                            </Field>
                        </div>

                        <Field label="Google Drive link" error={errors.drive_url} hint="Optional. A Drive file or folder with the materials.">
                            <Input type="url" inputMode="url" value={form.data.drive_url} onChange={(event) => form.setData('drive_url', event.target.value)} placeholder="https://drive.google.com/…" />
                        </Field>

                        <div className="space-y-1.5">
                            <span className="text-sm font-medium text-zinc-800">Images</span>
                            <div className="flex flex-wrap gap-2">
                                {form.data.images.map((file, index) => (
                                    <div key={`${file.name}-${index}`} className="relative h-20 w-20 overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50">
                                        <img src={previews[index]} alt={file.name} className="h-full w-full object-cover" />
                                        <button type="button" aria-label={`Remove ${file.name}`} className="absolute right-1 top-1 rounded-full bg-black/60 p-0.5 text-white" onClick={() => form.setData('images', form.data.images.filter((_, position) => position !== index))}>
                                            <X className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                ))}
                                {form.data.images.length < maxImages && (
                                    <label className="flex h-20 w-20 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-zinc-300 text-xs text-zinc-500 hover:border-brand-700 hover:text-brand-700">
                                        {preparing ? <Loader2 className="h-5 w-5 animate-spin" /> : <ImagePlus className="h-5 w-5" />}
                                        {preparing ? 'Preparing' : 'Add'}
                                        <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple className="sr-only" onChange={addImages} disabled={preparing} />
                                    </label>
                                )}
                            </div>
                            <span className="block text-xs text-zinc-500">Up to {maxImages} images. Large photos are resized before upload.</span>
                            {imageError && <span className="block text-sm text-rose-700">{imageError}</span>}
                        </div>

                        <div className="space-y-1.5">
                            <span className="text-sm font-medium text-zinc-800">Team</span>
                            {selected.length > 0 && (
                                <div className="flex flex-wrap gap-1.5">
                                    {selected.map((person, index) => (
                                        <span key={person.id} className="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-800">
                                            {person.name}{index === 0 && <span className="text-brand-600">· Lead</span>}
                                            <button type="button" aria-label={`Remove ${person.name}`} onClick={() => toggleMember(person.id)}><X className="h-3 w-3" /></button>
                                        </span>
                                    ))}
                                </div>
                            )}
                            <div className="rounded-lg border border-zinc-200">
                                <div className="flex items-center gap-2 border-b border-zinc-200 px-3">
                                    <Search className="h-4 w-4 text-zinc-400" />
                                    <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search teammates" className="w-full border-0 bg-transparent py-2 text-sm outline-none" />
                                </div>
                                <ul className="max-h-56 overflow-y-auto py-1">
                                    {filtered.map((person) => {
                                        const checked = form.data.member_ids.includes(person.id);
                                        return (
                                            <li key={person.id}>
                                                <button type="button" onClick={() => toggleMember(person.id)} className={cn('flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50', checked && 'bg-brand-50/60')}>
                                                    <span className={cn('flex h-4 w-4 shrink-0 items-center justify-center rounded border', checked ? 'border-brand-700 bg-brand-700 text-white' : 'border-zinc-300')}>
                                                        {checked && <Check className="h-3 w-3" />}
                                                    </span>
                                                    <span className="min-w-0">
                                                        <span className="block truncate font-medium text-zinc-900">{person.name}</span>
                                                        {person.job_title && <span className="block truncate text-xs text-zinc-500">{person.job_title}</span>}
                                                    </span>
                                                </button>
                                            </li>
                                        );
                                    })}
                                    {filtered.length === 0 && <li className="px-3 py-2 text-sm text-zinc-500">No teammates found.</li>}
                                </ul>
                            </div>
                            <span className="block text-xs text-zinc-500">The first person you pick leads the task. Everyone picked gets a notification.</span>
                            {memberError && <span className="block text-sm text-rose-700">{memberError}</span>}
                        </div>

                        {form.progress && <div className="h-1.5 overflow-hidden rounded-full bg-zinc-100"><div className="h-full bg-brand-700 transition-all" style={{ width: `${form.progress.percentage ?? 0}%` }} /></div>}

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing || preparing}>
                                {form.processing && <Loader2 className="h-4 w-4 animate-spin" />}
                                Create ticket
                            </Button>
                        </div>
                    </form>
                </Card>

                <Card className="p-4">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="font-semibold text-zinc-900">Recent tickets</h2>
                        <Link href="/board" className="text-sm font-medium text-brand-700 hover:underline">Production board</Link>
                    </div>
                    {tickets.length === 0 ? (
                        <p className="text-sm text-zinc-500">No tickets yet.</p>
                    ) : (
                        <ul className="divide-y divide-zinc-100">
                            {tickets.map((ticket) => (
                                <li key={ticket.id} className="py-3 first:pt-0 last:pb-0">
                                    <Link href={`/content/${ticket.id}`} className="font-medium text-zinc-900 hover:underline">{ticket.title}</Link>
                                    <p className="mt-0.5 text-xs text-zinc-500">{ticket.reference_number}{ticket.brand ? ` · ${ticket.brand}` : ''} · {formatDate(ticket.created_at)}</p>
                                    <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                        <StatusBadge status={ticket.status} />
                                        <PriorityBadge priority={ticket.priority} />
                                        {ticket.images > 0 && <span className="inline-flex items-center gap-1 text-xs text-zinc-500"><Images className="h-3.5 w-3.5" />{ticket.images}</span>}
                                        {ticket.drive_url && <a href={ticket.drive_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-xs font-medium text-brand-700 hover:underline">Drive <ExternalLink className="h-3 w-3" /></a>}
                                    </div>
                                    {ticket.team.length > 0 && <p className="mt-1.5 text-xs text-zinc-600">{ticket.team.map((person) => person.name).join(', ')}{ticket.deadline ? ` · Due ${formatDate(ticket.deadline)}` : ''}</p>}
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
