import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { ConfirmDialog } from '@/Components/ConfirmDialog';
import { Preloader } from '@/Components/Preloader';

const pages = import.meta.glob('./Pages/**/*.tsx');

const boot = {
    resolve(name: string) {
        return resolvePageComponent(`./Pages/${name}.tsx`, pages) as Promise<{ default: ComponentType<object> }>;
    },
    setup({ el, App, props }: { el: HTMLElement | null; App: ComponentType<Record<string, unknown>>; props: Record<string, unknown> }) {
        if (!el) {
            return;
        }

        createRoot(el).render(
            <>
                <Preloader />
                <ConfirmDialog />
                <App {...props} />
            </>,
        );
    },
    progress: {
        color: '#284cb8',
        showSpinner: true,
        delay: 100,
        includeCSS: true,
    },
};

createInertiaApp(boot as unknown as Parameters<typeof createInertiaApp>[0]);
