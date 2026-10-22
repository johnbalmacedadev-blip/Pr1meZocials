import { Head, Link, router } from '@inertiajs/react';
import { EmptyState, PageHeader, Pagination } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { formatBytes, formatDate } from '@/lib/utils';
import type { SharedProps } from '@/types';
import { usePage } from '@inertiajs/react';

interface AssetRow {
    id: number;
    name: string;
    original_filename: string;
    size: number;
    kind_label: string;
    mime_type: string;
    previewable: boolean;
    version: number;
    created_at: string | null;
    uploader: { name: string } | null;
    content_id: number | null;
    content_title: string | null;
    brand: { name: string } | null;
}

export default function Assets({ assets, filters }: { assets: { data: AssetRow[]; prev: string | null; next: string | null; total: number; from?: number | null; to?: number | null }; filters: Record<string, string>; kinds: { value: string; label: string }[] }) {
    const brands = usePage<SharedProps>().props.auth.user?.brands ?? [];
    return (
        <AppLayout>
            <Head title="Creative assets" />
            <PageHeader title="Creative assets" description="Drafts and references are stored privately. Preview and download require permission for the related content." />
            <form className="mb-4 flex flex-wrap gap-2" onSubmit={(event) => { event.preventDefault(); router.get('/assets', Object.fromEntries(new FormData(event.currentTarget).entries()), { preserveState: true }); }}>
                <input name="q" defaultValue={filters.q ?? ''} placeholder="Search files" className="rounded-lg border border-zinc-300 px-3 py-2 text-sm" />
                <select name="brand_id" defaultValue={filters.brand_id ?? ''} className="rounded-lg border border-zinc-300 px-3 py-2 text-sm"><option value="">All brands</option>{brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}</select>
                <button className="rounded-lg bg-zinc-900 px-3 text-sm text-white">Filter</button>
            </form>
            {assets.data.length === 0 ? <EmptyState title="No assets yet" body="Upload a draft from a content record. Files are not placed on a public URL." /> : (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {assets.data.map((asset) => (
                        <article key={asset.id} className="rounded-xl border border-zinc-200 bg-white p-4">
                            {asset.previewable && asset.mime_type.startsWith('image/') && <img alt="" src={`/assets/${asset.id}/preview`} className="mb-3 h-36 w-full rounded-lg object-cover" />}
                            <h2 className="font-medium">{asset.name}</h2>
                            <p className="text-sm text-zinc-500">{asset.kind_label} · v{asset.version} · {formatBytes(asset.size)}</p>
                            <p className="text-sm text-zinc-500">{asset.brand?.name} · {asset.uploader?.name} · {formatDate(asset.created_at)}</p>
                            <div className="mt-2 flex gap-3 text-sm">
                                <a className="font-medium text-teal-800" href={`/assets/${asset.id}/download`}>Download</a>
                                {asset.content_id && <Link className="text-zinc-700" href={`/content/${asset.content_id}`}>{asset.content_title}</Link>}
                            </div>
                        </article>
                    ))}
                </div>
            )}
            <Pagination prev={assets.prev} next={assets.next} total={assets.total} from={assets.from} to={assets.to} />
        </AppLayout>
    );
}
