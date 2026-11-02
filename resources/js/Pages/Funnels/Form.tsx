import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Button, Field, Input, PageHeader, Select } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import type { SharedProps } from '@/types';

interface FunnelData {
    id: number;
    brand_id: number;
    name: string;
    product_name: string;
    slug: string;
    template: string;
    status: string;
    unit_price: string;
    compare_at_price: string;
}

interface Options {
    brands: { id: number; name: string }[];
    templates: { key: string; label: string }[];
    statuses: string[];
}

function slugify(value: string): string {
    return value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}

export default function FunnelForm({ funnel, options }: { funnel: FunnelData | null; options: Options }) {
    const { basePath = '' } = usePage<SharedProps>().props;
    const form = useForm({
        brand_id: String(funnel?.brand_id ?? options.brands[0]?.id ?? ''),
        name: funnel?.name ?? '',
        product_name: funnel?.product_name ?? '',
        slug: funnel?.slug ?? '',
        template: funnel?.template ?? options.templates[0]?.key ?? '',
        status: funnel?.status ?? 'active',
        unit_price: funnel?.unit_price ?? '',
        compare_at_price: funnel?.compare_at_price ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        if (funnel) {
            form.put(`/funnels/${funnel.id}`);
        } else {
            form.post('/funnels');
        }
    }

    const title = funnel ? 'Edit funnel' : 'New funnel';

    return (
        <AppLayout>
            <Head title={title} />
            <PageHeader title={title} description="Each funnel gets its own public link. Orders from that link are listed under the funnel." />
            <form onSubmit={submit} className="grid max-w-3xl gap-4 rounded-xl border border-zinc-200 bg-white p-5">
                <Field label="Brand" error={form.errors.brand_id}>
                    <Select value={form.data.brand_id} onChange={(e) => form.setData('brand_id', e.target.value)}>
                        {options.brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}
                    </Select>
                </Field>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Funnel name" error={form.errors.name}>
                        <Input
                            value={form.data.name}
                            onChange={(e) => {
                                const name = e.target.value;
                                form.setData((data) => ({
                                    ...data,
                                    name,
                                    slug: !funnel && (data.slug === '' || data.slug === slugify(data.name)) ? slugify(name) : data.slug,
                                }));
                            }}
                            required
                        />
                    </Field>
                    <Field label="Product" error={form.errors.product_name}>
                        <Input value={form.data.product_name} onChange={(e) => form.setData('product_name', e.target.value)} required />
                    </Field>
                </div>
                <Field label="Link" hint={`Customers open ${basePath}/f/${form.data.slug || 'your-link'}`} error={form.errors.slug}>
                    <Input value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} required />
                </Field>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Sales page" hint="The page design and wording come from this template." error={form.errors.template}>
                        <Select value={form.data.template} onChange={(e) => form.setData('template', e.target.value)}>
                            {options.templates.map((template) => <option key={template.key} value={template.key}>{template.label}</option>)}
                        </Select>
                    </Field>
                    <Field label="Status" hint="Only active funnels are open to customers." error={form.errors.status}>
                        <Select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                            {options.statuses.map((status) => <option key={status} value={status} className="capitalize">{status[0].toUpperCase() + status.slice(1)}</option>)}
                        </Select>
                    </Field>
                    <Field label="Promo price (₱)" error={form.errors.unit_price}>
                        <Input type="number" min="1" step="0.01" value={form.data.unit_price} onChange={(e) => form.setData('unit_price', e.target.value)} required />
                    </Field>
                    <Field label="Regular price (₱)" hint="Shown crossed out. The % off on the page is worked out from these two prices." error={form.errors.compare_at_price}>
                        <Input type="number" min="1" step="0.01" value={form.data.compare_at_price} onChange={(e) => form.setData('compare_at_price', e.target.value)} required />
                    </Field>
                </div>
                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>{funnel ? 'Save changes' : 'Create funnel'}</Button>
                    <Link href={funnel ? `/funnels/${funnel.id}` : '/funnels'} className="inline-flex h-10 items-center rounded-lg border border-zinc-300 bg-white px-3 text-sm">Cancel</Link>
                </div>
            </form>
        </AppLayout>
    );
}
