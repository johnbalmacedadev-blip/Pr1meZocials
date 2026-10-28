import type { InertiaFormProps } from '@inertiajs/react';
import { Check, ImagePlus, Loader2, Search, X } from 'lucide-react';
import { ChangeEvent, FormEvent, ReactNode, useEffect, useMemo, useState } from 'react';
import { Field, Input, Select, Textarea } from '@/Components/ui';
import { cn } from '@/lib/utils';

export interface Option {
    value: string;
    label: string;
}

export interface Person {
    id: number;
    name: string;
    job_title?: string | null;
}

export interface TicketOptions {
    brands: { id: number; name: string }[];
    people: Person[];
    formats: Option[];
    priorities: Option[];
    maxImages: number;
}

export interface TicketFormData {
    title: string;
    details: string;
    brand_id?: string;
    format: string;
    priority: string;
    deadline: string;
    drive_url: string;
    images: File[];
    remove_image_ids?: number[];
    member_ids: number[];
}

export interface SavedImage {
    id: number;
    name: string;
    url: string;
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

/** Fields shared by "new ticket" and "edit ticket". */
export function TicketFields({ form, options, savedImages = [], onSubmit, actions }: {
    form: InertiaFormProps<TicketFormData>;
    options: TicketOptions;
    savedImages?: SavedImage[];
    onSubmit: (event: FormEvent) => void;
    actions: (preparing: boolean) => ReactNode;
}) {
    const { brands, people, formats, priorities, maxImages } = options;
    const [preparing, setPreparing] = useState(false);
    const [query, setQuery] = useState('');
    const previews = useMemo(() => form.data.images.map((file) => URL.createObjectURL(file)), [form.data.images]);
    useEffect(() => () => previews.forEach((url) => URL.revokeObjectURL(url)), [previews]);

    const removed = form.data.remove_image_ids ?? [];
    const kept = savedImages.filter((image) => !removed.includes(image.id));
    const room = maxImages - kept.length - form.data.images.length;
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
        const ready = await Promise.all(files.slice(0, Math.max(0, room)).map(shrinkImage));
        form.setData('images', [...form.data.images, ...ready]);
        setPreparing(false);
    }

    function toggleMember(id: number) {
        form.setData('member_ids', form.data.member_ids.includes(id) ? form.data.member_ids.filter((member) => member !== id) : [...form.data.member_ids, id]);
    }

    return (
        <form onSubmit={onSubmit} className="space-y-4">
            <Field label="Task" error={errors.title}>
                <Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} placeholder="e.g. Poster for Saturday product promo" maxLength={180} required />
            </Field>

            <Field label="Notes / details" error={errors.details} hint="What needs to be done, sizes, copy, references…">
                <Textarea value={form.data.details} onChange={(event) => form.setData('details', event.target.value)} rows={5} maxLength={10000} />
            </Field>

            <div className="grid gap-4 sm:grid-cols-2">
                {form.data.brand_id !== undefined && brands.length > 1 && (
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
                    {kept.map((image) => (
                        <Thumb key={`saved-${image.id}`} src={image.url} name={image.name} onRemove={() => form.setData('remove_image_ids', [...removed, image.id])} />
                    ))}
                    {form.data.images.map((file, index) => (
                        <Thumb key={`new-${file.name}-${index}`} src={previews[index]} name={file.name} badge="New" onRemove={() => form.setData('images', form.data.images.filter((_, position) => position !== index))} />
                    ))}
                    {room > 0 && (
                        <label className="flex h-24 w-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-zinc-300 text-xs text-zinc-500 hover:border-brand-700 hover:text-brand-700">
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
                <span className="block text-xs text-zinc-500">The first person you pick leads the task. Everyone newly added gets a notification.</span>
                {memberError && <span className="block text-sm text-rose-700">{memberError}</span>}
            </div>

            {form.progress && <div className="h-1.5 overflow-hidden rounded-full bg-zinc-100"><div className="h-full bg-brand-700 transition-all" style={{ width: `${form.progress.percentage ?? 0}%` }} /></div>}

            <div className="flex justify-end gap-2">{actions(preparing)}</div>
        </form>
    );
}

function Thumb({ src, name, badge, onRemove }: { src: string; name: string; badge?: string; onRemove: () => void }) {
    return (
        <div className="relative h-24 w-24 overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50">
            <img src={src} alt={name} className="h-full w-full object-cover" />
            {badge && <span className="absolute bottom-1 left-1 rounded bg-brand-700 px-1.5 py-0.5 text-[10px] font-semibold text-white">{badge}</span>}
            <button type="button" aria-label={`Remove ${name}`} className="absolute right-1 top-1 rounded-full bg-black/60 p-0.5 text-white" onClick={onRemove}>
                <X className="h-3.5 w-3.5" />
            </button>
        </div>
    );
}
