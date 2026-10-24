import { ReactNode } from 'react';
import { Logo } from '@/Components/Logo';

export default function GuestLayout({ children }: { children: ReactNode }) {
    return (
        <div className="grid min-h-screen place-items-center bg-zinc-100 px-4 py-10">
            <div className="w-full max-w-md">
                <div className="mb-6 px-2 py-2">
                    <Logo className="mx-auto h-12" />
                    <p className="mt-3 text-center text-sm text-zinc-500">Creative operations</p>
                </div>
                {children}
            </div>
        </div>
    );
}
