import { Head, Link, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import { Logo } from '@/Components/Logo';
import { withBase } from '@/lib/utils';
import type { SharedProps } from '@/types';

export default function LegalLayout({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    const basePath = usePage<SharedProps>().props.basePath ?? '';

    return (
        <div className="min-h-screen bg-zinc-50 text-zinc-900">
            <Head title={title} />
            <header className="border-b border-zinc-200 bg-white">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                    <Link href={withBase('/login', basePath)} className="inline-flex items-center gap-3">
                        <Logo className="h-9" />
                    </Link>
                    <nav className="flex flex-wrap items-center gap-3 text-sm font-medium text-zinc-600">
                        <Link href={withBase('/privacy', basePath)} className="hover:text-brand-700">Privacy</Link>
                        <Link href={withBase('/terms', basePath)} className="hover:text-brand-700">Terms</Link>
                        <Link href={withBase('/login', basePath)} className="rounded-lg bg-brand-700 px-3 py-1.5 text-white hover:bg-brand-800">Sign in</Link>
                    </nav>
                </div>
            </header>
            <main className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
                {children}
            </main>
            <footer className="border-t border-zinc-200 py-6 text-center text-xs text-zinc-500">
                <p>
                    <Link href={withBase('/privacy', basePath)} className="hover:text-zinc-800">Privacy Policy</Link>
                    {' · '}
                    <Link href={withBase('/terms', basePath)} className="hover:text-zinc-800">Terms of Service</Link>
                </p>
            </footer>
        </div>
    );
}
