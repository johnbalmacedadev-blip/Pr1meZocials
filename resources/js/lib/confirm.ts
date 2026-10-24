export type ConfirmOptions = {
    title?: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    tone?: 'danger' | 'warning';
};

type ConfirmRequest = ConfirmOptions & {
    resolve: (value: boolean) => void;
};

type Listener = (request: ConfirmRequest | null) => void;

let listener: Listener | null = null;
let pending: ConfirmRequest | null = null;

export function subscribeConfirm(next: Listener): () => void {
    listener = next;
    if (pending) {
        next(pending);
    }
    return () => {
        if (listener === next) {
            listener = null;
        }
    };
}

export function confirmAction(options: ConfirmOptions | string): Promise<boolean> {
    const request: ConfirmRequest = {
        ...(typeof options === 'string' ? { message: options } : options),
        resolve: () => undefined,
    };

    return new Promise<boolean>((resolve) => {
        request.resolve = resolve;
        pending = request;
        listener?.(request);
    });
}

export function settleConfirm(value: boolean): void {
    if (!pending) {
        return;
    }
    const current = pending;
    pending = null;
    listener?.(null);
    current.resolve(value);
}
