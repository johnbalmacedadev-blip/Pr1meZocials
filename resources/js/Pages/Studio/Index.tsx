import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Check, Copy, Download, ExternalLink, ImagePlus, Loader2, Pencil, RefreshCw, Trash2, Upload, Wand2, X } from 'lucide-react';
import { ChangeEvent, DragEvent, FormEvent, useEffect, useRef, useState } from 'react';
import { Button, Card, EmptyState, Field, PageHeader, Pagination, Select, Textarea } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface ReferenceImage {
    key: string;
    id: number | null;
    name: string;
    url: string;
    filename: string;
}

interface Generation {
    id: number;
    parent_id: number | null;
    parent_url: string | null;
    prompt: string;
    aspect_ratio: string;
    references: string[];
    url: string;
    download_url: string;
    filename: string;
    width: number | null;
    height: number | null;
    user: string | null;
    created_at: string | null;
}

interface Props {
    flowUrl: string;
    products: ReferenceImage[];
    references: ReferenceImage[];
    generations: { data: Generation[]; prev: string | null; next: string | null; from: number | null; to: number | null; total: number };
    selected: Generation | null;
    aspectRatios: { value: string; label: string }[];
    maxReferences: number;
    canCreate: boolean;
    canDelete: boolean;
}

interface FlowImage {
    name: string;
    url: string;
    filename: string;
}

/** What is being made in Flow right now, until the finished image comes back. */
interface FlowSession {
    prompt: string;
    flowText: string;
    aspectRatio: string;
    referenceKeys: string[];
    images: FlowImage[];
    parentId: number | null;
}

const changeInstruction = (text: string) => `Edit the attached image as described here, and keep everything else the same, especially product labels and logos: ${text}`;

// Kept outside the component so moving around Studio finds the same Flow tab instead of opening another.
let flowTab: Window | null = null;

function openFlow(url: string): boolean {
    if (flowTab && !flowTab.closed) {
        // Reuse it without reloading, so whatever is open in Flow stays where it is.
        flowTab.focus();
        return true;
    }
    flowTab = window.open(url, '_blank');
    return flowTab !== null;
}

/**
 * Copies straight away, inside the click. Opening Flow takes focus from this page, and
 * browsers refuse to write to the clipboard from a page without focus.
 */
function copyText(text: string): boolean {
    const box = document.createElement('textarea');
    box.value = text;
    box.setAttribute('readonly', '');
    box.style.position = 'fixed';
    box.style.opacity = '0';
    document.body.appendChild(box);
    box.select();
    let copied = false;
    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }
    box.remove();
    if (!copied && navigator.clipboard) {
        void navigator.clipboard.writeText(text).catch(() => {});
    }
    return copied;
}

function downloadImage(image: FlowImage) {
    const link = document.createElement('a');
    link.href = image.url;
    link.download = image.filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
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
        </>
    );
}

