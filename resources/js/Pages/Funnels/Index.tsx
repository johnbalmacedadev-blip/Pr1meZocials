import { Head, Link } from '@inertiajs/react';
import { Check, Copy, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { Badge, EmptyState, PageHeader, Pagination } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';

interface FunnelRow {
    id: number;
    name: string;
    product_name: string;
    brand: string | null;
    status: string;
    public_url: string;
    short_link: string | null;
    price: string;
    orders_count: number;
    new_orders_count: number;
    sales_total: string;
    created_at: string | null;
}

interface PageOfFunnels {
    data: FunnelRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    from: number | null;
    to: number | null;
    total: number;
}

const statusTone: Record<string, string> = {
    active: 'bg-emerald-100 text-emerald-800',
    draft: 'bg-zinc-100 text-zinc-700',
    paused: 'bg-amber-100 text-amber-800',
};

export function FunnelStatus({ status }: { status: string }) {
    return <Badge className={`capitalize ${statusTone[status] ?? statusTone.draft}`}>{status}</Badge>;
}

export function FunnelLink({ url, compact = false }: { url: string; compact?: boolean }) {
    const [copied, setCopied] = useState(false);

    async function copy() {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            window.prompt('Copy this link', url);
        }
    }

    return (
        <div className="flex min-w-0 items-center gap-1.5">
            <a href={url} target="_blank" rel="noreferrer" className="inline-flex min-w-0 items-center gap-1 text-sm text-brand-700 hover:underline">
                <span className={`${compact ? 'max-w-44' : 'max-w-72'} truncate`}>{url.replace(/^https?:\/\//, '')}</span>
                <ExternalLink className="h-3.5 w-3.5 shrink-0" />
            </a>
            <button type="button" onClick={copy} aria-label={`Copy ${url}`} title="Copy link" className="shrink-0 rounded p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800">
                {copied ? <Check className="h-3.5 w-3.5 text-emerald-700" /> : <Copy className="h-3.5 w-3.5" />}
            </button>
        </div>
    );
}

export default function FunnelsIndex({ funnels, canCreate, canUpdate }: { funnels: PageOfFunnels; canCreate: boolean; canUpdate: boolean }) {
    return (
        <AppLayout>
            <Head title="Funnels" />
            <PageHeader
                title="Funnels"
                description="Sales pages for each product, their public links and the orders they bring in."
                actions={canCreate ? <Link href="/funnels/create" className="inline-flex h-10 items-center rounded-lg bg-brand-700 px-3 text-sm font-medium text-white">New funnel</Link> : undefined}
            />
            {funnels.data.length === 0 ? (
                <EmptyState
                    title="No funnels yet"
                    body="Create a funnel to get a shareable sales page and collect orders here."
                    action={canCreate ? <Link href="/funnels/create" className="text-sm font-medium text-brand-700">Create a funnel</Link> : undefined}
                />
            ) : (
                <div className="overflow-x-auto rounded-xl border border-zinc-200 bg-white">
                    <table className="w-full min-w-[1000px] text-left text-sm">
                        <thead className="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">Funnel</th>
                                <th className="px-4 py-3 font-medium">Link</th>
                                <th className="px-4 py-3 font-medium">Short link</th>
                                <th className="px-4 py-3 font-medium">Price</th>
                                <th className="px-4 py-3 font-medium">Orders</th>
                                <th className="px-4 py-3 font-medium">Sales</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-zinc-100">
                            {funnels.data.map((funnel) => (
                                <tr key={funnel.id} className="hover:bg-zinc-50">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <Link href={`/funnels/${funnel.id}`} className="font-medium text-zinc-900 hover:underline">{funnel.name}</Link>
                                            {funnel.new_orders_count > 0 && (
                                                <Link
                                                    href={`/funnels/${funnel.id}?status=new`}
                                                    aria-label={`${funnel.new_orders_count} new ${funnel.new_orders_count === 1 ? 'order' : 'orders'}`}
                                                    title={`${funnel.new_orders_count} new ${funnel.new_orders_count === 1 ? 'order' : 'orders'}`}
                                                    className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1.5 text-xs font-semibold text-white hover:bg-rose-700"
                                                >
                                                    {funnel.new_orders_count > 99 ? '99+' : funnel.new_orders_count}
                                                </Link>
                                            )}
                                        </div>
                                        <p className="text-xs text-zinc-500">{funnel.product_name}{funnel.brand ? ` · ${funnel.brand}` : ''}</p>
                                    </td>
                                    <td className="px-4 py-3"><FunnelLink url={funnel.public_url} compact /></td>
                                    <td className="px-4 py-3">
                                        {funnel.short_link
                                            ? <FunnelLink url={funnel.short_link} compact />
                                            : canUpdate
                                                ? <Link href={`/funnels/${funnel.id}/edit`} className="text-sm font-medium text-brand-700 hover:underline">Add short link</Link>
                                                : <span className="text-zinc-400">—</span>}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">{funnel.price}</td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <Link href={`/funnels/${funnel.id}`} className="hover:underline">{funnel.orders_count}</Link>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">{funnel.sales_total}</td>
                                    <td className="px-4 py-3"><FunnelStatus status={funnel.status} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <Pagination prev={funnels.prev_page_url} next={funnels.next_page_url} from={funnels.from} to={funnels.to} total={funnels.total} />
        </AppLayout>
    );
}
