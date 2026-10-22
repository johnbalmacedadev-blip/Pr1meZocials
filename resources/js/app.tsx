import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

const pages = import.meta.glob('./Pages/**/*.tsx');

const boot = {
    resolve(name: string) {
        return resolvePageComponent(`./Pages/${name}.tsx`, pages) as Promise<{ default: ComponentType<object> }>;
    },
    setup({ el, App, props }: { el: HTMLElement | null; App: ComponentType<Record<string, unknown>>; props: Record<string, unknown> }) {
        if (!el) {
            return;
        }

        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#0f766e',
    },
};

createInertiaApp(boot as unknown as Parameters<typeof createInertiaApp>[0]);