function FlowPanel({ session, flowUrl, ratioLabel, popupBlocked, copiedAtStart, onCancel, onOpenFlow, onSaved }: { session: FlowSession; flowUrl: string; ratioLabel: string; popupBlocked: boolean; copiedAtStart: boolean; onCancel: () => void; onOpenFlow: () => void; onSaved: () => void }) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [copied, setCopied] = useState(copiedAtStart);
    const [dragging, setDragging] = useState(false);
    const [saving, setSaving] = useState(false);
    const errors = (usePage<SharedProps>().props.errors ?? {}) as Record<string, string>;
    const uploadError = errors.file ?? errors.prompt ?? errors.references;

    useEffect(() => setCopied(copiedAtStart), [session, copiedAtStart]);

    function copy() {
        setCopied(copyText(session.flowText));
    }

    function save(file: File | undefined) {
        if (!file || saving) {
            return;
        }
        router.post('/studio/images', {
            file,
            prompt: session.prompt,
            aspect_ratio: session.aspectRatio,
            references: session.referenceKeys,
            parent_id: session.parentId,
        }, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onSuccess: onSaved,
        });
    }

    useEffect(() => {
        function onPaste(event: ClipboardEvent) {
            const file = Array.from(event.clipboardData?.files ?? []).find((item) => item.type.startsWith('image/'));
            if (file) {
                event.preventDefault();
                save(file);
            }
        }
        window.addEventListener('paste', onPaste);
        return () => window.removeEventListener('paste', onPaste);
    });

    function onDrop(event: DragEvent) {
        event.preventDefault();
        setDragging(false);
        save(Array.from(event.dataTransfer.files).find((item) => item.type.startsWith('image/')));
    }

    function downloadAll() {
        session.images.forEach((image, index) => window.setTimeout(() => downloadImage(image), index * 400));
    }

    return (
        <Card className="border-2 border-brand-700 p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold">Make this image in Google Flow</h2>
                    <p className="text-sm text-zinc-600">Flow uses the Google account signed in on this browser, and your own Flow credits.</p>
                </div>
                <button type="button" onClick={onCancel} aria-label="Close" className="rounded-md p-1 text-zinc-500 hover:bg-zinc-100"><X className="h-4 w-4" /></button>
            </div>

            {popupBlocked && (
                <p role="alert" className="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    Your browser blocked the new tab. <a className="font-medium underline" href={flowUrl} target="_blank" rel="noreferrer">Open Google Flow</a>
                </p>
            )}

            <ol className="mt-4 space-y-4 text-sm">
                <li>
                    <p className="font-semibold">1. Paste the prompt in Flow</p>
                    <p className="mt-1 whitespace-pre-line rounded-lg bg-zinc-50 p-2.5 text-zinc-700">{session.flowText}</p>
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <Button type="button" variant="secondary" size="sm" onClick={copy}><Copy className="h-4 w-4" /> Copy prompt</Button>
                        <Button type="button" variant="secondary" size="sm" onClick={onOpenFlow}><ExternalLink className="h-4 w-4" /> Go to Flow</Button>
                        {copied && <span className="inline-flex items-center gap-1 text-emerald-700"><Check className="h-4 w-4" /> Copied</span>}
                        {!copied && <span className="text-amber-800">Could not copy. Select the text above instead.</span>}
                    </div>
                </li>

                {session.images.length > 0 && (
                    <li>
                        <p className="font-semibold">2. Add these images in Flow</p>
                        <p className="mt-1 text-zinc-600">Download them and add them in Flow, or drag them across if the two windows are side by side.</p>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {session.images.map((image) => (
                                <div key={image.url} className="w-20">
                                    <img src={image.url} alt={image.name} title={image.name} className="aspect-square w-full rounded-md border border-zinc-200 bg-white object-contain" />
                                    <button type="button" onClick={() => downloadImage(image)} className="mt-1 inline-flex w-full items-center justify-center gap-1 text-xs font-medium text-brand-700 hover:underline" aria-label={`Download ${image.name}`}>
                                        <Download className="h-3 w-3" /> Download
                                    </button>
                                </div>
                            ))}
                        </div>
                        {session.images.length > 1 && (
                            <Button type="button" variant="secondary" size="sm" className="mt-2" onClick={downloadAll}><Download className="h-4 w-4" /> Download all {session.images.length}</Button>
                        )}
                    </li>
                )}

                <li>
                    <p className="font-semibold">{session.images.length > 0 ? '3' : '2'}. Generate in Flow</p>
                    <p className="mt-1 text-zinc-600">Choose the closest size to <span className="font-medium text-zinc-800">{ratioLabel}</span>, then generate and download the image you like.</p>
                </li>

                <li>
                    <p className="font-semibold">{session.images.length > 0 ? '4' : '3'}. Bring the image back</p>
                    <div
                        onDragOver={(event) => { event.preventDefault(); setDragging(true); }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={onDrop}
                        className={`mt-2 rounded-lg border-2 border-dashed p-5 text-center ${dragging ? 'border-brand-700 bg-brand-50' : 'border-zinc-300'}`}
                    >
                        <input ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp" className="hidden" aria-label="Choose the image from Flow" onChange={(event) => { save(event.target.files?.[0]); event.target.value = ''; }} />
                        <Button type="button" disabled={saving} onClick={() => fileInput.current?.click()}>
                            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
                            {saving ? 'Saving...' : 'Choose image'}
                        </Button>
                        <p className="mt-2 text-xs text-zinc-500">Or drop it here, or copy it in Flow and press Ctrl+V here.</p>
                    </div>
                    {uploadError && <p role="alert" className="mt-2 text-sm text-rose-700">{uploadError}</p>}
                </li>
            </ol>
        </Card>
    );
}

