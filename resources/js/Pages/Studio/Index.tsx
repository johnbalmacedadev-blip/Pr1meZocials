import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Check, Download, ImagePlus, Loader2, Pencil, RefreshCw, Trash2, Unplug, Wand2, X } from 'lucide-react';
import { ChangeEvent, FormEvent, useRef, useState } from 'react';
import { Button, Card, EmptyState, Field, Input, PageHeader, Pagination, Select, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface ReferenceImage {
    key: string;
    id: number | null;
    name: string;
    url: string;
}

interface Generation {
    id: number;
    parent_id: number | null;
    prompt: string;
    aspect_ratio: string;
    image_size: string;
    references: string[];
    url: string;
    download_url: string;
    width: number | null;
    height: number | null;
    user: string | null;
    created_at: string | null;
}

interface Props {
    connection: { connected: boolean; hint: string | null; connected_by: string | null };
    products: ReferenceImage[];
    references: ReferenceImage[];
    generations: { data: Generation[]; prev: string | null; next: string | null; from: number | null; to: number | null; total: number };
    selected: Generation | null;
    aspectRatios: { value: string; label: string }[];
    imageSizes: { value: string; label: string; cost: string }[];
    maxReferences: number;
    canGenerate: boolean;
    canManage: boolean;
    canDelete: boolean;
}

function ConnectCard({ canManage }: { canManage: boolean }) {
    const form = useForm({ api_key: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/studio/connection', { preserveScroll: true, onSuccess: () => form.reset() });
    }

    return (
        <Card className="max-w-2xl p-5">
            <h2 className="text-base font-semibold">Connect Google to generate images</h2>
            <p className="mt-2 text-sm text-zinc-600">
                Google Flow cannot be connected to other apps. Studio connects to the Gemini API instead, Google's official route to the Nano Banana image models.
            </p>
            {canManage ? (
                <>
                    <ol className="mt-4 list-decimal space-y-1.5 pl-5 text-sm text-zinc-700">
                        <li>Open <a className="font-medium text-brand-700 hover:underline" href="https://aistudio.google.com/apikey" target="_blank" rel="noreferrer">Google AI Studio</a> and sign in with your Google account.</li>
                        <li>Turn on billing. Images are charged to that Google account, and your Flow credits are not used.</li>
                        <li>Create an API key and paste it below.</li>
                    </ol>
                    <form onSubmit={submit} className="mt-4 space-y-3">
                        <Field label="Gemini API key" error={form.errors.api_key} hint="Kept encrypted on the server. Nobody can see it here after you save it.">
                            <Input type="password" autoComplete="off" value={form.data.api_key} onChange={(event) => form.setData('api_key', event.target.value)} required />
                        </Field>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Loader2 className="h-4 w-4 animate-spin" />}
                            Connect
                        </Button>
                    </form>
                </>
            ) : (
                <p className="mt-4 text-sm font-medium text-zinc-800">Ask an administrator to connect Google.</p>
            )}
        </Card>
    );
}

function ReferenceTile({ image, selected, disabled, onToggle, onRemove }: { image: ReferenceImage; selected: boolean; disabled: boolean; onToggle: () => void; onRemove?: () => void }) {
    return (
        <div className="relative">
            <button
                type="button"
                onClick={onToggle}
                disabled={disabled && !selected}
                aria-pressed={selected}
                aria-label={`${selected ? 'Remove' : 'Use'} ${image.name}`}
                title={image.name}
                className={`block w-full overflow-hidden rounded-lg border-2 bg-white text-left disabled:opacity-40 ${selected ? 'border-brand-700' : 'border-zinc-200 hover:border-zinc-400'}`}
            >
                <img src={image.url} alt="" loading="lazy" className="aspect-square w-full object-contain" />
                <span className="block truncate border-t border-zinc-100 px-1.5 py-1 text-xs text-zinc-700">{image.name}</span>
            </button>
            {selected && <span className="pointer-events-none absolute left-1 top-1 rounded-full bg-brand-700 p-0.5 text-white"><Check className="h-3 w-3" /></span>}
            {onRemove && (
                <button type="button" onClick={onRemove} aria-label={`Delete ${image.name}`} title="Delete" className="absolute right-1 top-1 rounded-full bg-white/90 p-0.5 text-zinc-600 shadow hover:text-rose-700">
                    <X className="h-3.5 w-3.5" />
                </button>
            )}
        </div>
    );
}

function UploadButton({ kind, label }: { kind: 'product' | 'reference'; label: string }) {
    const input = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const errors = usePage<SharedProps>().props.errors as Record<string, string> | undefined;

    function upload(event: ChangeEvent<HTMLInputElement>) {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) {
            return;
        }
        router.post('/studio/references', { file, kind }, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });
    }

    return (
        <>
            <input ref={input} type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={upload} aria-label={label} />
            <Button type="button" variant="secondary" size="sm" disabled={busy} onClick={() => input.current?.click()}>
                {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <ImagePlus className="h-4 w-4" />}
                {label}
            </Button>
            {errors?.file && kind === 'reference' && <p className="basis-full text-sm text-rose-700">{errors.file}</p>}
        </>
    );
}

