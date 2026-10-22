import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Archive, ArchiveRestore, Pencil, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { Button, EmptyState, Field, Input, Modal, PageHeader, Pagination, Select } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';
import { tone, type SharedProps } from '@/types';

interface InspirationCard {
    id: number;
    name: string | null;
    url: string;
    embed_url: string | null;
    kind: 'iframe' | 'video' | null;
    created_at: string | null;
    can_edit: boolean;
    can_delete: boolean;
    platform: { id: number; name: string; slug: string; color: string } | null;
    user: { id: number; name: string } | null;
}

export default function InspirationIndex({
    items,
    canSave,
    archived = false,
}: {
    items: { data: InspirationCard[]; prev: string | null; next: string | null; total: number; from?: number | null; to?: number | null };
    canSave: boolean;
    archived?: boolean;
}) {
    const platforms = usePage<SharedProps>().props.lookups?.platforms ?? [];
    const instagram = platforms.find((platform) => platform.slug === 'instagram');
    const form = useForm({ name: '', platform_id: instagram ? String(instagram.id) : (platforms[0] ? String(platforms[0].id) : ''), url: '' });
    const editForm = useForm({ name: '', platform_id: '', url: '' });
    const [viewing, setViewing] = useState<InspirationCard | null>(null);
    const [editing, setEditing] = useState<InspirationCard | null>(null);

    function openEdit(item: InspirationCard) {
        editForm.setData({
            name: item.name ?? '',
            platform_id: item.platform ? String(item.platform.id) : '',
            url: item.url,
        });
        editForm.clearErrors();
        setEditing(item);
    }

    function saveEdit(event: FormEvent) {
        event.preventDefault();
        if (!editing) {
            return;
        }
        editForm.put(`/inspiration/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    }

    function save(event: FormEvent) {
        event.preventDefault();
        form.post('/inspiration', { preserveScroll: true, onSuccess: () => form.reset('name', 'url') });
    }

    return (
        <AppLayout>
            <Head title="Inspiration board" />
            <PageHeader title="Content inspiration board" description="Save a social post or reel. View opens the video here when the platform provides a player." />
            <div className="mb-4 flex gap-2">
                <button type="button" className={`rounded-full px-3 py-1 text-sm ${archived ? 'bg-white text-zinc-700' : 'bg-zinc-900 text-white'}`} onClick={() => router.get('/inspiration', {}, { preserveState: true, preserveScroll: true })}>Active</button>
                <button type="button" className={`rounded-full px-3 py-1 text-sm ${archived ? 'bg-zinc-900 text-white' : 'bg-white text-zinc-700'}`} onClick={() => router.get('/inspiration', { archived: 1 }, { preserveState: true, preserveScroll: true })}>Archived</button>
            </div>
            {canSave && !archived && (
                <form onSubmit={save} className="mb-6 grid gap-4 rounded-xl border border-zinc-200 bg-white p-5 md:grid-cols-[16rem_14rem_minmax(0,1fr)_auto] md:items-start">
                    <Field label="Name" error={form.errors.name}>
                        <Input className="h-10" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="Cafe reel" required />
                    </Field>
                    <Field label="Platform" error={form.errors.platform_id}>
                        <Select className="h-10" value={form.data.platform_id} onChange={(event) => form.setData('platform_id', event.target.value)} required>
                            <option value="">Select a platform</option>
                            {platforms.map((platform) => <option key={platform.id} value={platform.id}>{platform.name}</option>)}
                        </Select>
                    </Field>
                    <Field label="URL" error={form.errors.url} hint="Example: an Instagram reel or post link.">
                        <Input className="h-10" type="url" value={form.data.url} onChange={(event) => form.setData('url', event.target.value)} placeholder="https://www.instagram.com/reel/..." required />
                    </Field>
                    <Button type="submit" className="h-10 md:mt-6" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save'}</Button>
                </form>
            )}
            {items.data.length === 0 ? <EmptyState title={archived ? 'Nothing archived' : 'No inspiration yet'} body={archived ? 'Archived videos stay off the active board until you restore them.' : 'Choose a platform, paste a URL, and save it to the board.'} /> : (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {items.data.map((item) => (
                        <article key={item.id} className="flex flex-col rounded-xl border border-zinc-200 bg-white p-4">
                            <div className="flex items-start justify-between gap-3">
                                <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${tone[item.platform?.color ?? 'zinc'] ?? tone.zinc}`}>{item.platform?.name ?? 'Platform'}</span>
                                <div className="flex items-center gap-1">
                                    <span className="mr-1 text-xs text-zinc-500">{formatDate(item.created_at)}</span>
                                    {item.can_edit && (
                                        <button type="button" aria-label={`${archived ? 'Restore' : 'Archive'} ${item.name || 'inspiration'}`} className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900" onClick={() => router.post(`/inspiration/${item.id}/archive`, {}, { preserveScroll: true })}>
                                            {archived ? <ArchiveRestore className="h-4 w-4" /> : <Archive className="h-4 w-4" />}
                                        </button>
                                    )}
                                    {item.can_edit && !archived && (
                                        <button type="button" aria-label={`Edit ${item.name || 'inspiration'}`} className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900" onClick={() => openEdit(item)}>
                                            <Pencil className="h-4 w-4" />
                                        </button>
                                    )}
                                    {item.can_delete && (
                                        <button type="button" aria-label={`Delete ${item.name || 'inspiration'}`} className="rounded-md p-1.5 text-zinc-500 hover:bg-rose-50 hover:text-rose-700" onClick={() => router.delete(`/inspiration/${item.id}`, { preserveScroll: true })}>
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    )}
                                </div>
                            </div>
                            <h2 className="mt-3 font-medium text-zinc-900">{item.name || item.platform?.name || 'Saved video'}</h2>
                            <p className="mt-1 line-clamp-3 break-all text-sm text-zinc-700">{item.url}</p>
                            <p className="mt-2 text-xs text-zinc-500">{item.user?.name}</p>
                            <div className="mt-4">
                                <Button type="button" onClick={() => setViewing(item)}>View</Button>
                            </div>
                        </article>
                    ))}
                </div>
            )}
            <Pagination prev={items.prev} next={items.next} total={items.total} from={items.from} to={items.to} />
            <Modal open={viewing !== null} onOpenChange={(open) => { if (!open) setViewing(null); }} title={viewing?.name || viewing?.platform?.name || 'Inspiration'} contentClassName="w-[min(100%-1rem,28rem)]">
                {viewing?.kind === 'video' && viewing.embed_url && (
                    <video key={viewing.id} src={viewing.embed_url} controls className="max-h-[70vh] w-full rounded-lg bg-black" />
                )}
                {viewing?.kind === 'iframe' && viewing.embed_url && (
                    <iframe key={viewing.id} src={viewing.embed_url} title={`${viewing.platform?.name ?? 'Inspiration'} video`} className="h-[min(70vh,640px)] w-full rounded-lg bg-black" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowFullScreen />
                )}
                {viewing && !viewing.embed_url && (
                    <p className="text-sm text-zinc-600">This platform does not provide a player for this link. Open it in a new tab to watch the video.</p>
                )}
                {viewing && (
                    <a className="mt-3 inline-block text-sm font-medium text-brand-700" href={viewing.url} target="_blank" rel="noreferrer">Open original</a>
                )}
            </Modal>
            <Modal open={editing !== null} onOpenChange={(open) => { if (!open) setEditing(null); }} title="Edit inspiration">
                <form onSubmit={saveEdit} className="space-y-4">
                    <Field label="Name" error={editForm.errors.name}>
                        <Input value={editForm.data.name} onChange={(event) => editForm.setData('name', event.target.value)} required />
                    </Field>
                    <Field label="Platform" error={editForm.errors.platform_id}>
                        <Select value={editForm.data.platform_id} onChange={(event) => editForm.setData('platform_id', event.target.value)} required>
                            <option value="">Select a platform</option>
                            {platforms.map((platform) => <option key={platform.id} value={platform.id}>{platform.name}</option>)}
                        </Select>
                    </Field>
                    <Field label="URL" error={editForm.errors.url}>
                        <Input type="url" value={editForm.data.url} onChange={(event) => editForm.setData('url', event.target.value)} required />
                    </Field>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={() => setEditing(null)}>Cancel</Button>
                        <Button type="submit" disabled={editForm.processing}>{editForm.processing ? 'Saving…' : 'Save changes'}</Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}
