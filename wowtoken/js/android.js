// thanks http://updates.html5rocks.com/2015/03/push-notificatons-on-the-open-web

wowtoken.Notification = {
    isSubscribed: false,
    createdForms: false,

    Check: function() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        navigator.serviceWorker.register('/notification.worker.js').then(wowtoken.Notification.Init);
    },

    Init: function(registration) {
        //console.log('service worker registration:', registration);

        if (!('showNotification' in ServiceWorkerRegistration.prototype)) {
            //console.warn('Notifications aren\'t supported.');
            return;
        }

        if (!('PushManager' in window)) {
            //console.warn('Push messaging isn\'t supported.');
            return;
        }

        if (Notification.permission == 'denied') {
            wowtoken.Notification.SetDenied(true);
            return;
        }

        wowtoken.Notification.SetDenied(false);

        navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
            serviceWorkerRegistration.pushManager.getSubscription().then(function(subscription) {
                if (!subscription) {
                    //console.log('No current subscription.');
                    return;
                }
                //console.log('Subscription: ', subscription);

                wowtoken.Notification.SendSubscriptionToServer(subscription);

                wowtoken.Notification.isSubscribed = true;
            }).catch(function(err) {
                //console.warn('Error during getSubscription()', err);
            });
        });
    },

    SetDenied: function(d) {
        wowtoken.Notification.CreateForms(!!d);

        if (d) {
            //console.log('User has blocked notifications.');
            wowtoken.Notification.Unsubscribe();
            $('.notifications-allowed').hide();
            $('.notifications-denied').show();
        } else {
            $('.notifications-denied').hide();
            $('.notifications-allowed').show();
        }
    },

    CreateForms: function(denied) {
        var sub = wowtoken.Storage.Get('subscription');
        var target = 0;

        for (var region in wowtoken.regions) {
            var ns = document.getElementById('ns-'+region);
            if (!ns) {
                continue;
            }
            $(ns).empty();
            var s = document.createElement('span');
            s.className = 'notifications-denied';
            s.innerHTML = 'Your browser has blocked notifications. You\'ll need to unblock WoWToken.info notifications in your browser settings to receive them.';
            if (!denied) {
                s.style.display = 'none';
            }
            ns.appendChild(s);

            var d = document.createElement('div');
            d.className = 'notifications-allowed';
            if (denied) {
                d.style.display = 'none';
            }
            ns.appendChild(d);

            var dup = document.createElement('div');
            dup.id = 'notifications-up-' + region;
            s = document.createElement('span');
            s.innerHTML = 'Tell me if '+wowtoken.regions[region]+' exceeds:';
            dup.appendChild(s);
            var sup = document.createElement('select');
            sup.id = region+'-up';
            sup.addEventListener('change', wowtoken.Notification.SelectionUpdate);
            var o = document.createElement('option');
            o.value = '0';
            o.label = '(None)';
            sup.appendChild(o);
            target = sub.hasOwnProperty(sup.id) ? sub[sup.id] : 0;
            for (var x = 1000; x < 100000; x += 1000) {
                var o = document.createElement('option');
                o.value = x;
                o.label = ''+wowtoken.NumberCommas(x)+'g';
                sup.appendChild(o);
                if (x == target) {
                    sup.selectedIndex = sup.options.length-1;
                }
            }
            dup.appendChild(sup);

            var ddn = document.createElement('div');
            ddn.id = 'notifications-dn-' + region;
            s = document.createElement('span');
            s.innerHTML = 'Tell me if '+wowtoken.regions[region]+' falls under:';
            ddn.appendChild(s);
            var sdn = document.createElement('select');
            sdn.id = region+'-dn';
            sdn.addEventListener('change', wowtoken.Notification.SelectionUpdate);
            var o = document.createElement('option');
            o.value = '0';
            o.label = '(None)';
            sdn.appendChild(o);
            target = sub.hasOwnProperty(sdn.id) ? sub[sdn.id] : 0;
            for (var x = 1000; x < 100000; x += 1000) {
                var o = document.createElement('option');
                o.value = x;
                o.label = ''+wowtoken.NumberCommas(x)+'g';
                sdn.appendChild(o);
                if (x == target) {
                    sdn.selectedIndex = sdn.options.length-1;
                }
            }
            ddn.appendChild(sdn);

            d.appendChild(ddn);
            d.appendChild(dup);
        }
    },

    Subscribe: function(evt) {
        navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
            serviceWorkerRegistration.pushManager.subscribe().then(function(subscription) {
                // sub successful
                wowtoken.Notification.isSubscribed = true;

                return wowtoken.Notification.SendSubscriptionToServer(subscription, evt);
            }).catch(function(e) {
                if (Notification.permission == 'denied') {
                    wowtoken.Notification.SetDenied(true);
                } else {
                    //console.error('Unable to subscribe to push', e);
                    return false;
                }
            });
        });
    },

    SelectionUpdate: function() {
        var evt = {
            dir: this.parentNode.id.substr(14,2),
            region: this.parentNode.id.substr(17),
            value: this.options[this.selectedIndex].value,
            action: 'selection'
        };

        if (!wowtoken.Notification.isSubscribed) {
            wowtoken.Notification.Subscribe(evt);
        } else {
            wowtoken.Notification.SendSubEvent(evt);
        }
    },

    SendSubEvent: function(evt) {
        var sub = wowtoken.Storage.Get('subscription');
        if (!sub) {
            return;
        }
        evt.id = sub.id;
        evt.endpoint = sub.endpoint;

        $.ajax({
            url: '/subscription.php',
            method: 'POST',
            data: evt,
            success: function (d) {
                var sub = wowtoken.Storage.Get('subscription');
                if (!sub) {
                    sub = {};
                }
                if (d.value == 0) {
                    delete sub[d.name];
                } else {
                    sub[d.name] = d.value;
                }
                wowtoken.Storage.Set('subscription', sub);
            }
        });

    },

    Unsubscribe: function() {
        var UnsubscribeFromStorage = function() {
            var oldSub = wowtoken.Storage.Get('subscription');
            if (oldSub) {
                $.ajax({
                    url: '/subscription.php',
                    method: 'POST',
                    data: {
                        'id': oldSub.id,
                        'endpoint': oldSub.endpoint,
                        'action': 'unsubscribe'
                    }
                });
                wowtoken.Storage.Remove('subscription');
            }
        };

        navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
            serviceWorkerRegistration.pushManager.getSubscription().then(function(subscription) {
                if (!subscription) {
                    // didn't get sub data from the browser, check localstorage and remove that too
                    UnsubscribeFromStorage();
                    wowtoken.Notification.isSubscribed = false;
                    wowtoken.Notification.SetDenied(Notification.permission == 'denied');
                    return;
                }

                var storageSub = wowtoken.Storage.Get('subscription');
                if (storageSub) {
                    if ((subscription.endpoint != storageSub.endpoint) || (subscription.subscriptionId != storageSub.id)) {
                        // weird, we have a sub in storage different than the one we're unsubbing. do both.
                        UnsubscribeFromStorage();
                    } else {
                        wowtoken.Storage.Remove('subscription');
                    }
                }

                subscription.unsubscribe().catch(function(e){
                    //console.log('Unsubscription error: ', e);
                });

                $.ajax({
                    url: '/subscription.php',
                    method: 'POST',
                    data: {
                        'id': subscription.subscriptionId,
                        'endpoint': subscription.endpoint,
                        'action': 'unsubscribe'
                    }
                });

                wowtoken.Notification.isSubscribed = false;
                wowtoken.Notification.SetDenied(Notification.permission == 'denied');
            }).catch(function(e) {
                //console.error('Could not unsubscribe from push messaging.', e);
            });
        });
    },

    SendSubscriptionToServer: function(sub, evt) {
        var data = {
            'id': sub.subscriptionId,
            'endpoint': sub.endpoint,
            'action': 'subscribe',
        };

        var oldSub = wowtoken.Storage.Get('subscription');
        if (oldSub) {
            data.oldid = oldSub.id;
            data.oldendpoint = oldSub.endpoint;
        }

        $.ajax({
            url: '/subscription.php',
            method: 'POST',
            data: data,
            success: function (d) {
                var sub = wowtoken.Storage.Get('subscription');
                var re = /^([a-z]{2})-(up|dn)$/, res, sel;
                if (!sub) {
                    sub = {};
                }
                for (var z in d) {
                    sub[z] = d[z];
                    if (res = re.exec(z)) {
                        if ((sel = document.getElementById(z)) && (sel.tagName.toLowerCase() == 'select')) {
                            for (var x = 0; x < sel.options.length; x++) {
                                if (sel.options[x].value == d[z]) {
                                    sel.selectedIndex = x;
                                    break;
                                }
                            }
                        }
                    }
                }
                wowtoken.Storage.Set('subscription', sub);

                if (evt) {
                    wowtoken.Notification.SendSubEvent(evt);
                }
            }
        });

        return true;
    },
};

$(document).ready(wowtoken.Notification.Check());
