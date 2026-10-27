import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { ConfirmDialog } from '@/Components/ConfirmDialog';
import { Preloader } from '@/Components/Preloader';
// Side effect: catch the browser's install prompt as early as possible.
import '@/lib/pwa';

const pages = import.meta.glob('./Pages/**/*.tsx');

let hostingGuardsInstalled = false;
let resolvedBasePath = '';

/**
 * Shared hosting (cPanel) often returns Apache 403 for real PUT/PATCH/DELETE.
 * Spoof those as POST + _method so Laravel still routes them correctly.
 */
function installHostingGuards(basePath = ''): void {
    if (basePath) {
        resolvedBasePath = basePath;
    }

    if (hostingGuardsInstalled) {
        return;
    }
    hostingGuardsInstalled = true;

    router.on('before', (event) => {
        const visit = event.detail.visit as {
            method: string;
            url: { pathname: string };
            data?: FormData | Record<string, unknown>;
        };

        const base = resolvedBasePath;
        if (base) {
            const path = visit.url.pathname;
            if (path.startsWith('/') && !path.startsWith(`${base}/`) && path !== base) {
                visit.url.pathname = `${base}${path}`;
            }
        }

        const method = String(visit.method ?? 'get').toLowerCase();
        if (!['put', 'patch', 'delete'].includes(method)) {
            return;
        }

        const spoofed = method.toUpperCase();
        if (typeof FormData !== 'undefined' && visit.data instanceof FormData) {
            if (!visit.data.has('_method')) {
                visit.data.append('_method', spoofed);
            }
        } else {
            const data = (visit.data && typeof visit.data === 'object' ? visit.data : {}) as Record<string, unknown>;
            visit.data = { ...data, _method: spoofed };
        }
        visit.method = 'post';
    });
}

installHostingGuards();

try {
    const bootPage = document.querySelector('[data-page]');
    if (bootPage?.textContent) {
        const parsed = JSON.parse(bootPage.textContent) as { props?: { basePath?: string } };
        installHostingGuards(parsed.props?.basePath ?? '');
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
        installHostingGuards(initial?.props?.basePath ?? '');

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
