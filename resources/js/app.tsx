import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { ConfirmDialog } from '@/Components/ConfirmDialog';
import { Preloader } from '@/Components/Preloader';

const pages = import.meta.glob('./Pages/**/*.tsx');

function applyBasePath(basePath: string): void {
    if (!basePath) {
        return;
    }

    router.on('before', (event) => {
        const visit = event.detail.visit;
        const path = visit.url.pathname;
        if (path.startsWith('/') && !path.startsWith(`${basePath}/`) && path !== basePath) {
            visit.url.pathname = `${basePath}${path}`;
        }
    });
}

try {
    const bootPage = document.querySelector('[data-page]');
    if (bootPage?.textContent) {
        const parsed = JSON.parse(bootPage.textContent) as { props?: { basePath?: string } };
        applyBasePath(parsed.props?.basePath ?? '');
    }
} catch {
    // Ignore malformed bootstrap JSON; setup() can still apply the shared prop.
}

const boot = {
    resolve(name: string) {
        return resolvePageComponent(`./Pages/${name}.tsx`, pages) as Promise<{ default: ComponentType<object> }>;
    },
    setup({ el, App, props }: { el: HTMLElement | null; App: ComponentType<Record<string, unknown>>; props: Record<string, unknown> }) {
        if (!el) {
            return;
        }

        const initial = props.initialPage as { props?: { basePath?: string } } | undefined;
        applyBasePath(initial?.props?.basePath ?? '');

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
