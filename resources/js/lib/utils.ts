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

export function formatDate(value?: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}
