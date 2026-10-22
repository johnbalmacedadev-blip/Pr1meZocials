import { cn } from '@/lib/utils';

export function Logo({ className }: { className?: string }) {
    return <img src="/brand/primez-logo.png" alt="pr1meZ" className={cn('h-9 w-auto', className)} />;
}
