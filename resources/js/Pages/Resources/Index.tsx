import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Download, File, Folder, LayoutGrid, List, Pencil, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';
import { Button, EmptyState, Field, Input, Modal, PageHeader, Pagination } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { confirmAction } from '@/lib/confirm';
import { formatDate, visitFilters, withBase } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface ResourceCard {
    id: number;
    name: string;
    url: string;
    created_at: string | null;
    can_edit: boolean;
    can_delete: boolean;
    user: { id: number; name: string } | null;
}

interface DriveEntry {
    id: string;
    name: string;
    kind: 'folder' | 'file';
    mime: string | null;
    modified: string | null;
    previewable: boolean;
}

export default function ResourcesIndex({
    items,
    canSave,
    filters,
}: {
    items: { data: ResourceCard[]; prev: string | null; next: string | null; total: number; from?: number | null; to?: number | null };
    canSave: boolean;
    filters: { q: string; view: 'cards' | 'list' };
}) {
    const form = useForm({ name: '', url: '' });
    const editForm = useForm({ name: '', url: '' });
    const [editing, setEditing] = useState<ResourceCard | null>(null);
    const [browsing, setBrowsing] = useState<ResourceCard | null>(null);

    function openEdit(item: ResourceCard) {
        editForm.setData({ name: item.name, url: item.url });
        editForm.clearErrors();
        setEditing(item);
    }

    function save(event: FormEvent) {
        event.preventDefault();
        form.post('/creative-assets', { preserveScroll: true, onSuccess: () => form.reset('name', 'url') });
    }

    function saveEdit(event: FormEvent) {
        event.preventDefault();
        if (!editing) {
            return;
        }
        editForm.put(`/creative-assets/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    }

    return (
        <AppLayout>
            <Head title="Creative Assets" />
            <PageHeader title="Creative Assets" description="Save a Google Drive folder and browse its files here. Download stays in this app. View in Google is the only link that leaves." />
            <div className="mb-4 flex flex-wrap items-center gap-2">
                <form className="flex min-w-0 flex-1 items-center gap-2" onSubmit={(event) => { event.preventDefault(); const data = new FormData(event.currentTarget); visitFilters('/creative-assets', { q: String(data.get('q') ?? ''), view: filters.view }); }}>
                    <Input name="q" defaultValue={filters.q} placeholder="Search by title" aria-label="Search by title" className="h-10 max-w-sm" />
                    <Button type="submit" variant="secondary" className="h-10">Search</Button>
                </form>
                <div className="flex rounded-lg border border-zinc-200 bg-white p-1">
                    <button type="button" aria-pressed={filters.view === 'cards'} className={`inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-sm ${filters.view === 'cards' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-50'}`} onClick={() => visitFilters('/creative-assets', { q: filters.q, view: 'cards' })}>
                        <LayoutGrid className="h-4 w-4" /> Cards
                    </button>
                    <button type="button" aria-pressed={filters.view === 'list'} className={`inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-sm ${filters.view === 'list' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-50'}`} onClick={() => visitFilters('/creative-assets', { q: filters.q, view: 'list' })}>
                        <List className="h-4 w-4" /> List
                    </button>
                </div>
            </div>
            {canSave && (
                <form onSubmit={save} className="mb-6 grid gap-4 rounded-xl border border-zinc-200 bg-white p-5 md:grid-cols-[16rem_minmax(0,1fr)_auto] md:items-start">
                    <Field label="Name" error={form.errors.name}>
                        <Input className="h-10" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="Brand assets" required />
                    </Field>
                    <Field label="Google Drive folder" error={form.errors.url} hint="Paste a folder link that anyone with the link can view.">
                        <Input className="h-10" type="url" value={form.data.url} onChange={(event) => form.setData('url', event.target.value)} placeholder="https://drive.google.com/drive/folders/..." required />
                    </Field>
                    <Button type="submit" className="h-10 md:mt-6" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save'}</Button>
                </form>
            )}
            {items.data.length === 0 ? <EmptyState title={filters.q ? 'No matching titles' : 'No folders yet'} body={filters.q ? 'Try a different title, or clear the search.' : 'Name a folder, paste its Google Drive link, and save it here.'} /> : filters.view === 'list' ? (
                <div className="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white">
                    {items.data.map((item) => (
                        <article key={item.id} className="flex flex-wrap items-center gap-3 px-4 py-3">
                            <div className="min-w-0 flex-1">
                                <h2 className="font-medium text-zinc-900">{item.name}</h2>
                                <p className="truncate text-sm text-zinc-500">{item.url}</p>
                            </div>
                            <p className="text-xs text-zinc-500">{item.user?.name} · {formatDate(item.created_at)}</p>
                            <FolderActions item={item} onEdit={openEdit} onBrowse={setBrowsing} />
                        </article>
                    ))}
                </div>
            ) : (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {items.data.map((item) => (
                        <article key={item.id} className="flex flex-col rounded-xl border border-zinc-200 bg-white p-4">
                            <div className="flex items-start justify-between gap-3">
                                <h2 className="font-medium text-zinc-900">{item.name}</h2>
                                <div className="flex shrink-0 items-center gap-1">
                                    <span className="mr-1 text-xs text-zinc-500">{formatDate(item.created_at)}</span>
                                    <EditDelete item={item} onEdit={openEdit} />
                                </div>
                            </div>
                            <p className="mt-2 line-clamp-2 break-all text-sm text-zinc-600">{item.url}</p>
                            <p className="mt-2 text-xs text-zinc-500">{item.user?.name}</p>
                            <div className="mt-4 flex flex-wrap gap-2">
                                <Button type="button" onClick={() => setBrowsing(item)}>Browse</Button>
                                <Button asChild variant="secondary">
                                    <a href={item.url} target="_blank" rel="noreferrer">View in Google</a>
                                </Button>
                            </div>
                        </article>
                    ))}
                </div>
            )}
            <Pagination prev={items.prev} next={items.next} total={items.total} from={items.from} to={items.to} />
            <DriveBrowser key={browsing?.id ?? 'closed'} item={browsing} onClose={() => setBrowsing(null)} />
            <Modal open={editing !== null} onOpenChange={(open) => { if (!open) setEditing(null); }} title="Edit folder">
                <form onSubmit={saveEdit} className="space-y-4">
                    <Field label="Name" error={editForm.errors.name}>
                        <Input value={editForm.data.name} onChange={(event) => editForm.setData('name', event.target.value)} required />
                    </Field>
                    <Field label="Google Drive folder" error={editForm.errors.url}>
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

function FolderActions({ item, onEdit, onBrowse }: { item: ResourceCard; onEdit: (item: ResourceCard) => void; onBrowse: (item: ResourceCard) => void }) {
    return (
        <div className="flex items-center gap-1">
            <Button type="button" size="sm" onClick={() => onBrowse(item)}>Browse</Button>
            <Button asChild size="sm" variant="secondary">
                <a href={item.url} target="_blank" rel="noreferrer">View in Google</a>
            </Button>
            <EditDelete item={item} onEdit={onEdit} />
        </div>
    );
}

function DriveBrowser({ item, onClose }: { item: ResourceCard | null; onClose: () => void }) {
    const basePath = usePage<SharedProps>().props.basePath ?? '';
    const [path, setPath] = useState<{ id: string; name: string }[]>([]);
    const [folderId, setFolderId] = useState<string | null>(null);
    const [entries, setEntries] = useState<DriveEntry[]>([]);
    const [selected, setSelected] = useState<DriveEntry | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        setPath([]);
        setSelected(null);
        setFolderId(null);
        setEntries([]);
        setError('');
    }, [item?.id]);

    useEffect(() => {
        if (!item) {
            return;
        }

        const current = path[path.length - 1];
        const params = current ? `?folder=${encodeURIComponent(current.id)}` : '';
        let cancelled = false;
        setLoading(true);
        setError('');

        fetch(withBase(`/creative-assets/${item.id}/files${params}`, basePath), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(async (response) => {
                const data = await response.json().catch(() => ({} as Record<string, unknown>));
                if (!response.ok) {
                    const message = typeof data.message === 'string'
                        ? data.message
                        : response.status === 404
                            ? 'This folder link is invalid or could not be found.'
                            : 'This Google Drive folder could not be opened. Share it so anyone with the link can view, then try again — or use View in Google.';
                    throw new Error(message);
                }
                if (cancelled) {
                    return;
                }
                setFolderId(typeof data.folder_id === 'string' ? data.folder_id : null);
                setEntries(Array.isArray(data.entries) ? data.entries : []);
            })
            .catch((reason: Error) => {
                if (!cancelled) {
                    setEntries([]);
                    setError(reason.message);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [item, path, basePath]);

    const googleUrl = !item ? '' : path.length === 0 ? item.url : `https://drive.google.com/drive/folders/${path[path.length - 1].id}`;

    function fileHref(entry: DriveEntry, action: 'preview' | 'download') {
        if (!item || !folderId) {
            return '#';
        }

        return withBase(`/creative-assets/${item.id}/files/${entry.id}/${action}?folder=${encodeURIComponent(folderId)}`, basePath);
    }

    return (
        <Modal open={item !== null} onOpenChange={(open) => { if (!open) onClose(); }} title={item?.name ?? 'Folder'} contentClassName="!w-[min(100%-2rem,68rem)] max-h-[min(100%-2rem,90vh)] overflow-auto">
            {item && (
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-1 text-sm">
                            <button type="button" className="font-medium text-brand-700" onClick={() => { setPath([]); setSelected(null); }}>{item.name}</button>
                            {path.map((crumb, index) => (
                                <span key={crumb.id} className="flex items-center gap-1">
                                    <span className="text-zinc-400">/</span>
                                    <button type="button" className="font-medium text-brand-700" onClick={() => { setPath(path.slice(0, index + 1)); setSelected(null); }}>{crumb.name}</button>
                                </span>
                            ))}
                        </div>
                        <Button asChild variant="secondary" size="sm">
                            <a href={googleUrl} target="_blank" rel="noreferrer">View in Google</a>
                        </Button>
                    </div>
                    {error && <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-900">{error}</p>}
                    {loading && <p className="text-sm text-zinc-500">Loading files…</p>}
                    {!loading && !error && entries.length === 0 && <p className="text-sm text-zinc-500">This folder is empty.</p>}
                    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
                        <div className="grid gap-2 sm:grid-cols-2">
                            {entries.map((entry) => (
                                <button
                                    key={entry.id}
                                    type="button"
                                    className={`flex items-center gap-3 rounded-lg border p-2 text-left ${selected?.id === entry.id ? 'border-brand-700 bg-[#EEF2FB]' : 'border-zinc-200 bg-white hover:bg-zinc-50'}`}
                                    onClick={() => {
                                        if (entry.kind === 'folder') {
                                            setSelected(null);
                                            setPath((current) => [...current, { id: entry.id, name: entry.name }]);
                                            return;
                                        }
                                        setSelected(entry);
                                    }}
                                >
                                    {entry.kind === 'folder' ? <Folder className="h-8 w-8 shrink-0 text-brand-700" /> : entry.previewable && entry.mime?.startsWith('image/') ? (
                                        <img src={fileHref(entry, 'preview')} alt="" className="h-12 w-12 shrink-0 rounded object-cover" />
                                    ) : <File className="h-8 w-8 shrink-0 text-zinc-400" />}
                                    <span className="min-w-0">
                                        <span className="block truncate text-sm font-medium text-zinc-900">{entry.name}</span>
                                        <span className="block text-xs text-zinc-500">{entry.kind === 'folder' ? 'Folder' : entry.modified || 'File'}</span>
                                    </span>
                                </button>
                            ))}
                        </div>
                        <aside className="rounded-lg border border-zinc-200 bg-zinc-50 p-3">
                            {!selected && <p className="text-sm text-zinc-500">Select a file to preview it here, or open a folder.</p>}
                            {selected && (
                                <div className="space-y-3">
                                    <p className="break-all text-sm font-medium">{selected.name}</p>
                                    {selected.previewable && selected.mime?.startsWith('image/') && <img src={fileHref(selected, 'preview')} alt={selected.name} className="max-h-64 w-full rounded object-contain" />}
                                    {selected.previewable && selected.mime?.startsWith('video/') && <video src={fileHref(selected, 'preview')} controls className="max-h-64 w-full rounded" />}
                                    {!selected.previewable && <p className="text-sm text-zinc-500">Preview is not available for this file. Download it to open it on this computer.</p>}
                                    <Button asChild size="sm">
                                        <a href={fileHref(selected, 'download')}><Download className="h-4 w-4" /> Download</a>
                                    </Button>
                                </div>
                            )}
                        </aside>
                    </div>
                </div>
            )}
        </Modal>
    );
}

function EditDelete({ item, onEdit }: { item: ResourceCard; onEdit: (item: ResourceCard) => void }) {
    return (
        <>
            {item.can_edit && (
                <button type="button" aria-label={`Edit ${item.name}`} className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900" onClick={() => onEdit(item)}>
                    <Pencil className="h-4 w-4" />
                </button>
            )}
            {item.can_delete && (
                <button
                    type="button"
                    aria-label={`Delete ${item.name}`}
                    className="rounded-md p-1.5 text-zinc-500 hover:bg-rose-50 hover:text-rose-700"
                    onClick={async () => {
                        const ok = await confirmAction({
                            title: 'Delete resource?',
                            message: `Delete “${item.name}”? This cannot be undone.`,
                            confirmLabel: 'Delete',
                        });
                        if (ok) router.delete(`/creative-assets/${item.id}`, { preserveScroll: true });
                    }}
                >
                    <Trash2 className="h-4 w-4" />
                </button>
            )}
        </>
    );
}
