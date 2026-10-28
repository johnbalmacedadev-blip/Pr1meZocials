import { usePage } from '@inertiajs/react';
import { Check, Copy, Mail, MessageCircle, Share2, Smartphone } from 'lucide-react';
import { useState } from 'react';
import { Button, Modal } from '@/Components/ui';
import { usePwa } from '@/lib/pwa';
import { withBase } from '@/lib/utils';
import type { SharedProps } from '@/types';

/**
 * Shares a link to a page in the app. Whoever opens it signs in first (if needed)
 * and then lands on that page; on Android the installed app opens the link itself.
 */
export function ShareButton({ path, title, kind }: { path: string; title: string; kind: string }) {
    const { basePath = '', facebookAppId = '' } = usePage<SharedProps>().props;
    const { touch } = usePwa();
    const [open, setOpen] = useState(false);
    const [copied, setCopied] = useState(false);
    const url = `${window.location.origin}${withBase(path, basePath)}`;
    const message = `${kind}: ${title}`;
    const canNativeShare = typeof navigator !== 'undefined' && typeof navigator.share === 'function';

    async function copy() {
        try {
            await navigator.clipboard.writeText(url);
        } catch {
            const field = document.getElementById('share-link') as HTMLInputElement | null;
            field?.select();
            document.execCommand('copy');
        }
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    }

    async function nativeShare() {
        try {
            await navigator.share({ title: message, text: message, url });
            setOpen(false);
        } catch {
            // Cancelled: keep the dialog open.
        }
    }

    function messenger() {
        if (touch) {
            // Opens the Messenger app's share screen when it is installed.
            window.location.href = `fb-messenger://share/?link=${encodeURIComponent(url)}`;
            return;
        }
        if (facebookAppId) {
            const dialog = `https://www.facebook.com/dialog/send?app_id=${encodeURIComponent(facebookAppId)}&link=${encodeURIComponent(url)}&redirect_uri=${encodeURIComponent(url)}`;
            window.open(dialog, '_blank', 'noopener,width=640,height=560');
            return;
        }
        void copy();
        window.open('https://www.messenger.com/', '_blank', 'noopener');
    }

    const email = `mailto:?subject=${encodeURIComponent(message)}&body=${encodeURIComponent(`${message}\n\nOpen it here: ${url}\n\nYou may need to sign in first.`)}`;

    return (
        <>
            <Button type="button" variant="secondary" onClick={() => setOpen(true)} aria-label={`Share this ${kind.toLowerCase()}`}>
                <Share2 className="h-4 w-4" /> Share
            </Button>
            <Modal open={open} title={`Share ${kind.toLowerCase()}`} onOpenChange={setOpen}>
                <p className="text-sm text-zinc-600">Send this link to a teammate. They sign in if needed, then land right on this {kind.toLowerCase()}.</p>
                <div className="mt-3 flex gap-2">
                    <input id="share-link" readOnly value={url} onFocus={(event) => event.target.select()} className="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-700" />
                    <Button type="button" variant="secondary" onClick={copy} aria-label="Copy link">
                        {copied ? <Check className="h-4 w-4 text-emerald-600" /> : <Copy className="h-4 w-4" />}
                        <span className="hidden sm:inline">{copied ? 'Copied' : 'Copy'}</span>
                    </Button>
                </div>
                <div className="mt-4 grid gap-2 sm:grid-cols-2">
                    <Button type="button" onClick={messenger} className="bg-[#0866FF] hover:bg-[#0756d6]">
                        <MessageCircle className="h-4 w-4" /> Messenger
                    </Button>
                    <Button type="button" variant="secondary" asChild>
                        <a href={email}><Mail className="h-4 w-4" /> Email</a>
                    </Button>
                    {canNativeShare && (
                        <Button type="button" variant="secondary" onClick={nativeShare} className="sm:col-span-2">
                            <Smartphone className="h-4 w-4" /> More apps…
                        </Button>
                    )}
                </div>
                {!touch && !facebookAppId && <p className="mt-3 text-xs text-zinc-500">On a computer, Messenger opens in a new tab and the link is copied so you can paste it.</p>}
            </Modal>
        </>
    );
}
