import { Head, Link } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function ErrorPage({ status }: { status: number }) {
    const copy: Record<number, string> = {
        403: 'You do not have permission to open this record.',
        404: 'That page or record could not be found.',
        500: 'Something went wrong. The details were logged.',
    };

    return (
        <GuestLayout>
            <Head title={`Error ${status}`} />
            <div className="rounded-xl border border-zinc-200 bg-white p-6">
                <p className="text-sm font-medium text-zinc-500">{status}</p>
                <h1 className="mt-1 text-xl font-semibold">{copy[status] ?? 'The request could not be completed.'}</h1>
                <Link href="/dashboard" className="mt-4 inline-flex text-sm font-medium text-teal-800">Back to the dashboard</Link>
            </div>
        </GuestLayout>
    );
}
