import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Loader2, Pencil } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { ShareButton } from '@/Components/ShareButton';
import { TicketFields, type TicketFormData, type TicketOptions } from '@/Components/TicketForm';
import { Button, Card, Modal, PriorityBadge, StatusBadge } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, withBase } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface Ticket {
    id: number;
    reference_number: string;
    title: string;
    details: string | null;
    brand: string | null;
    format: string;
    format_label: string;
    priority: string | null;
    deadline: string | null;
    drive_url: string | null;
    status: { name: string; slug: string; color: string } | null;
    creator: string | null;
    created_at: string | null;
    team: { id: number; name: string }[];
    images: { id: number; name: string }[];
}

export default function TicketShow({ ticket, shareUrl, canEdit, startEditing, ...options }: TicketOptions & { ticket: Ticket; shareUrl: string; canEdit: boolean; startEditing: boolean }) {
    const basePath = usePage<SharedProps>().props.basePath ?? '';
    const [editing, setEditing] = useState(canEdit && startEditing);
    const [viewing, setViewing] = useState<{ url: string; name: string } | null>(null);
    const images = ticket.images.map((image) => ({ ...image, url: withBase(`/tickets/${ticket.id}/images/${image.id}`, basePath) }));

    const initial = (): TicketFormData => ({
        title: ticket.title,
        details: ticket.details ?? '',
        format: ticket.format,
        priority: ticket.priority ?? 'medium',
        deadline: ticket.deadline ?? '',
        drive_url: ticket.drive_url ?? '',
        images: [],
        remove_image_ids: [],
        member_ids: ticket.team.map((person) => person.id),
    });
    const form = useForm<TicketFormData>(initial());

    function startEdit() {
        form.setData(initial());
        form.clearErrors();
        setEditing(true);
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/tickets/${ticket.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    }

    return (
        <AppLayout>
            <Head title={ticket.title} />
            <Link href="/tickets" className="mb-3 inline-flex items-center gap-1 text-sm font-medium text-zinc-600 hover:text-zinc-900"><ArrowLeft className="h-4 w-4" /> Tickets</Link>

            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">Ticket · {ticket.reference_number}</p>
                    <h1 className="mt-1 text-2xl font-semibold text-zinc-900">{ticket.title}</h1>
                    <div className="mt-2 flex flex-wrap items-center gap-1.5">
                        <StatusBadge status={ticket.status} />
                        <PriorityBadge priority={ticket.priority} />
                        <span className="text-xs text-zinc-500">{ticket.format_label}</span>
                    </div>
                </div>
                {!editing && (
                    <div className="flex gap-2">
                        <ShareButton path={shareUrl} title={ticket.title} kind="Task" viewOnly />
                        {canEdit && <Button variant="secondary" onClick={startEdit}><Pencil className="h-4 w-4" /> Edit ticket</Button>}
                    </div>
                )}
            </div>

            {editing ? (
                <Card className="p-4 sm:p-5">
                    <TicketFields
                        form={form}
                        options={options}
                        savedImages={images}
                        onSubmit={submit}
                        actions={(preparing) => (
                            <>
                                <Button type="button" variant="secondary" onClick={() => setEditing(false)} disabled={form.processing}>Cancel</Button>
                                <Button type="submit" disabled={form.processing || preparing}>
                                    {form.processing && <Loader2 className="h-4 w-4 animate-spin" />}
                                    Save changes
                                </Button>
                            </>
                        )}
                    />
                </Card>
            ) : (
                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div className="space-y-4">
                        <Card className="p-4 sm:p-5">
                            <h2 className="text-sm font-semibold text-zinc-900">Notes / details</h2>
                            {ticket.details ? <p className="mt-2 whitespace-pre-wrap text-sm text-zinc-800">{ticket.details}</p> : <p className="mt-2 text-sm text-zinc-500">No details added.</p>}
                        </Card>
                        <Card className="p-4 sm:p-5">
                            <h2 className="text-sm font-semibold text-zinc-900">Images</h2>
                            {images.length === 0 ? (
                                <p className="mt-2 text-sm text-zinc-500">No images attached.</p>
                            ) : (
                                <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    {images.map((image) => (
                                        <button key={image.id} type="button" onClick={() => setViewing(image)} className="aspect-square overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50">
                                            <img src={image.url} alt={image.name} loading="lazy" className="h-full w-full object-cover transition hover:scale-105" />
                                        </button>
                                    ))}
                                </div>
                            )}
                        </Card>
                    </div>

                    <Card className="h-fit space-y-4 p-4 text-sm">
                        <Detail label="Team">
                            {ticket.team.length === 0 ? 'No one yet' : ticket.team.map((person, index) => (
                                <span key={person.id} className="block">{person.name}{index === 0 && <span className="text-zinc-500"> · Lead</span>}</span>
                            ))}
                        </Detail>
                        <Detail label="Deadline">{ticket.deadline ? formatDate(ticket.deadline) : 'None'}</Detail>
                        {ticket.brand && <Detail label="Brand">{ticket.brand}</Detail>}
                        <Detail label="Google Drive">
                            {ticket.drive_url ? <a href={ticket.drive_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 font-medium text-brand-700 hover:underline">Open files <ExternalLink className="h-3.5 w-3.5" /></a> : 'No link'}
                        </Detail>
                        <Detail label="Created">{ticket.creator ?? 'Someone'} · {formatDate(ticket.created_at)}</Detail>
                        <Link href="/board" className="block font-medium text-brand-700 hover:underline">View on production board</Link>
                    </Card>
                </div>
            )}

            <Modal open={viewing !== null} title={viewing?.name ?? 'Image'} onOpenChange={(open) => { if (!open) setViewing(null); }} contentClassName="w-[min(100%-2rem,48rem)]">
                {viewing && <img src={viewing.url} alt={viewing.name} className="max-h-[75vh] w-full rounded-lg object-contain" />}
            </Modal>
        </AppLayout>
    );
}

function Detail({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">{label}</p>
            <div className="mt-1 text-zinc-900">{children}</div>
        </div>
    );
}
