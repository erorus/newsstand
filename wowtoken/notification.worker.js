self.addEventListener('push', function(event) {
    console.log('Received a push!', event);

    event.waitUntil(
        self.registration.showNotification('WoWToken.info', {
            body: 'body here',
            tag: 'dev-token-tag',
        })
    );
});
console.log('push event added');