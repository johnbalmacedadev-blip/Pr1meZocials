import { Head, usePage } from '@inertiajs/react';
import ContentForm, { emptyContentForm } from '@/Components/ContentForm';
import { PageHeader } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import type { FormOptions, SharedProps } from '@/types';

export default function Create({ options }: { options: FormOptions }) {
    const canAssign = usePage<SharedProps>().props.auth.user?.permissions.includes('content.assign') ?? false;
    const prime = options.brands.find((brand) => brand.name === 'Prime Z') ?? options.brands[0];
    const createOptions = { ...options, brands: prime ? [prime] : [] };

    return (
        <AppLayout>
            <Head title="New idea" />
            <PageHeader title="Capture an idea" description="Save a title, brand, and format now. The brief, assignments, and platform versions can wait." />
            <ContentForm initial={{ ...emptyContentForm, brand_id: prime ? String(prime.id) : '' }} options={createOptions} action="/content" method="post" canAssign={canAssign} />
        </AppLayout>
    );
}
