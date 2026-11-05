import { Head, router, usePage } from '@inertiajs/react';
import { ExternalLink, Trash2 } from 'lucide-react';
import { Button, Card, EmptyState, PageHeader } from '@/Components/ui';
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

export default function RolletResults({ results, remaining, total, canRemove }: { results: Result[]; remaining: string[]; total: number; canRemove: boolean }) {
    const basePath = usePage<SharedProps>().props.basePath ?? '';

    async function remove(result: Result) {
        const ok = await confirmAction({
            title: 'Put this name back?',
            message: `${result.picked_by}'s pick will be removed and ${result.name} can be picked again.`,
            confirmLabel: 'Put back',
            tone: 'warning',
        });
        if (ok) {
            router.delete(`/rollet-results/${result.id}`, { preserveScroll: true });
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
                                            <button type="button" onClick={() => remove(result)} aria-label={`Put ${result.name} back in the draw`} title="Put back in the draw" className="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-rose-700">
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

            {remaining.length > 0 && (
                <section className="mt-6">
                    <h2 className="text-sm font-semibold text-zinc-900">Not picked yet ({remaining.length})</h2>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                        {remaining.map((name, index) => (
                            <span key={`${name}-${index}`} className="rounded-full bg-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-800">{name}</span>
                        ))}
                    </div>
                </section>
            )}
        </AppLayout>
    );
}