export default function StudioIndex({ flowUrl, products, references, generations, selected, aspectRatios, maxReferences, canCreate, canDelete }: Props) {
    const current = selected ?? generations.data[0] ?? null;
    const library = [...products, ...references];
    const known = new Set(library.map((image) => image.key));
    const promptBox = useRef<HTMLTextAreaElement>(null);
    const changeBox = useRef<HTMLTextAreaElement>(null);
    const panel = useRef<HTMLDivElement>(null);

    const [prompt, setPrompt] = useState('');
    const [aspectRatio, setAspectRatio] = useState('4:5');
    const [picked, setPicked] = useState<string[]>([]);
    const [changeWords, setChangeWords] = useState('');
    const [session, setSession] = useState<FlowSession | null>(null);
    const [popupBlocked, setPopupBlocked] = useState(false);
    const [copiedAtStart, setCopiedAtStart] = useState(false);

    const chosen = picked.filter((key) => known.has(key));
    const atLimit = chosen.length >= maxReferences;

    function toggle(key: string) {
        setPicked(chosen.includes(key) ? chosen.filter((item) => item !== key) : [...chosen, key]);
    }

    function removeReference(image: ReferenceImage) {
        if (image.id && window.confirm(`Delete ${image.name}?`)) {
            setPicked(chosen.filter((item) => item !== image.key));
            router.delete(`/studio/references/${image.id}`, { preserveScroll: true, preserveState: true });
        }
    }

    function goToFlow() {
        setPopupBlocked(!openFlow(flowUrl));
    }

    /** Must run straight from the click, or the browser blocks the new tab. */
    function start(next: FlowSession) {
        setCopiedAtStart(copyText(next.flowText));
        setSession(next);
        goToFlow();
        window.setTimeout(() => panel.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
    }

    function imagesFor(keys: string[]): FlowImage[] {
        return keys.flatMap((key) => library.filter((image) => image.key === key));
    }

    function sendToFlow(event: FormEvent) {
        event.preventDefault();
        const text = prompt.trim();
        if (text === '') {
            promptBox.current?.focus();
            return;
        }
        start({ prompt: text, flowText: text, aspectRatio, referenceKeys: chosen, images: imagesFor(chosen), parentId: null });
    }

    function tryAgain(item: Generation) {
        const keys = item.references.filter((key) => known.has(key));
        if (item.parent_id && item.parent_url) {
            start({
                prompt: item.prompt,
                flowText: changeInstruction(item.prompt),
                aspectRatio: item.aspect_ratio,
                referenceKeys: keys,
                images: [{ name: 'Image to change', url: item.parent_url, filename: `studio-${item.parent_id}` }, ...imagesFor(keys)],
                parentId: item.parent_id,
            });
            return;
        }
        start({ prompt: item.prompt, flowText: item.prompt, aspectRatio: item.aspect_ratio, referenceKeys: keys, images: imagesFor(keys), parentId: null });
    }

    function editPrompt(item: Generation) {
        // A change only makes sense against an image, so its wording goes back in the change box.
        if (item.parent_id) {
            setChangeWords(item.prompt);
            changeBox.current?.focus();
            changeBox.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        setPrompt(item.prompt);
        setAspectRatio(item.aspect_ratio);
        setPicked(item.references.filter((key) => known.has(key)));
        promptBox.current?.focus();
        promptBox.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function changeImage(event: FormEvent, item: Generation) {
        event.preventDefault();
        const text = changeWords.trim();
        if (text === '') {
            return;
        }
        const keys = item.references.filter((key) => known.has(key));
        start({
            prompt: text,
            flowText: changeInstruction(text),
            aspectRatio: item.aspect_ratio,
            referenceKeys: keys,
            images: [{ name: 'Image to change', url: item.url, filename: item.filename }, ...imagesFor(keys)],
            parentId: item.id,
        });
    }

    function removeGeneration(item: Generation) {
        if (window.confirm('Delete this image? This cannot be undone.')) {
            router.delete(`/studio/images/${item.id}`, { preserveScroll: true });
        }
    }

    const ratioLabel = (value: string) => aspectRatios.find((ratio) => ratio.value === value)?.label ?? value;

    return (
        <AppLayout>
            <Head title="Studio" />
            <PageHeader
                title="Studio"
                description="Plan images here, make them in Google Flow with your own credits, and keep the results for the team."
                actions={<Button type="button" variant="secondary" onClick={goToFlow}><ExternalLink className="h-4 w-4" /> Open Google Flow</Button>}
            />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
                {canCreate && (
                    <Card className="p-4">
                        <form onSubmit={sendToFlow} className="space-y-5">
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
                            </section>

                            <Field label="Describe the image">
                                <Textarea
                                    ref={promptBox}
                                    value={prompt}
                                    onChange={(event) => setPrompt(event.target.value)}
                                    maxLength={4000}
                                    rows={5}
                                    required
                                    placeholder="Example: Place this bottle on a wooden table in morning sunlight, with fresh wheatgrass behind it. Keep the label exactly as it is."
                                />
                            </Field>

                            <Field label="Size">
                                <Select value={aspectRatio} onChange={(event) => setAspectRatio(event.target.value)}>
                                    {aspectRatios.map((ratio) => <option key={ratio.value} value={ratio.value}>{ratio.label}</option>)}
                                </Select>
                            </Field>

                            <div>
                                <Button type="submit" className="w-full"><Wand2 className="h-4 w-4" /> Create in Google Flow</Button>
                                <p className="mt-1.5 text-center text-xs text-zinc-500">Copies your prompt and opens Flow in another tab.</p>
                            </div>
                        </form>
                    </Card>
                )}

                <div className="min-w-0 space-y-6">
                    <div ref={panel} className="scroll-mt-20">
                        {session && (
                            <FlowPanel
                                session={session}
                                flowUrl={flowUrl}
                                ratioLabel={ratioLabel(session.aspectRatio)}
                                popupBlocked={popupBlocked}
                                copiedAtStart={copiedAtStart}
                                onCancel={() => setSession(null)}
                                onOpenFlow={goToFlow}
                                onSaved={() => { setSession(null); setChangeWords(''); }}
                            />
                        )}
                    </div>

                    <Card className="p-4">
                        {current ? (
                            <div className="space-y-4">
                                <div className="overflow-hidden rounded-lg bg-zinc-100">
                                    <a href={current.url} target="_blank" rel="noreferrer">
                                        <img src={current.url} alt={current.prompt} className="mx-auto max-h-[36rem] w-auto max-w-full" />
                                    </a>
                                </div>
                                <div>
                                    <p className="whitespace-pre-line text-sm text-zinc-800">{current.prompt}</p>
                                    <p className="mt-1 text-xs text-zinc-500">
                                        {current.width && current.height ? `${current.width} x ${current.height}` : current.aspect_ratio}{current.parent_id ? ' · Changed from an earlier image' : ''}{current.user ? ` · ${current.user}` : ''}{current.created_at ? ` · ${formatDateTime(current.created_at)}` : ''}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button asChild variant="secondary" size="sm">
                                        <a href={current.download_url}><Download className="h-4 w-4" /> Download</a>
                                    </Button>
                                    {canCreate && (
                                        <>
                                            <Button type="button" variant="secondary" size="sm" onClick={() => tryAgain(current)}>
                                                <RefreshCw className="h-4 w-4" /> Try again in Flow
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
                                {canCreate && (
                                    <form onSubmit={(event) => changeImage(event, current)} className="space-y-2 border-t border-zinc-100 pt-4">
                                        <Field label="Change this image" hint="Say only what should change. Studio prepares the wording and the image for Flow.">
                                            <Textarea ref={changeBox} value={changeWords} onChange={(event) => setChangeWords(event.target.value)} maxLength={4000} rows={2} placeholder="Example: Make the background lighter and move the bottle to the left." />
                                        </Field>
                                        <Button type="submit" size="sm" disabled={changeWords.trim() === ''}><Wand2 className="h-4 w-4" /> Change in Google Flow</Button>
                                    </form>
                                )}
                            </div>
                        ) : (
                            <EmptyState title="No images yet" body="Images you bring back from Flow will appear here for the team." />
                        )}
                    </Card>

                    {generations.total > 0 && (
                        <section>
                            <h2 className="mb-2 text-sm font-semibold">All images</h2>
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
