import { Head, usePage } from '@inertiajs/react';
import ContentForm, { emptyContentForm } from '@/Components/ContentForm';
import { PageHeader } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import type { FormOptions, SharedProps } from '@/types';

export default function Create({ options }: { options: FormOptions }) {
    const canAssign = usePage<SharedProps>().props.auth.user?.permissions.includes('content.assign') ?? false;

    return (
        <AppLayout>
            <Head title="New idea" />
            <PageHeader title="Capture an idea" description="Save a title, brand, and format now. The brief, assignments, and platform versions can wait." />
            <ContentForm initial={{ ...emptyContentForm, brand_id: options.brands[0] ? String(options.brands[0].id) : '' }} options={options} action="/content" method="post" canAssign={canAssign} />
        </AppLayout>
    );
}
