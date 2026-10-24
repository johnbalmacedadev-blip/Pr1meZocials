import type { ReactNode } from 'react';
import { tone } from '@/types';

const glyphs: Record<string, (props: { className?: string }) => ReactNode> = {
    facebook: ({ className }) => (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true" fill="currentColor">
            <path d="M14 8.2h2.4V4.8H14c-2.4 0-4.1 1.8-4.1 4.1v1.7H7.6v3.4h2.3V20h3.5v-6h2.4l.5-3.4h-2.9V9.2c0-.6.5-1 1.1-1z" />
        </svg>
    ),
    instagram: ({ className }) => (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true" fill="currentColor">
            <path d="M12 7.2A4.8 4.8 0 1 0 12 16.8 4.8 4.8 0 0 0 12 7.2zm0 7.9a3.1 3.1 0 1 1 0-6.2 3.1 3.1 0 0 1 0 6.2z" />
            <path d="M17.5 2.5h-11A4 4 0 0 0 2.5 6.5v11a4 4 0 0 0 4 4h11a4 4 0 0 0 4-4v-11a4 4 0 0 0-4-4zm2.3 15a2.3 2.3 0 0 1-2.3 2.3h-11A2.3 2.3 0 0 1 4.2 17.5v-11A2.3 2.3 0 0 1 6.5 4.2h11a2.3 2.3 0 0 1 2.3 2.3v11z" />
            <circle cx="17.4" cy="6.6" r="1.2" />
        </svg>
    ),
    tiktok: ({ className }) => (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true" fill="currentColor">
            <path d="M19.6 8.3a5.7 5.7 0 0 1-3.4-1.1v7.2a5.6 5.6 0 1 1-5.6-5.6c.3 0 .6 0 .9.1v2.8a2.8 2.8 0 1 0 2 2.7V2.5h2.7a5.7 5.7 0 0 0 3.4 3.3v2.5z" />
        </svg>
    ),
    youtube: ({ className }) => (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true" fill="currentColor">
            <path d="M21.6 7.2a2.7 2.7 0 0 0-1.9-1.9C18 4.9 12 4.9 12 4.9s-6 0-7.7.4A2.7 2.7 0 0 0 2.4 7.2 28 28 0 0 0 2 12a28 28 0 0 0 .4 4.8 2.7 2.7 0 0 0 1.9 1.9c1.7.4 7.7.4 7.7.4s6 0 7.7-.4a2.7 2.7 0 0 0 1.9-1.9A28 28 0 0 0 22 12a28 28 0 0 0-.4-4.8zM10 15.2V8.8l5.2 3.2L10 15.2z" />
        </svg>
    ),
    linkedin: ({ className }) => (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true" fill="currentColor">
            <path d="M6.3 9.3H3.5V20h2.8V9.3zM4.9 4a1.6 1.6 0 1 0 0 3.2 1.6 1.6 0 0 0 0-3.2zM20.5 20h-2.8v-5.2c0-1.2 0-2.8-1.7-2.8s-2 1.3-2 2.7V20H11V9.3h2.7v1.5h.1c.4-.7 1.3-1.5 2.7-1.5 2.9 0 3.4 1.9 3.4 4.4V20z" />
        </svg>
    ),
    x: ({ className }) => (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true" fill="currentColor">
            <path d="M17.6 3H20l-6.2 7.1L21 21h-5.5l-4.3-5.6L6.2 21H3.8l6.6-7.6L3 3h5.6l3.9 5.1L17.6 3zm-1 16.2h1.5L7.5 4.7H5.9l10.7 14.5z" />
        </svg>
    ),
    pinterest: ({ className }) => (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true" fill="currentColor">
            <path d="M12 2.5A9.5 9.5 0 0 0 5.4 17c.4-.7.8-1.4.8-2.1 0-.7-.4-1.7-.8-2.4.7-2.8 2.7-5.4 5.7-5.4 2.7 0 4.3 1.7 4.3 4.1 0 2.6-1.3 4.5-3.1 4.5-.9 0-1.6-.8-1.4-1.7.3-1.1.8-2.3.8-3.1 0-.7-.4-1.3-1.1-1.3-1.1 0-1.9 1.1-1.9 2.6 0 .9.3 1.5.3 1.5l-1.2 5.1c-.2.9-.1 2.3-.1 2.6A9.5 9.5 0 1 0 12 2.5z" />
        </svg>
    ),
};

export default function PlatformIcon({
    slug,
    name,
    color,
    size = 'md',
}: {
    slug: string;
    name: string;
    color?: string | null;
    size?: 'sm' | 'md';
}) {
    const Glyph = glyphs[slug];
    const toneClass = tone[color || ''] ?? tone.zinc;
    const box = size === 'sm' ? 'h-7 w-7' : 'h-9 w-9';
    const icon = size === 'sm' ? 'h-3.5 w-3.5' : 'h-4.5 w-4.5';

    return (
        <span
            className={`inline-flex shrink-0 items-center justify-center rounded-full ${box} ${toneClass}`}
            title={name}
            aria-hidden="true"
        >
            {Glyph ? (
                <Glyph className={icon} />
            ) : (
                <span className="text-xs font-semibold uppercase">{name.trim().charAt(0) || '?'}</span>
            )}
        </span>
    );
}
