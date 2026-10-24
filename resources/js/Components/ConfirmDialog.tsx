import * as Dialog from '@radix-ui/react-dialog';
import { AlertTriangle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/Components/ui';
import { settleConfirm, subscribeConfirm, type ConfirmOptions } from '@/lib/confirm';
import { cn } from '@/lib/utils';

type ActiveConfirm = ConfirmOptions & { id: number };

export function ConfirmDialog() {
    const [active, setActive] = useState<ActiveConfirm | null>(null);

    useEffect(() => subscribeConfirm((request) => {
        if (!request) {
            setActive(null);
            return;
        }
        setActive({
            id: Date.now(),
            title: request.title ?? 'Are you sure?',
            message: request.message,
            confirmLabel: request.confirmLabel ?? 'Delete',
            cancelLabel: request.cancelLabel ?? 'Cancel',
            tone: request.tone ?? 'danger',
        });
    }), []);

    const open = active !== null;
    const tone = active?.tone ?? 'danger';

    return (
        <Dialog.Root
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    settleConfirm(false);
                }
            }}
        >
            <Dialog.Portal>
                <Dialog.Overlay className="confirm-overlay fixed inset-0 z-[10000] bg-zinc-950/50 backdrop-blur-[2px]" />
                <Dialog.Content
                    className="confirm-panel fixed left-1/2 top-1/2 z-[10001] w-[min(100%-2rem,24rem)] -translate-x-1/2 -translate-y-1/2 rounded-2xl bg-white p-6 text-center shadow-2xl outline-none"
                    aria-describedby={undefined}
                >
                    {active && (
                        <>
                            <div
                                className={cn(
                                    'mx-auto mb-4 grid h-14 w-14 place-items-center rounded-full',
                                    tone === 'danger' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600',
                                )}
                            >
                                <AlertTriangle className="h-7 w-7" strokeWidth={2.25} />
                            </div>
                            <Dialog.Title className="text-lg font-semibold text-zinc-900">{active.title}</Dialog.Title>
                            <Dialog.Description className="mt-2 text-sm leading-relaxed text-zinc-600">{active.message}</Dialog.Description>
                            <div className="mt-6 grid grid-cols-2 gap-3">
                                <Button type="button" variant="secondary" onClick={() => settleConfirm(false)}>
                                    {active.cancelLabel}
                                </Button>
                                <Button
                                    type="button"
                                    variant={tone === 'danger' ? 'danger' : 'primary'}
                                    onClick={() => settleConfirm(true)}
                                >
                                    {active.confirmLabel}
                                </Button>
                            </div>
                        </>
                    )}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
