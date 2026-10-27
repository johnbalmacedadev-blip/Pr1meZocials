/* Prime Z service worker: makes the app installable and shows push notifications. */

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Network only; having a fetch handler keeps the app installable on every browser.
self.addEventListener('fetch', () => {});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (error) {
        data = { title: 'Prime Z', body: event.data ? event.data.text() : '' };
    }

    const scope = self.registration.scope;
    event.waitUntil(self.registration.showNotification(data.title || 'Prime Z', {
        body: data.body || '',
        icon: scope + 'brand/icon-192.png',
        badge: scope + 'brand/icon-192.png',
        tag: data.tag || undefined,
        renotify: Boolean(data.tag),
        data: { url: data.url || scope },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url) || self.registration.scope;

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of windows) {
            if (client.url.startsWith(self.registration.scope) && 'focus' in client) {
                await client.focus();
                return 'navigate' in client ? client.navigate(target) : undefined;
            }
        }
        return self.clients.openWindow(target);
    })());
});

// If the browser rotates the subscription, the page re-syncs it with the server the next
// time the app opens (a service worker cannot send the CSRF token Laravel requires).
