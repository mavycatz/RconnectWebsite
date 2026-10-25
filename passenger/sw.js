self.addEventListener('install', (event) => {

    console.log('Service Worker Installed');

    self.skipWaiting();

});

self.addEventListener('activate', (event) => {

    console.log('Service Worker Activated');

});

self.addEventListener('fetch', (event) => {

    // Let network requests pass through normally

});

self.addEventListener('notificationclick', (event) => {

    // Destination alerts: clicking a notification returns the passenger to
    // the app instead of opening a second window when one is already open.
    event.notification.close();

    const target = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/passenger/home';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {

            for (const client of clients) {

                if (client.url && client.url.indexOf('/passenger/') !== -1 && 'focus' in client) {

                    return client.focus();

                }

            }

            if (self.clients.openWindow) {

                return self.clients.openWindow(target);

            }

            return null;

        })
    );

});
