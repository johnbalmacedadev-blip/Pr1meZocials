import { Head, Link, useForm } from '@inertiajs/react';
import { ExternalLink, Images, Loader2 } from 'lucide-react';
import { FormEvent } from 'react';
import { TicketFields, type TicketFormData, type TicketOptions } from '@/Components/TicketForm';
import { Button, Card, PageHeader, PriorityBadge, StatusBadge } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';

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

export default function Tickets({ tickets, ...options }: TicketOptions & { tickets: TicketRow[] }) {
    const form = useForm<TicketFormData>({
        title: '',
        details: '',
        brand_id: options.brands[0] ? String(options.brands[0].id) : '',
        format: 'other',
        priority: 'medium',
        deadline: '',
        drive_url: '',
        images: [],
        member_ids: [],
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/tickets', { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
    }

    return (
        <AppLayout>
            <Head title="Ticket" />
            <PageHeader title="Ticket" description="Create a task for your team. It goes straight to Ready for Production on the production board, and everyone you add is notified." />

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <Card className="p-4 sm:p-5">
                    <TicketFields
                        form={form}
                        options={options}
                        onSubmit={submit}
                        actions={(preparing) => (
                            <Button type="submit" disabled={form.processing || preparing}>
                                {form.processing && <Loader2 className="h-4 w-4 animate-spin" />}
                                Create ticket
                            </Button>
                        )}
                    />
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
                                    <Link href={`/tickets/${ticket.id}`} className="font-medium text-zinc-900 hover:underline">{ticket.title}</Link>
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
