import { Link } from '@inertiajs/react';
import * as Dialog from '@radix-ui/react-dialog';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { X } from 'lucide-react';
import { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';
import { priorityTone, tone } from '@/types';

const buttonStyles = cva(
    'inline-flex items-center justify-center gap-2 rounded-lg text-sm font-medium transition disabled:pointer-events-none disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700',
    {
        variants: {
            variant: {
                primary: 'bg-brand-700 text-white hover:bg-brand-800',
                secondary: 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50',
                danger: 'bg-rose-700 text-white hover:bg-rose-800',
                ghost: 'text-zinc-700 hover:bg-zinc-100',
            },
            size: {
                sm: 'h-8 px-2.5',
                md: 'h-10 px-3.5',
            },
        },
        defaultVariants: { variant: 'primary', size: 'md' },
    },
);

export function Button({
    className,
    variant,
    size,
    asChild,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & VariantProps<typeof buttonStyles> & { asChild?: boolean }) {
    const Comp = asChild ? Slot : 'button';
    return <Comp className={cn(buttonStyles({ variant, size }), className)} {...props} />;
}

export const fieldClass = 'w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none placeholder:text-zinc-400 focus:border-brand-700 focus:ring-2 focus:ring-brand-700/15';

export function Input(props: InputHTMLAttributes<HTMLInputElement>) {
    return <input {...props} className={cn(fieldClass, props.className)} />;
}

export function Textarea(props: TextareaHTMLAttributes<HTMLTextAreaElement>) {
    return <textarea {...props} className={cn(fieldClass, 'min-h-24', props.className)} />;
}

export function Select(props: SelectHTMLAttributes<HTMLSelectElement>) {
    return <select {...props} className={cn(fieldClass, props.className)} />;
}

export function Field({ label, error, hint, children }: { label: string; error?: string; hint?: string; children: ReactNode }) {
    return (
        <label className="block space-y-1.5">
            <span className="text-sm font-medium text-zinc-800">{label}</span>
            {children}
            {hint && <span className="block text-xs text-zinc-500">{hint}</span>}
            {error && <span className="block text-sm text-rose-700">{error}</span>}
        </label>
    );
}

export function Badge({ className, children }: { className?: string; children: ReactNode }) {
    return <span className={cn('inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', className)}>{children}</span>;
}

export function StatusBadge({ status }: { status?: { name: string; color: string } | null }) {
    if (!status) return null;
    return <Badge className={tone[status.color] ?? tone.zinc}>{status.name}</Badge>;
}

export function PriorityBadge({ priority }: { priority?: string | null }) {
    if (!priority) return null;
    return <Badge className={priorityTone[priority] ?? tone.zinc}>{priority}</Badge>;
}

export function Card({ className, children }: { className?: string; children: ReactNode }) {
    return <section className={cn('rounded-xl border border-zinc-200 bg-white shadow-sm', className)}>{children}</section>;
}

export function PageHeader({ title, description, actions }: { title: string; description?: string; actions?: ReactNode }) {
    return (
        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 className="text-2xl font-semibold tracking-tight text-zinc-950">{title}</h1>
                {description && <p className="mt-1 max-w-3xl text-sm text-zinc-600">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </div>
    );
}

export function EmptyState({ title, body, action }: { title: string; body: string; action?: ReactNode }) {
    return (
        <div className="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center">
            <h2 className="text-base font-semibold text-zinc-900">{title}</h2>
            <p className="mx-auto mt-2 max-w-md text-sm text-zinc-600">{body}</p>
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}

export function Pagination({ prev, next, from, to, total }: { prev: string | null; next: string | null; from?: number | null; to?: number | null; total: number }) {
    if (total === 0) return null;
    return (
        <div className="mt-4 flex items-center justify-between text-sm text-zinc-600">
            <span>Showing {from ?? 0}–{to ?? 0} of {total}</span>
            <div className="flex gap-2">
                {prev ? <Link href={prev} className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 hover:bg-zinc-50">Previous</Link> : <span className="rounded-lg border border-zinc-200 px-3 py-1.5 text-zinc-400">Previous</span>}
                {next ? <Link href={next} className="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 hover:bg-zinc-50">Next</Link> : <span className="rounded-lg border border-zinc-200 px-3 py-1.5 text-zinc-400">Next</span>}
            </div>
        </div>
    );
}

export function Modal({ open, onOpenChange, title, children, contentClassName }: { open: boolean; onOpenChange: (open: boolean) => void; title: string; children: ReactNode; contentClassName?: string }) {
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-40 bg-zinc-950/40" />
                <Dialog.Content className={cn('fixed left-1/2 top-1/2 z-50 w-[min(100%-2rem,36rem)] -translate-x-1/2 -translate-y-1/2 rounded-xl bg-white p-5 shadow-xl', contentClassName)}>
                    <div className="mb-4 flex items-start justify-between gap-4">
                        <Dialog.Title className="text-lg font-semibold">{title}</Dialog.Title>
                        <Dialog.Close className="rounded-md p-1 text-zinc-500 hover:bg-zinc-100" aria-label="Close">
                            <X className="h-4 w-4" />
                        </Dialog.Close>
                    </div>
                    {children}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
