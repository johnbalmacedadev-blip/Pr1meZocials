import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Button, Card, Field, Input } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/login');
    }

    return (
        <GuestLayout>
            <Head title="Sign in" />
            <Card className="p-6">
                <h1 className="text-xl font-semibold">Sign in</h1>
                <p className="mt-1 text-sm text-zinc-600">Use the account an administrator created for you.</p>
                <form onSubmit={submit} className="mt-5 space-y-4">
                    <Field label="Username or email" error={form.errors.email}>
                        <Input type="text" autoComplete="username" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required />
                    </Field>
                    <Field label="Password" error={form.errors.password}>
                        <Input type="password" autoComplete="current-password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} required />
                    </Field>
                    <label className="flex items-center gap-2 text-sm text-zinc-700">
                        <input type="checkbox" checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} />
                        Remember this browser
                    </label>
                    <Button type="submit" disabled={form.processing} className="w-full">{form.processing ? 'Signing in…' : 'Sign in'}</Button>
                </form>
            </Card>
        </GuestLayout>
    );
}
