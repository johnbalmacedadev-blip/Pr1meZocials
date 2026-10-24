import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

export function Logo({ className, variant = 'full' }: { className?: string; variant?: 'full' | 'mark' }) {
    const { basePath = '' } = usePage<SharedProps>().props;
    const mark = variant === 'mark';

    return (
        <img
            src={`${basePath}/brand/${mark ? 'primez-z.png' : 'primez-logo.png'}`}
            alt="pr1meZ"
            className={cn(mark ? 'h-8 w-8 object-contain' : 'h-9 w-auto', className)}
        />
    );
}
