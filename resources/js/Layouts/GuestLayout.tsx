import { ReactNode } from 'react';

export default function GuestLayout({ children }: { children: ReactNode }) {
    return (
        <div className="grid min-h-screen place-items-center bg-zinc-100 px-4 py-10">
            <div className="w-full max-w-md">
                <div className="mb-6 flex items-center gap-3">
                    <span className="grid h-10 w-10 place-items-center rounded-lg bg-teal-800 text-sm font-semibold text-white">PZ</span>
                    <div>
                        <p className="text-lg font-semibold text-zinc-950">Prime Z</p>
                        <p className="text-sm text-zinc-600">Creative operations</p>
                    </div>
                </div>
                {children}
            </div>
        </div>
    );
}
