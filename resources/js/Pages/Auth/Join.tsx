import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';
import { Button, Card, Field, Input } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';

export default function Join({
    token,
    role,
    organization,
    expires_at,
    registered = false,
    registeredName = null,
    dashboardUrl = '/dashboard',
}: {
    token: string;
    role: { name: string; slug: string } | null;
    organization: string;
    expires_at: string | null;
    registered?: boolean;
    registeredName?: string | null;
    dashboardUrl?: string;
}) {
    const form = useForm({
        name: '',
        username: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    useEffect(() => {
        if (!registered) {
            return;
        }

        const timer = window.setTimeout(() => {
            router.visit(dashboardUrl);
        }, 2500);

        return () => window.clearTimeout(timer);
    }, [registered, dashboardUrl]);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/join/${token}`);
    }

    if (registered) {
        return (
            <GuestLayout>
                <Head title="Registration successful" />
                <Card className="p-6 text-center">
                    <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                        <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                            <path d="M5 13l4 4L19 7" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                    </div>
                    <h1 className="text-xl font-semibold text-zinc-900">Registration successful</h1>
                    <p className="mt-2 text-sm text-zinc-600">
                        Welcome{registeredName ? `, ${registeredName}` : ''}! Your {organization} account is ready.
                    </p>
                    <p className="mt-1 text-sm text-zinc-500">A welcome email is on the way. Redirecting you to the dashboard…</p>
                    <Button type="button" className="mt-5 w-full" onClick={() => router.visit(dashboardUrl)}>
                        Go to dashboard now
                    </Button>
                </Card>
            </GuestLayout>
        );
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
