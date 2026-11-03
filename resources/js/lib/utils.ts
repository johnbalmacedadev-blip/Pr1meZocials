import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import { router } from '@inertiajs/react';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/** Prefix root-relative paths with the app subdirectory (e.g. /pr1mesocials). */
export function withBase(path: string, basePath = ''): string {
    if (!path.startsWith('/') || path.startsWith('//') || /^https?:/i.test(path)) {
        return path;
    }

    const base = basePath.replace(/\/$/, '');
    if (!base || path === base || path.startsWith(`${base}/`)) {
        return path;
    }

    return `${base}${path}`;
}

export function visitFilters(url: string, filters: Record<string, string | number | boolean | null | undefined>) {
    const query: Record<string, string> = {};

    Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            query[key] = String(value);
        }
    });

    router.get(url, query, { preserveState: true, preserveScroll: true, replace: true });
}

export function formatBytes(size: number) {
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / 1024 / 1024).toFixed(1)} MB`;
}

/** The team works in Philippine time, whatever device or location they open the app from. */
export const APP_TIME_ZONE = 'Asia/Manila';

export function formatDate(value?: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric', timeZone: APP_TIME_ZONE });
}

export function formatDateTime(value?: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', timeZone: APP_TIME_ZONE });
}

/** A moment as Philippine clock time, in the format a datetime-local input uses. */
export function philippineInputValue(value: string | Date): string {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
        timeZone: APP_TIME_ZONE, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date(value)).map((part) => [part.type, part.value]));

    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}
