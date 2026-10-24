import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Button, Card, Field, Input } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';

export default function Join({
    token,
    role,
    organization,
    expires_at,
}: {
    token: string;
    role: { name: string; slug: string } | null;
    organization: string;
    expires_at: string | null;
}) {
    const form = useForm({
        name: '',
        username: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/join/${token}`);
    }

    return (
        <GuestLayout>
            <Head title={`Join ${organization}`} />
            <Card className="p-6">
                <h1 className="text-xl font-semibold">Join {organization}</h1>
                <p className="mt-1 text-sm text-zinc-600">
                    Create your account{role ? ` as ${role.name}` : ''}.
                    {expires_at ? ` This invite expires ${new Date(expires_at).toLocaleDateString()}.` : ''}
                </p>
                <form onSubmit={submit} className="mt-5 space-y-4">
                    <Field label="Full name" error={form.errors.name}>
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required autoComplete="name" />
                    </Field>
                    <Field label="Username" error={form.errors.username} hint="Letters, numbers, dashes, and underscores.">
                        <Input value={form.data.username} onChange={(event) => form.setData('username', event.target.value)} required autoComplete="username" />
                    </Field>
                    <Field label="Email" error={form.errors.email}>
                        <Input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required autoComplete="email" />
                    </Field>
                    <Field label="Password" error={form.errors.password}>
                        <Input type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required autoComplete="new-password" />
                    </Field>
                    <Field label="Confirm password" error={form.errors.password_confirmation}>
                        <Input type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} required autoComplete="new-password" />
                    </Field>
                    <Button type="submit" disabled={form.processing} className="w-full">
                        {form.processing ? 'Creating account…' : 'Create account'}
                    </Button>
                </form>
                <p className="mt-4 text-center text-sm text-zinc-500">
                    Already have an account? <Link href="/login" className="text-brand-700 hover:underline">Sign in</Link>
                </p>
            </Card>
        </GuestLayout>
    );
}
