import { useSyncExternalStore } from 'react';
import { withBase } from '@/lib/utils';

/**
 * Installable app + push notifications.
 * - Android/Chrome: the browser's install prompt is captured and shown from our "Install app" button.
 * - iPhone/iPad: Safari has no install prompt, so we show "Share → Add to Home Screen" steps;
 *   push only works once the app is opened from the Home Screen (iOS 16.4+).
 * - When the app runs installed, push is turned on automatically (iOS still needs one tap).
 */

export type PushStatus = 'unsupported' | 'blocked' | 'off' | 'on' | 'busy';

export interface PwaState {
    installed: boolean;
    standalone: boolean;
    canPrompt: boolean;
    ios: boolean;
    touch: boolean;
    push: PushStatus;
    /** iOS (and some browsers) only allow the permission prompt after a tap. */
    needsTap: boolean;
    error: string;
}

interface InstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

// Older builds remembered "installed" forever, which hid the button after an uninstall.
const LEGACY_INSTALLED_FLAG = 'primez-app-installed';

const media = (query: string) => typeof window !== 'undefined' && window.matchMedia?.(query).matches;
const isStandalone = () => Boolean(media('(display-mode: standalone)') || (navigator as Navigator & { standalone?: boolean }).standalone);
const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const pushSupported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

function clearLegacyFlag() {
    try {
        localStorage.removeItem(LEGACY_INSTALLED_FLAG);
    } catch {
        // Private mode: nothing was stored.
    }
}

let deferredPrompt: InstallPromptEvent | null = null;
let basePath = '';
let started = false;
let state: PwaState = typeof window === 'undefined'
    ? { installed: false, standalone: false, canPrompt: false, ios: false, touch: false, push: 'unsupported', needsTap: false, error: '' }
    : {
        // Never remembered: an uninstall leaves browser storage behind, so detect it fresh each load.
        installed: isStandalone(),
        standalone: isStandalone(),
        canPrompt: false,
        ios: isIos(),
        touch: Boolean(media('(pointer: coarse)')) || navigator.maxTouchPoints > 0,
        push: pushSupported() ? 'off' : 'unsupported',
        needsTap: false,
        error: '',
    };
const listeners = new Set<() => void>();

function setState(patch: Partial<PwaState>) {
    state = { ...state, ...patch };
    listeners.forEach((listener) => listener());
}

// Chrome fires this once, early; capture it at import time so it is never missed.
// It only fires when the app is NOT installed, so it also undoes any earlier "installed" guess.
if (typeof window !== 'undefined') {
    clearLegacyFlag();
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event as InstallPromptEvent;
        setState({ canPrompt: true, installed: state.standalone });
    });
    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        setState({ installed: true, canPrompt: false });
    });
}

function xsrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
    const response = await fetch(withBase(path, basePath), {
        credentials: 'same-origin',
        ...init,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error((data as { message?: string }).message ?? `Request failed (${response.status})`);
    return data as T;
}

function keyToBytes(base64: string) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(padded);
    return Uint8Array.from(raw, (char) => char.charCodeAt(0));
}

async function registration() {
    return navigator.serviceWorker.register(withBase('/sw.js', basePath), { scope: `${basePath}/` });
}

async function saveSubscription(subscription: PushSubscription) {
    const json = subscription.toJSON();
    await api('/push/subscriptions', {
        method: 'POST',
        body: JSON.stringify({ endpoint: json.endpoint, keys: json.keys, content_encoding: (PushManager as unknown as { supportedContentEncodings?: string[] }).supportedContentEncodings?.[0] ?? 'aes128gcm' }),
    });
}

/** Asks for permission (if needed), subscribes this device and stores it on the server. */
export async function enablePush(): Promise<boolean> {
    if (!pushSupported()) return false;
    setState({ push: 'busy', error: '' });
    try {
        const permission = Notification.permission === 'granted' ? 'granted' : await Notification.requestPermission();
        if (permission === 'denied') {
            setState({ push: 'blocked', needsTap: false });
            return false;
        }
        if (permission !== 'granted') {
            setState({ push: 'off', needsTap: true });
            return false;
        }
        const reg = await registration();
        await navigator.serviceWorker.ready;
        let subscription = await reg.pushManager.getSubscription();
        if (!subscription) {
            const { public_key } = await api<{ public_key: string }>('/push/key');
            subscription = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyToBytes(public_key) });
        }
        await saveSubscription(subscription);
        setState({ push: 'on', needsTap: false });
        return true;
    } catch (exception) {
        setState({ push: 'off', error: exception instanceof Error ? exception.message : 'Could not turn on notifications.' });
        return false;
    }
}

export async function disablePush(): Promise<void> {
    if (!pushSupported()) return;
    setState({ push: 'busy', error: '' });
    try {
        const reg = await navigator.serviceWorker.getRegistration(`${basePath}/`);
        const subscription = await reg?.pushManager.getSubscription();
        if (subscription) {
            await api('/push/subscriptions/delete', { method: 'POST', body: JSON.stringify({ endpoint: subscription.endpoint }) });
            await subscription.unsubscribe();
        }
    } finally {
        setState({ push: 'off' });
    }
}

/** Shows Chrome's install dialog when available. Returns false when the user must install manually. */
export async function promptInstall(): Promise<boolean> {
    if (!deferredPrompt) return false;
    const prompt = deferredPrompt;
    deferredPrompt = null;
    setState({ canPrompt: false });
    await prompt.prompt();
    const choice = await prompt.userChoice;
    if (choice.outcome === 'accepted') {
        setState({ installed: true });
    }
    return true;
}

/** Called once from the app layout: registers the worker, detects install state, syncs push. */
export async function startPwa(base: string) {
    basePath = base;
    if (started || typeof window === 'undefined' || !('serviceWorker' in navigator)) return;
    started = true;

    try {
        const reg = await registration();

        // Chrome can tell a browser tab that this app is installed on the phone.
        const related = (navigator as Navigator & { getInstalledRelatedApps?: () => Promise<unknown[]> }).getInstalledRelatedApps;
        if (!state.installed && related) {
            const apps = await related.call(navigator).catch(() => []);
            if (apps.length > 0 && !deferredPrompt) {
                setState({ installed: true });
            }
        }

        if (!pushSupported()) return;
        if (Notification.permission === 'denied') {
            setState({ push: 'blocked' });
            return;
        }

        const subscription = await reg.pushManager.getSubscription();
        if (subscription && Notification.permission === 'granted') {
            // Re-sync every launch: covers rotated subscriptions and a different user signing in.
            await saveSubscription(subscription);
            setState({ push: 'on' });
            return;
        }

        // Installed app: turn push on automatically. iOS only allows the prompt after a tap.
        if (state.standalone) {
            if (Notification.permission === 'granted' || !state.ios) {
                await enablePush();
            } else {
                setState({ needsTap: true });
            }
        }
    } catch {
        // Service worker or push unavailable (e.g. plain http); the app still works.
    }
}

function subscribe(listener: () => void) {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

export function usePwa(): PwaState {
    return useSyncExternalStore(subscribe, () => state, () => state);
}
