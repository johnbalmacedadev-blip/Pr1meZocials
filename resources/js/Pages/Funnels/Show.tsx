import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Button, EmptyState, Input, PageHeader, Pagination, Select } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { FunnelLink, FunnelStatus } from '@/Pages/Funnels/Index';
import { confirmAction } from '@/lib/confirm';
import { formatDate } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface OrderRow {
    id: number;
    reference: string;
    full_name: string;
    phone: string;
    address: string;
    quantity: number;
    total: string;
    status: string;
    source: string | null;
    created_at: string | null;
}

interface Props {
    funnel: { id: number; name: string; product_name: string; status: string; public_url: string; short_link: string | null; price: string; compare_price: string };
    orders: { data: OrderRow[]; prev_page_url: string | null; next_page_url: string | null; from: number | null; to: number | null; total: number };
    filters: { status: string; q: string };
    statuses: string[];
    canManage: boolean;
    canDelete: boolean;
}

function label(status: string): string {
    return status[0].toUpperCase() + status.slice(1);
}

export default function FunnelShow({ funnel, orders, filters, statuses, canManage, canDelete }: Props) {
    const { basePath = '' } = usePage<SharedProps>().props;
    const [search, setSearch] = useState(filters.q);

    function applyFilters(next: Partial<Props['filters']>) {
        router.get(`/funnels/${funnel.id}`, { ...filters, q: search, ...next }, { preserveState: true, replace: true });
    }

    function submitSearch(event: FormEvent) {
        event.preventDefault();
        applyFilters({ q: search });
    }

    async function remove() {
        if (await confirmAction({ title: 'Delete this funnel?', message: 'Its public link stops working right away. Its orders are kept.' })) {
            router.delete(`/funnels/${funnel.id}`);
        }
    }

    function changeStatus(order: OrderRow, status: string) {
        router.put(`/funnels/${funnel.id}/orders/${order.id}`, { status }, { preserveScroll: true, preserveState: true });
    }

    return (
        <AppLayout>
            <Head title={funnel.name} />
            <PageHeader
                title={funnel.name}
                description={`${funnel.product_name} · ${funnel.price} (regular ${funnel.compare_price})`}
                actions={(
                    <div className="flex flex-wrap gap-2">
                        <Link href="/funnels" className="inline-flex h-10 items-center rounded-lg border border-zinc-300 bg-white px-3 text-sm">Back</Link>
                        <a href={`${basePath}/funnels/${funnel.id}/orders/export`} className="inline-flex h-10 items-center rounded-lg border border-zinc-300 bg-white px-3 text-sm">Export orders</a>
                        {canManage && <Link href={`/funnels/${funnel.id}/edit`} className="inline-flex h-10 items-center rounded-lg bg-brand-700 px-3 text-sm font-medium text-white">Edit funnel</Link>}
                        {canDelete && <Button type="button" variant="danger" onClick={remove}>Delete</Button>}
                    </div>
                )}
            />

            <div className="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4">
                <FunnelStatus status={funnel.status} />
                <FunnelLink url={funnel.public_url} />
                {funnel.short_link && <FunnelLink url={funnel.short_link} />}
            </div>

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <form onSubmit={submitSearch} className="flex gap-2">
                    <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search name or mobile" className="w-56" />
                    <Button type="submit" variant="secondary">Search</Button>
                </form>
                <Select value={filters.status} onChange={(e) => applyFilters({ status: e.target.value })} className="w-40" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    {statuses.map((status) => <option key={status} value={status}>{label(status)}</option>)}
                </Select>
            </div>

            {orders.data.length === 0 ? (
                <EmptyState
                    title={filters.status || filters.q ? 'No matching orders' : 'No orders yet'}
                    body={filters.status || filters.q ? 'Try a different search or status.' : 'Orders submitted from this funnel’s link will appear here.'}
                />
            ) : (
                <div className="overflow-x-auto rounded-xl border border-zinc-200 bg-white">
                    <table className="w-full min-w-[960px] text-left text-sm">
                        <thead className="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">Order</th>
                                <th className="px-4 py-3 font-medium">Customer</th>
                                <th className="px-4 py-3 font-medium">Ship to</th>
                                <th className="px-4 py-3 font-medium">Qty</th>
                                <th className="px-4 py-3 font-medium">Total</th>
                                <th className="px-4 py-3 font-medium">Source</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-zinc-100 align-top">
                            {orders.data.map((order) => (
                                <tr key={order.id}>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <p className="font-medium text-zinc-900">{order.reference}</p>
                                        <p className="text-xs text-zinc-500">{order.created_at ? formatDate(order.created_at) : ''}</p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-zinc-900">{order.full_name}</p>
                                        <a href={`tel:${order.phone}`} className="text-brand-700 hover:underline">{order.phone}</a>
                                    </td>
                                    <td className="max-w-80 px-4 py-3 text-zinc-700">{order.address}</td>
                                    <td className="px-4 py-3">{order.quantity}</td>
                                    <td className="px-4 py-3 whitespace-nowrap font-medium">{order.total}</td>
                                    <td className="px-4 py-3 text-zinc-600">{order.source ?? 'Direct'}</td>
                                    <td className="px-4 py-3">
                                        {canManage ? (
                                            <Select value={order.status} onChange={(e) => changeStatus(order, e.target.value)} className="w-32" aria-label={`Status of ${order.reference}`}>
                                                {statuses.map((status) => <option key={status} value={status}>{label(status)}</option>)}
                                            </Select>
                                        ) : label(order.status)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <Pagination prev={orders.prev_page_url} next={orders.next_page_url} from={orders.from} to={orders.to} total={orders.total} />
        </AppLayout>
    );
}
