import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ExternalLink, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';
import { Button, Card, EmptyState, Field, Input, Modal, PageHeader } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { confirmAction } from '@/lib/confirm';
import { formatDateTime, withBase } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface Result {
    id: number;
    name: string;
    picked_by: string;
    picked_at: string;
}

interface Person {
    id: number;
    name: string;
    drawn_by: string | null;
    picked: string | null;
    wishlist: string | null;
}

interface Props {
    results: Result[];
    people: Person[];
    remaining: string[];
    waiting: string[];
    total: number;
    canAdd: boolean;
    canEdit: boolean;
    canRemove: boolean;
}

export default function RolletResults({ results, people, remaining, waiting, total, canAdd, canEdit, canRemove }: Props) {
    const basePath = usePage<SharedProps>().props.basePath ?? '';
    const errors = (usePage<SharedProps>().props.errors ?? {}) as Record<string, string>;
    const addForm = useForm({ name: '' });
    const [editing, setEditing] = useState<Person | null>(null);

    async function putBack(result: Result) {
        const ok = await confirmAction({
            title: 'Put this name back?',
            message: `${result.picked_by}'s pick will be removed, so they can pick again and ${result.name} can be picked again.`,
            confirmLabel: 'Put back',
            tone: 'warning',
        });
        if (ok) {
            router.delete(`/rollet-results/${result.id}`, { preserveScroll: true });
        }
    }

    function addName(event: FormEvent) {
        event.preventDefault();
        addForm.post('/rollet-results/names', { preserveScroll: true, onSuccess: () => addForm.reset() });
    }

    async function deleteName(person: Person) {
        const effects = [
            person.drawn_by ? `${person.drawn_by} drew this name, so ${person.drawn_by} will have to pick again.` : null,
            person.picked ? `${person.name}'s pick is removed, and ${person.picked} can be picked again.` : null,
        ].filter(Boolean);
        const ok = await confirmAction({
            title: `Delete ${person.name}?`,
            message: [`${person.name} will be removed from the draw.`, ...effects].join(' '),
            confirmLabel: 'Delete',
            tone: 'danger',
        });
        if (ok) {
            router.delete(`/rollet-results/names/${person.id}`, { preserveScroll: true });
        }
    }

    return (
        <AppLayout>
            <Head title="Rollet Results" />
            <PageHeader
                title="Rollet Results"
                description={`${results.length} of ${total} names picked for the exchange gift.`}
                actions={<Button asChild variant="secondary"><a href={withBase('/rollet', basePath)} target="_blank" rel="noreferrer"><ExternalLink className="h-4 w-4" /> Open Rollet</a></Button>}
            />

            {results.length === 0 ? (
                <EmptyState title="No names picked yet" body="Picks appear here as soon as someone confirms their name on the Rollet page." />
            ) : (
                <Card className="overflow-x-auto">
                    <table className="w-full min-w-[32rem] text-left text-sm">
                        <thead className="border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-500">
                            <tr>
                                <th className="px-4 py-3 font-semibold">#</th>
                                <th className="px-4 py-3 font-semibold">Picked by</th>
                                <th className="px-4 py-3 font-semibold">Name they got</th>
                                <th className="px-4 py-3 font-semibold">When</th>
                                {canRemove && <th className="px-4 py-3"><span className="sr-only">Actions</span></th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-zinc-100">
                            {results.map((result, index) => (
                                <tr key={result.id}>
                                    <td className="px-4 py-3 text-zinc-500">{index + 1}</td>
                                    <td className="px-4 py-3 font-medium text-zinc-900">{result.picked_by}</td>
                                    <td className="px-4 py-3 font-semibold text-brand-700">{result.name}</td>
                                    <td className="px-4 py-3 text-zinc-600">{formatDateTime(result.picked_at)}</td>
                                    {canRemove && (
                                        <td className="px-4 py-3 text-right">
                                            <button type="button" onClick={() => putBack(result)} aria-label={`Put ${result.name} back in the draw`} title="Put back in the draw" className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-rose-700">
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Card>
            )}

            <NameChips title="Still has to pick" names={waiting} />
            <NameChips title="Names not taken yet" names={remaining} />

            <section className="mt-8">
                <h2 className="text-base font-semibold text-zinc-900">Name list ({people.length})</h2>
                <p className="mt-0.5 text-sm text-zinc-600">Everyone on this list can pick, and can be picked.</p>

                {errors.name_list && <p role="alert" className="mt-2 rounded-lg bg-rose-50 p-3 text-sm text-rose-800">{errors.name_list}</p>}

                {canAdd && (
                    <form onSubmit={addName} className="mt-3 flex max-w-md items-start gap-2">
                        <div className="min-w-0 flex-1">
                            <Input value={addForm.data.name} onChange={(event) => { addForm.setData('name', event.target.value); addForm.clearErrors('name'); }} maxLength={80} placeholder="New name" aria-label="New name" required />
                            {addForm.errors.name && <p className="mt-1 text-sm text-rose-700">{addForm.errors.name}</p>}
                        </div>
                        <Button type="submit" disabled={addForm.processing}><Plus className="h-4 w-4" /> Add</Button>
                    </form>
                )}

                <Card className="mt-3 max-w-2xl divide-y divide-zinc-100">
                    {people.map((person) => (
                        <div key={person.id} className="flex items-center justify-between gap-3 px-4 py-2.5">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium text-zinc-900">{person.name}</p>
                                <p className="text-xs text-zinc-500">
                                    {person.picked ? `Picked ${person.picked}` : 'Has not picked'} · {person.drawn_by ? `Drawn by ${person.drawn_by}` : 'Not drawn yet'}
                                </p>
                                <p className="mt-0.5 whitespace-pre-line break-words text-xs text-zinc-600">{person.wishlist ? `Wishlist: ${person.wishlist}` : 'No wishlist yet'}</p>
                            </div>
                            <div className="flex shrink-0 items-center gap-1">
                                {canEdit && (
                                    <button type="button" onClick={() => setEditing(person)} aria-label={`Edit ${person.name}`} title="Edit name" className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800">
                                        <Pencil className="h-4 w-4" />
                                    </button>
                                )}
                                {canRemove && (
                                    <button type="button" onClick={() => deleteName(person)} aria-label={`Delete ${person.name}`} title="Delete name" className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-rose-700">
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </Card>
            </section>

            <EditNameModal person={editing} onClose={() => setEditing(null)} />
        </AppLayout>
    );
}

function EditNameModal({ person, onClose }: { person: Person | null; onClose: () => void }) {
    const form = useForm({ name: '' });

    useEffect(() => {
        if (person) {
            form.setData('name', person.name);
            form.clearErrors();
        }
    }, [person]); // eslint-disable-line react-hooks/exhaustive-deps

    function save(event: FormEvent) {
        event.preventDefault();
        if (person) {
            form.put(`/rollet-results/names/${person.id}`, { preserveScroll: true, onSuccess: onClose });
        }
    }

    return (
        <Modal open={person !== null} onOpenChange={(open) => { if (!open) onClose(); }} title={person ? `Edit ${person.name}` : 'Edit name'}>
            <form onSubmit={save} className="space-y-4">
                <Field label="Name" error={form.errors.name}>
                    <Input value={form.data.name} maxLength={80} onChange={(event) => form.setData('name', event.target.value)} required />
                </Field>
                <p className="text-sm text-zinc-600">Picks already made stay, and show the new name.</p>
                <div className="flex flex-wrap gap-2">
                    <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save'}</Button>
                    <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                </div>
            </form>
        </Modal>
    );
}

function NameChips({ title, names }: { title: string; names: string[] }) {
    if (names.length === 0) {
        return null;
    }

    return (
        <section className="mt-6">
            <h2 className="text-sm font-semibold text-zinc-900">{title} ({names.length})</h2>
            <div className="mt-2 flex flex-wrap gap-1.5">
                {names.map((name) => (
                    <span key={name} className="rounded-full bg-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-800">{name}</span>
                ))}
            </div>
        </section>
    );
}
