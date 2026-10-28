import { Head, Link } from '@inertiajs/react';
import { Eye, Image as ImageIcon, Lock } from 'lucide-react';
import { Logo } from '@/Components/Logo';
import { Button, Card, PriorityBadge, StatusBadge } from '@/Components/ui';
import { formatDate } from '@/lib/utils';

interface SharedTicket {
    id: number;
    reference_number: string;
    title: string;
    details: string | null;
    format_label: string;
    priority: string | null;
    deadline: string | null;
    status: { name: string; slug: string; color: string } | null;
    images: number;
    has_drive: boolean;
}

/** Read-only summary for someone who opened a shared task link. */
export default function TicketShared({ ticket, signedIn }: { ticket: SharedTicket; signedIn: boolean }) {
    const hidden = [
        ticket.images > 0 && `${ticket.images} image${ticket.images === 1 ? '' : 's'}`,
        ticket.has_drive && 'Google Drive files',
        'the team',
    ].filter(Boolean).join(', ');

    return (
        <div className="min-h-screen bg-zinc-100 px-4 py-8">
            <Head title={ticket.title}>
                <meta name="robots" content="noindex" />
            </Head>
            <div className="mx-auto w-full max-w-2xl">
                <Logo className="mx-auto mb-6 h-10" />
                <Card className="p-5 sm:p-6">
                    <p className="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-600">
                        <Eye className="h-3.5 w-3.5" /> View only
                    </p>
                    <p className="mt-3 text-xs font-medium uppercase tracking-wide text-zinc-500">Task · {ticket.reference_number}</p>
                    <h1 className="mt-1 text-2xl font-semibold text-zinc-900">{ticket.title}</h1>
                    <div className="mt-2 flex flex-wrap items-center gap-1.5">
                        <StatusBadge status={ticket.status} />
                        <PriorityBadge priority={ticket.priority} />
                        <span className="text-xs text-zinc-500">{ticket.format_label}</span>
                        {ticket.deadline && <span className="text-xs text-zinc-500">· Due {formatDate(ticket.deadline)}</span>}
                    </div>

                    <h2 className="mt-5 text-sm font-semibold text-zinc-900">Notes / details</h2>
                    {ticket.details ? <p className="mt-2 whitespace-pre-wrap text-sm text-zinc-800">{ticket.details}</p> : <p className="mt-2 text-sm text-zinc-500">No details added.</p>}

                    <div className="mt-6 rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-4">
                        <p className="flex items-center gap-2 text-sm font-medium text-zinc-900">
                            {ticket.images > 0 ? <ImageIcon className="h-4 w-4" /> : <Lock className="h-4 w-4" />}
                            Full details are for signed-in teammates
                        </p>
                        <p className="mt-1 text-sm text-zinc-600">
                            {signedIn ? "Your account isn't on this task, so only the summary is shown." : `Log in to see ${hidden}.`}
                        </p>
                        {!signedIn && (
                            <Button asChild className="mt-3">
                                <Link href={`/tickets/${ticket.id}`}><Lock className="h-4 w-4" /> Log in to view full details</Link>
                            </Button>
                        )}
                    </div>
                </Card>
            </div>
        </div>
    );
}
