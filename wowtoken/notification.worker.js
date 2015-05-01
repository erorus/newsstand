console.log('v11');
self.addEventListener('push', function(event) {
    event.waitUntil(
        self.registration.pushManager.getSubscription().then(function(reg) {
            var fd = new FormData();
            fd.append('id', reg.subscriptionId);
            fd.append('endpoint', reg.endpoint);
            fd.append('action', 'fetch');

            return fetch('/subscription.php', {
                method: 'POST',
                body: fd
                }).then(function(resp) {
                    if (resp.status !== 200) {
                        throw new Error();
                    }

                    return resp.json().then(function(data) {
                        if (!data.notification) {
                            throw new Error();
                        }
                        return self.registration.showNotification(data.title, data.notification);
                    });
                });
        }).catch(function(err) {
            return self.registration.showNotification('WoWToken.info', {
                body: 'Couldn\'t fetch notification data, but something probably happened that you should check out at WoWToken.info.',
                icon: '/images/token-192x192.jpg',
                tag: 'wowtoken'
            })
        })
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    event.waitUntil(
        clients.matchAll({
            type: 'window'
        }).then(function(clientList) {
            for (var x = 0; x < clientList.length; x++) {
                var client = clientList[x];
                if (client.url == '/' && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow('/');
            }
        })
    )
});

self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open('wowtokeninfo-cache-1').then(function(cache) {
            return cache.addAll(['/images/token-192x192.jpg']);
        })
    );
});
