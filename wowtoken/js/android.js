wowtoken.Notification = {
    isSubscribed: false,

    Check: function() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        navigator.serviceWorker.register('/notification.worker.js').then(wowtoken.Notification.Init);
    },

    Init: function(registration) {
        console.log('service worker registration:', registration);

        if (!('showNotification' in ServiceWorkerRegistration.prototype)) {
            console.warn('Notifications aren\'t supported.');
            return;
        }

        if (Notification.permission == 'denied') {
            wowtoken.Notification.SetDenied(true);
            return;
        }

        if (!('PushManager' in window)) {
            console.warn('Push messaging isn\'t supported.');
            return;
        }

        navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
            serviceWorkerRegistration.pushManager.getSubscription().then(function(subscription) {
                if (!subscription) {
                    console.log('No current subscription.');
                    return;
                }
                console.log('Subscription: ', subscription);

                wowtoken.Notification.SendSubscriptionToServer(subscription);

                wowtoken.Notification.isSubscribed = true;
            }).catch(function(err) {
                console.warn('Error during getSubscription()', err);
            });
        });

        console.log('end of init');
    },

    SetDenied: function(d) {
        if (d) {
            console.log('User has blocked notifications.');
            wowtoken.Notification.isSubscribed = false;
        }
    },

    Subscribe: function() {
        navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
            serviceWorkerRegistration.pushManager.subscribe().then(function(subscription) {
                // sub successful
                wowtoken.Notification.isSubscribed = true;

                return wowtoken.Notification.SendSubscriptionToServer(subscription);
            }).catch(function(e) {
                if (Notification.permission == 'denied') {
                    wowtoken.Notification.SetDenied(true);
                } else {
                    console.error('Unable to subscribe to push', e);
                }
            });
        });
    },

    SendSubscriptionToServer: function(sub) {
        console.log('Send to server: ',sub);
    },
};

$(document).ready(wowtoken.Notification.Check());