export default function StudioIndex({ connection, products, references, generations, selected, aspectRatios, imageSizes, maxReferences, canGenerate, canManage, canDelete }: Props) {
    const current = selected ?? generations.data[0] ?? null;
    const known = new Set([...products, ...references].map((image) => image.key));
    const promptBox = useRef<HTMLTextAreaElement>(null);
    const changeBox = useRef<HTMLTextAreaElement>(null);

    const form = useForm({
        prompt: '',
        aspect_ratio: '4:5',
        image_size: '1K',
        references: [] as string[],
        parent_id: null as number | null,
    });
    const change = useForm({ prompt: '', aspect_ratio: '', image_size: '', references: [] as string[], parent_id: null as number | null });
    const [lastAction, setLastAction] = useState<'form' | 'change' | 'again' | null>(null);
    const [againBusy, setAgainBusy] = useState(false);
    const errors = usePage<SharedProps>().props.errors as Record<string, string> | undefined;

    const busy = form.processing || change.processing || againBusy;
    const chosen = form.data.references.filter((key) => known.has(key));
    const atLimit = chosen.length >= maxReferences;
    const cost = imageSizes.find((size) => size.value === form.data.image_size)?.cost;

    function toggle(key: string) {
        form.setData('references', chosen.includes(key) ? chosen.filter((item) => item !== key) : [...chosen, key]);
    }

    function removeReference(image: ReferenceImage) {
        if (image.id && window.confirm(`Delete ${image.name}?`)) {
            form.setData('references', chosen.filter((item) => item !== image.key));
            router.delete(`/studio/references/${image.id}`, { preserveScroll: true, preserveState: true });
        }
    }

    function generate(event: FormEvent) {
        event.preventDefault();
        setLastAction('form');
        form.transform((data) => ({ ...data, references: chosen, parent_id: null }));
        form.post('/studio/generate', { preserveScroll: true, preserveState: true });
    }

    function editPrompt(item: Generation) {
        // A change only makes sense against an image, so its wording goes back in the change box.
        if (item.parent_id) {
            change.setData('prompt', item.prompt);
            changeBox.current?.focus();
            changeBox.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        form.setData({
            prompt: item.prompt,
            aspect_ratio: item.aspect_ratio,
            image_size: item.image_size,
            references: item.references.filter((key) => known.has(key)),
            parent_id: null,
        });
        promptBox.current?.focus();
        promptBox.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function regenerate(item: Generation) {
        setLastAction('again');
        router.post('/studio/generate', {
            prompt: item.prompt,
            aspect_ratio: item.aspect_ratio,
            image_size: item.image_size,
            references: item.references.filter((key) => known.has(key)),
            parent_id: item.parent_id,
        }, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setAgainBusy(true),
            onFinish: () => setAgainBusy(false),
        });
    }

    function applyChange(event: FormEvent, item: Generation) {
        event.preventDefault();
        setLastAction('change');
        change.transform((data) => ({ ...data, aspect_ratio: item.aspect_ratio, image_size: item.image_size, references: [], parent_id: item.id }));
        change.post('/studio/generate', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => change.reset(),
        });
    }

    function removeGeneration(item: Generation) {
        if (window.confirm('Delete this image? This cannot be undone.')) {
            router.delete(`/studio/images/${item.id}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout>
            <Head title="Studio" />
            <PageHeader
                title="Studio"
                description="Generate images from your product photos and references, then refine them until they are right."
                actions={connection.connected ? (
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-zinc-600">Google connected{connection.hint ? ` (key ending ${connection.hint})` : ''}</span>
                        {canManage && (
                            <Button type="button" variant="secondary" size="sm" onClick={() => { if (window.confirm('Disconnect Google? Nobody can generate images until it is connected again.')) { router.delete('/studio/connection', { preserveScroll: true }); } }}>
                                <Unplug className="h-4 w-4" /> Disconnect
                            </Button>
                        )}
                    </div>
                ) : undefined}
            />

            {!connection.connected && <div className="mb-6"><ConnectCard canManage={canManage} /></div>}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
                {canGenerate && (
                    <Card className="p-4">
                        <form onSubmit={generate} className="space-y-5">
                            <section className="space-y-2">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h2 className="text-sm font-semibold">Product images</h2>
                                    <UploadButton kind="product" label="Add product image" />
                                </div>
                                <div className="grid grid-cols-3 gap-2">
                                    {products.map((image) => (
                                        <ReferenceTile key={image.key} image={image} selected={chosen.includes(image.key)} disabled={atLimit} onToggle={() => toggle(image.key)} onRemove={image.id ? () => removeReference(image) : undefined} />
                                    ))}
                                </div>
                            </section>

                            <section className="space-y-2">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h2 className="text-sm font-semibold">Your references</h2>
                                    <UploadButton kind="reference" label="Upload reference" />
                                </div>
                                {references.length > 0 ? (
                                    <div className="grid grid-cols-3 gap-2">
                                        {references.map((image) => (
                                            <ReferenceTile key={image.key} image={image} selected={chosen.includes(image.key)} disabled={atLimit} onToggle={() => toggle(image.key)} onRemove={() => removeReference(image)} />
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-sm text-zinc-500">Upload a photo for style, pose or scene.</p>
                                )}
                                <p className="text-xs text-zinc-500">{chosen.length} of {maxReferences} selected. References are optional.</p>
                                {errors?.references && <p className="text-sm text-rose-700">{errors.references}</p>}
                            </section>

                            <Field label="Describe the image" error={form.errors.prompt}>
                                <Textarea
                                    ref={promptBox}
                                    value={form.data.prompt}
                                    onChange={(event) => form.setData('prompt', event.target.value)}
                                    maxLength={4000}
                                    rows={5}
                                    placeholder="Example: Place this bottle on a wooden table in morning sunlight, with fresh wheatgrass behind it. Keep the label exactly as it is."
                                />
                            </Field>

                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Size">
                                    <Select value={form.data.aspect_ratio} onChange={(event) => form.setData('aspect_ratio', event.target.value)}>
                                        {aspectRatios.map((ratio) => <option key={ratio.value} value={ratio.value}>{ratio.label}</option>)}
                                    </Select>
                                </Field>
                                <Field label="Quality">
                                    <Select value={form.data.image_size} onChange={(event) => form.setData('image_size', event.target.value)}>
                                        {imageSizes.map((size) => <option key={size.value} value={size.value}>{size.label}</option>)}
                                    </Select>
                                </Field>
                            </div>

                            {errors?.generate && lastAction !== 'change' && <p role="alert" className="rounded-lg bg-rose-50 p-3 text-sm text-rose-800">{errors.generate}</p>}

                            <div>
                                <Button type="submit" className="w-full" disabled={busy || !connection.connected}>
                                    {form.processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Wand2 className="h-4 w-4" />}
                                    {form.processing ? 'Generating, this can take a minute...' : 'Generate'}
                                </Button>
                                <p className="mt-1.5 text-center text-xs text-zinc-500">Google charges {cost} per image.</p>
                            </div>
                        </form>
                    </Card>
                )}

                <div className="min-w-0 space-y-6">
                    <Card className="p-4">
                        {current ? (
                            <div className="space-y-4">
                                <div className="relative overflow-hidden rounded-lg bg-zinc-100">
                                    <a href={current.url} target="_blank" rel="noreferrer">
                                        <img src={current.url} alt={current.prompt} className="mx-auto max-h-[36rem] w-auto max-w-full" />
                                    </a>
                                    {busy && (
                                        <div className="absolute inset-0 flex items-center justify-center bg-white/70">
                                            <span className="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-medium shadow"><Loader2 className="h-4 w-4 animate-spin" /> Generating...</span>
                                        </div>
                                    )}
                                </div>
                                <div>
                                    <p className="whitespace-pre-line text-sm text-zinc-800">{current.prompt}</p>
                                    <p className="mt-1 text-xs text-zinc-500">
                                        {current.aspect_ratio} · {current.image_size}{current.parent_id ? ' · Changed from an earlier image' : ''}{current.user ? ` · ${current.user}` : ''}{current.created_at ? ` · ${formatDateTime(current.created_at)}` : ''}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button asChild variant="secondary" size="sm">
                                        <a href={current.download_url}><Download className="h-4 w-4" /> Download</a>
                                    </Button>
                                    {canGenerate && (
                                        <>
                                            <Button type="button" variant="secondary" size="sm" disabled={busy || !connection.connected} onClick={() => regenerate(current)}>
                                                <RefreshCw className="h-4 w-4" /> Regenerate
                                            </Button>
                                            <Button type="button" variant="secondary" size="sm" onClick={() => editPrompt(current)}>
                                                <Pencil className="h-4 w-4" /> Edit prompt
                                            </Button>
                                        </>
                                    )}
                                    {canDelete && (
                                        <Button type="button" variant="ghost" size="sm" onClick={() => removeGeneration(current)}>
                                            <Trash2 className="h-4 w-4" /> Delete
                                        </Button>
                                    )}
                                </div>
                                {canGenerate && (
                                    <form onSubmit={(event) => applyChange(event, current)} className="space-y-2 border-t border-zinc-100 pt-4">
                                        <Field label="Change this image" error={change.errors.prompt} hint="Say only what should change. Everything else stays the same as far as possible.">
                                            <Textarea ref={changeBox} value={change.data.prompt} onChange={(event) => change.setData('prompt', event.target.value)} maxLength={4000} rows={2} placeholder="Example: Make the background lighter and move the bottle to the left." />
                                        </Field>
                                        {errors?.generate && lastAction === 'change' && <p role="alert" className="rounded-lg bg-rose-50 p-3 text-sm text-rose-800">{errors.generate}</p>}
                                        <Button type="submit" size="sm" disabled={busy || !connection.connected || change.data.prompt.trim() === ''}>
                                            {change.processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Wand2 className="h-4 w-4" />}
                                            Apply change
                                        </Button>
                                    </form>
                                )}
                            </div>
                        ) : (
                            <EmptyState title={busy ? 'Generating your image...' : 'No images yet'} body={busy ? 'This can take a minute.' : 'Images you generate will appear here.'} />
                        )}
                    </Card>

                    {generations.total > 0 && (
                        <section>
                            <h2 className="mb-2 text-sm font-semibold">All generated images</h2>
                            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 xl:grid-cols-6">
                                {generations.data.map((item) => (
                                    <Link key={item.id} href={`/studio?image=${item.id}`} preserveState preserveScroll aria-label={`Open image: ${item.prompt}`} title={item.prompt} className={`block overflow-hidden rounded-lg border-2 bg-zinc-100 ${current?.id === item.id ? 'border-brand-700' : 'border-transparent hover:border-zinc-300'}`}>
                                        <img src={item.url} alt="" loading="lazy" className="aspect-square w-full object-cover" />
                                    </Link>
                                ))}
                            </div>
                            <div className="mt-3">
                                <Pagination prev={generations.prev} next={generations.next} from={generations.from} to={generations.to} total={generations.total} />
                            </div>
                        </section>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
