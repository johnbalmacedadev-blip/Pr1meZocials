import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

export function Logo({ className }: { className?: string }) {
    const { basePath = '' } = usePage<SharedProps>().props;

    return (
        <img
            src={`${basePath}/brand/primez-logo.png`}
            alt="pr1meZ"
            className={cn('h-9 w-auto', className)}
        />
    );
}
