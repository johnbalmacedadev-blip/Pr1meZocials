import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import EmailNotificationSettings, { EmailNotificationProps } from '@/Components/EmailNotificationSettings';
import { PushSettings } from '@/Components/Pwa';
import { Button, Field, Input, PageHeader } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import type { SharedProps } from '@/types';

export default function ProfileEdit({ profile, emailNotifications }: { profile: { name: string; email: string; job_title: string | null }; emailNotifications: EmailNotificationProps }) {
    const profileForm = useForm({ name: profile.name, job_title: profile.job_title ?? '' });
    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
    const roles = usePage<SharedProps>().props.auth.user?.roles.map((role) => role.name).join(', ');

    function saveProfile(event: FormEvent) {
        event.preventDefault();
        profileForm.put('/profile');
    }

    function savePassword(event: FormEvent) {
        event.preventDefault();
        passwordForm.put('/profile/password', { onSuccess: () => passwordForm.reset() });
    }

    return (
        <AppLayout>
            <Head title="Profile" />
            <PageHeader title="Profile" description={`${profile.email} · ${roles}`} />
            <form onSubmit={saveProfile} className="mb-4 max-w-xl space-y-3 rounded-xl border border-zinc-200 bg-white p-4">
                <Field label="Name" error={profileForm.errors.name}><Input value={profileForm.data.name} onChange={(event) => profileForm.setData('name', event.target.value)} /></Field>
                <Field label="Job title" error={profileForm.errors.job_title}><Input value={profileForm.data.job_title} onChange={(event) => profileForm.setData('job_title', event.target.value)} /></Field>
                <Button type="submit" disabled={profileForm.processing}>Save profile</Button>
            </form>
            <form onSubmit={savePassword} className="mb-4 max-w-xl space-y-3 rounded-xl border border-zinc-200 bg-white p-4">
                <h2 className="font-semibold">Password</h2>
                <Field label="Current password" error={passwordForm.errors.current_password}><Input type="password" value={passwordForm.data.current_password} onChange={(event) => passwordForm.setData('current_password', event.target.value)} /></Field>
                <Field label="New password" error={passwordForm.errors.password}><Input type="password" value={passwordForm.data.password} onChange={(event) => passwordForm.setData('password', event.target.value)} /></Field>
                <Field label="Confirm password"><Input type="password" value={passwordForm.data.password_confirmation} onChange={(event) => passwordForm.setData('password_confirmation', event.target.value)} /></Field>
                <Button type="submit" disabled={passwordForm.processing}>Update password</Button>
            </form>
            <div className="mb-4"><PushSettings /></div>
            <EmailNotificationSettings {...emailNotifications} />
        </AppLayout>
    );
}
