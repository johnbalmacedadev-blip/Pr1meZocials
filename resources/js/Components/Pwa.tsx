import { router } from '@inertiajs/react';
import { BellRing, Download, Share, SquarePlus, X } from 'lucide-react';
import { useState } from 'react';
import { Button, Modal } from '@/Components/ui';
import { disablePush, enablePush, promptInstall, usePwa } from '@/lib/pwa';
import { cn } from '@/lib/utils';

/**
 * "Install app" entry for the menu. Shown on phones and tablets until the app is installed;
 * hidden once it runs from the Home Screen or Chrome reports it installed.
 */
export function InstallAppButton({ className, onOpen }: { className?: string; onOpen?: () => void }) {
    const { installed, touch, canPrompt } = usePwa();
    const [steps, setSteps] = useState(false);

    if (installed || (!touch && !canPrompt)) return null;

    async function install() {
        // Keep the menu (and this component) mounted when showing manual steps.
        if (await promptInstall()) onOpen?.();
        else setSteps(true);
    }

    return (
        <>
            <button type="button" onClick={() => void install()} className={cn('flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm text-zinc-300 transition hover:bg-white/5 hover:text-white', className)}>
                <Download className="h-4 w-4" />
                Install app
                <span className="ml-auto rounded-full bg-brand-700 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">New</span>
            </button>
            <InstallSteps open={steps} onOpenChange={setSteps} />
        </>
    );
}

function InstallSteps({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
    const { ios } = usePwa();

    return (
        <Modal open={open} onOpenChange={onOpenChange} title="Install the app">
            {ios ? (
                <ol className="space-y-3 text-sm text-zinc-700">
                    <li className="flex gap-3"><span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">1</span><span>Open this site in <strong>Safari</strong>, then tap the <Share className="inline h-4 w-4 align-text-bottom" /> <strong>Share</strong> button.</span></li>
                    <li className="flex gap-3"><span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">2</span><span>Choose <SquarePlus className="inline h-4 w-4 align-text-bottom" /> <strong>Add to Home Screen</strong>, then <strong>Add</strong>.</span></li>
                    <li className="flex gap-3"><span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">3</span><span>Open the app from your Home Screen and tap <strong>Turn on</strong> when asked about notifications.</span></li>
                </ol>
            ) : (
                <ol className="space-y-3 text-sm text-zinc-700">
                    <li className="flex gap-3"><span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">1</span><span>Open your browser menu (<strong>⋮</strong> in Chrome).</span></li>
                    <li className="flex gap-3"><span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">2</span><span>Tap <strong>Install app</strong> or <strong>Add to Home screen</strong>.</span></li>
                    <li className="flex gap-3"><span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">3</span><span>Open the app from your Home Screen. Notifications turn on automatically after you allow them.</span></li>
                </ol>
            )}
            <p className="mt-4 rounded-lg bg-zinc-50 px-3 py-2 text-xs text-zinc-500">Once installed, you'll get chat messages and other notifications even when the app is closed.</p>
        </Modal>
    );
}

/**
 * Shown inside the installed app when notifications still need a tap (always the case on iPhone)
 * or the automatic attempt failed.
 */
export function PushBanner() {
    const { standalone, push, needsTap } = usePwa();
    const [hidden, setHidden] = useState(false);

    if (hidden || !standalone || push === 'on' || push === 'unsupported' || push === 'blocked' || !needsTap) return null;

    return (
        <div className="mx-3 mt-3 flex items-center gap-3 rounded-2xl bg-brand-50 px-4 py-3 text-sm text-brand-900 lg:mx-4">
            <BellRing className="h-5 w-5 shrink-0 text-brand-700" />
            <span className="min-w-0 flex-1">Get chat messages and updates even when the app is closed.</span>
            <Button type="button" size="sm" disabled={push === 'busy'} onClick={() => void enablePush()}>Turn on</Button>
            <button type="button" onClick={() => setHidden(true)} className="rounded-full p-1 text-brand-700 hover:bg-brand-700/10" aria-label="Dismiss"><X className="h-4 w-4" /></button>
        </div>
    );
}

/** Per-device push controls for Settings → Notifications and the profile page. */
export function PushSettings() {
    const { push, error, installed, ios, standalone } = usePwa();

    return (
        <section className="max-w-2xl space-y-3 rounded-xl border border-zinc-200 bg-white p-4">
            <div>
                <h2 className="font-semibold">Phone notifications</h2>
                <p className="text-sm text-zinc-600">Pop-up notifications on this device for chat messages and every in-app notification, even when the app is closed.</p>
            </div>
            <p className={cn('rounded-lg px-3 py-2 text-sm', push === 'on' ? 'bg-emerald-50 text-emerald-900' : push === 'blocked' ? 'bg-rose-50 text-rose-900' : 'bg-zinc-50 text-zinc-700')}>
                {push === 'on' && 'On for this device.'}
                {push === 'off' && 'Off for this device.'}
                {push === 'busy' && 'Working…'}
                {push === 'blocked' && 'Blocked. Allow notifications for this site in your phone or browser settings, then reload.'}
                {push === 'unsupported' && (ios && !standalone
                    ? 'On iPhone, install the app first (Share → Add to Home Screen) and open it from the Home Screen.'
                    : 'This browser does not support push notifications.')}
            </p>
            {error && <p className="text-sm text-rose-600">{error}</p>}
            <div className="flex flex-wrap gap-2">
                {(push === 'off' || push === 'busy') && <Button type="button" disabled={push === 'busy'} onClick={() => void enablePush()}>Turn on for this device</Button>}
                {push === 'on' && <Button type="button" variant="secondary" onClick={() => void disablePush()}>Turn off for this device</Button>}
                {push === 'on' && <Button type="button" variant="secondary" onClick={() => router.post('/push/test', {}, { preserveScroll: true })}>Send test notification</Button>}
            </div>
            {!installed && push !== 'unsupported' && <p className="text-xs text-zinc-500">Tip: install the app from the menu so notifications open straight into it.</p>}
        </section>
    );
}
