import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Button } from '@/Components/ui';

export interface EmailNotificationProps {
    categories: { key: string; label: string; description: string }[];
    enabled: string[];
    email: string;
    delivers: boolean;
    mailer: string;
}

export default function EmailNotificationSettings({ categories, enabled, email, delivers, mailer }: EmailNotificationProps) {
    const form = useForm({ categories: enabled });
    const [testing, setTesting] = useState(false);
    const allOn = categories.every((category) => form.data.categories.includes(category.key));

    function toggle(key: string) {
        form.setData('categories', form.data.categories.includes(key)
            ? form.data.categories.filter((item) => item !== key)
            : [...form.data.categories, key]);
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put('/notification-preferences', { preserveScroll: true });
    }

    function sendTest() {
        setTesting(true);
        router.post('/notification-preferences/test', {}, { preserveScroll: true, onFinish: () => setTesting(false) });
    }

    return (
        <form onSubmit={submit} className="max-w-2xl space-y-4 rounded-xl border border-zinc-200 bg-white p-4">
            <div>
                <h2 className="font-semibold">Email notifications</h2>
                <p className="text-sm text-zinc-600">Choose which updates are also emailed to <span className="font-medium text-zinc-900">{email}</span>. In-app notifications are always on.</p>
            </div>
            {!delivers && (
                <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    Email delivery is off on this server (mailer: <code>{mailer}</code>), so emails are only written to the log. Set <code>MAIL_MAILER</code> to <code>smtp</code> or <code>sendmail</code> in <code>.env</code> to send real emails.
                </p>
            )}
            <label className="flex items-center gap-2 border-b border-zinc-100 pb-3 text-sm font-medium">
                <input type="checkbox" checked={allOn} onChange={() => form.setData('categories', allOn ? [] : categories.map((category) => category.key))} />
                Select all
            </label>
            <div className="space-y-3">
                {categories.map((category) => (
                    <label key={category.key} className="flex items-start gap-3 text-sm">
                        <input type="checkbox" className="mt-1" checked={form.data.categories.includes(category.key)} onChange={() => toggle(category.key)} />
                        <span>
                            <span className="block font-medium text-zinc-900">{category.label}</span>
                            <span className="block text-zinc-500">{category.description}</span>
                        </span>
                    </label>
                ))}
            </div>
            {form.errors.categories && <p className="text-sm text-rose-600">{form.errors.categories}</p>}
            <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={form.processing}>Save email settings</Button>
                <Button type="button" variant="secondary" disabled={testing} onClick={sendTest}>{testing ? 'Sending…' : 'Send test email'}</Button>
            </div>
        </form>
    );
}
