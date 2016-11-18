var wowtoken = {

    timeLeftMap: {
        names: ['',
            'less than 30 mins',
            '30 mins to 2 hours',
            '2 to 12 hours',
            'over 12 hours'
        ]
    },

    regions: {
        'na': 'NA',
        'eu': 'EU',
        'cn': 'CN',
        'tw': 'TW',
        'kr': 'KR',
    },

    timeouts: {},

    NumberCommas: function(v) {
        return v.toFixed().split("").reverse().join("").replace(/(\d{3})(?=\d)/g, '$1,').split("").reverse().join("");
    },

    PrettySeconds: function(s) {
        s = parseInt(s, 10);

        if (s <= 0) {
            return 'Immediately';
        }
        if (s <= 90) {
            return '' + s + " seconds";
        }
        var m = Math.round(s/60);
        if (m <= 90) {
            return '' + m + ' minutes';
        }
        var h = Math.floor(m/60);
        m = m % 60;
        return '' + h + ' hours, ' + m + ' minutes';
    },

    Storage: {
        Get: function (key)
        {
            if (!window.localStorage) {
                return false;
            }

            var v = window.localStorage.getItem(key);
            if (v != null) {
                return JSON.parse(v);
            }
            else {
                return false;
            }
        },
        Set: function (key, val)
        {
            if (!window.localStorage) {
                return false;
            }

            window.localStorage.setItem(key, JSON.stringify(val));
        },
        Remove: function (key, val)
        {
            if (!window.localStorage) {
                return false;
            }

            window.localStorage.removeItem(key);
        }
    },

    // thanks http://updates.html5rocks.com/2015/03/push-notificatons-on-the-open-web
    Notification: {
        isSubscribed: false,
        createdForms: false,
        regionMinMax: {
            'na': [20000, 120000, 1000],
            'eu': [40000, 120000, 1000],
            'cn': [40000, 240000, 3000],
            'tw': [100000, 240000, 3000],
            'kr': [100000, 240000, 3000]
        },

        Check: function() {
            if (!('serviceWorker' in navigator)) {
                return;
            }

            if (navigator.userAgent.indexOf('Chrome') < 0) {
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
                if (!wowtoken.Notification.regionMinMax.hasOwnProperty(region)) {
                    continue;
                }
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
                o.innerHTML = '(None)';
                sup.appendChild(o);
                target = sub.hasOwnProperty(sup.id) ? sub[sup.id] : 0;
                for (var x = wowtoken.Notification.regionMinMax[region][0]; x <= wowtoken.Notification.regionMinMax[region][1]; x += wowtoken.Notification.regionMinMax[region][2]) {
                    var o = document.createElement('option');
                    o.value = x;
                    o.label = ''+wowtoken.NumberCommas(x)+'g';
                    o.innerHTML = ''+wowtoken.NumberCommas(x)+'g';
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
                for (var x = wowtoken.Notification.regionMinMax[region][0]; x <= wowtoken.Notification.regionMinMax[region][1]; x += wowtoken.Notification.regionMinMax[region][2]) {
                    var o = document.createElement('option');
                    o.value = x;
                    o.label = ''+wowtoken.NumberCommas(x)+'g';
                    o.innerHTML = ''+wowtoken.NumberCommas(x)+'g';
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

        GetEndpoint: function (subscription)
        {
            var endpoint = subscription.endpoint;
            // for Chrome 43
            if (endpoint === 'https://android.googleapis.com/gcm/send' &&
                'subscriptionId' in subscription) {
                return endpoint + '/' + subscription.subscriptionId;
            }
            return endpoint;
        },

        Subscribe: function(evt) {
            navigator.serviceWorker.ready.then(function(serviceWorkerRegistration) {
                serviceWorkerRegistration.pushManager.subscribe({userVisibleOnly:true}).then(function(subscription) {
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
                        var subEndpoint = wowtoken.Notification.GetEndpoint(subscription);
                        if (subEndpoint != storageSub.endpoint) {
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
                            'endpoint': wowtoken.Notification.GetEndpoint(subscription),
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
                'endpoint': wowtoken.Notification.GetEndpoint(sub),
                'action': 'subscribe',
            };

            var oldSub = wowtoken.Storage.Get('subscription');
            if (oldSub) {
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
        }
    },

    Main: function ()
    {
        wowtoken.LastVisitCheck();
        wowtoken.EUCheck();
        wowtoken.LoadUpdate();
        wowtoken.Notification.Check();
        wowtoken.LoadHistory();
    },

    EUCheck: function()
    {
        var offset = (new Date()).getTimezoneOffset();
        if ((offset < 120) && (offset > -360)) {
            var panelEU = document.getElementById('eu-panel');
            var panelNA = document.getElementById('na-panel');
            if (panelEU && panelNA) {
                panelNA.parentNode.insertBefore(panelEU, panelNA);
            }
        }
    },

    LastVisitCheck: function()
    {
        var moveFAQ = false;

        var lv = wowtoken.Storage.Get('lastvisit');
        if (lv) {
            lv = parseInt(lv, 10);
            moveFAQ |= (lv > Date.now() - (10 * 24 * 60 * 60 * 1000));
        }
        wowtoken.Storage.Set('lastvisit', Date.now());

        if (moveFAQ) {
            var panelFAQ = document.getElementById('faq-panel');
            var panelLinks = document.getElementById('links-panel');
            if (panelFAQ && panelLinks) {
                panelLinks.parentNode.insertBefore(panelFAQ, panelLinks);
            }
        }
    },

    LoadHistory: function ()
    {
        $.ajax({
            success: wowtoken.ShowHistory,
            url: '/wowtoken.json'
        });
    },

    ShowHistory: function (d)
    {
        var dest;
        for (var region in d.history) {
            if (d.history[region].length) {
                dest = document.getElementById('hc-'+region.toLowerCase());
                dest.className = 'hc';
                wowtoken.ShowChart(region, d.history[region], dest);
            }
        }
    },

    LoadUpdate: function ()
    {
        if (wowtoken.timeouts.loadUpdate) {
            window.clearTimeout(wowtoken.timeouts.loadUpdate);
            delete wowtoken.timeouts.loadUpdate;
        }

        $.ajax({
            success: wowtoken.ParseUpdate,
            url: '/snapshot.json'
        });
    },

    ParseUpdate: function (d)
    {
        for (var region in d) {
            if (!d[region].hasOwnProperty('formatted')) {
                continue;
            }
            for (var attrib in d[region].formatted) {
                $('#'+region+'-'+attrib).html(d[region].formatted[attrib]);
                $('#'+region+'-'+attrib+'-left').css('left', d[region].formatted[attrib] + '%');
            }
        }
        if (wowtoken.timeouts.loadUpdate) {
            window.clearTimeout(wowtoken.timeouts.loadUpdate);
        }
        wowtoken.timeouts.loadUpdate = window.setTimeout(wowtoken.LoadUpdate, 600000);
    },

    ShowChart: function(region, dta, dest) {
        var hcdata = { buy: [], timeleft: {}, navigator: [], pct: {}, pctchart: [], realPrices: [] };
        var maxPrice = 0;
        var o, showLabel, direction = 0, newDirection = 0, lastLabel = -1;
        var priceLowerBound = 0;
        var priceUpperBound = 100000;
        var colors = {
            'line': '#000000',
            'fill': 'rgba(51,51,51,0.6)',
            'text': '#000000',
        };

        switch (region) {
            case 'NA':
                priceLowerBound = 20000;
                priceUpperBound = 80000;
                colors = {
                    'line': '#0000ff',
                    'fill': 'rgba(204,204,255,0.6)',
                    'text': '#000099',
                };
                break;
            case 'EU':
                priceLowerBound = 40000;
                priceUpperBound = 140000;
                colors = {
                    'line': '#ff0000',
                    'fill': 'rgba(255,204,204,0.6)',
                    'text': '#990000',
                }
                break;
            case 'CN':
                priceLowerBound = 60000;
                priceUpperBound = 240000;
                colors = {
                    'line': '#00cc00',
                    'fill': 'rgba(178,230,178,0.6)',
                    'text': '#009900',
                }
                break;
            case 'TW':
                priceLowerBound = 60000;
                priceUpperBound = 240000;
                colors = {
                    'line': '#cccc00',
                    'fill': 'rgba(230,230,178,0.6)',
                    'text': '#999900',
                }
                break;
            case 'KR':
                priceLowerBound = 60000;
                priceUpperBound = 240000;
                colors = {
                    'line': '#00cccc',
                    'fill': 'rgba(178,230,230,0.6)',
                    'text': '#009999',
                }
                break;
        }
        var labelFormatter = function() {
            return wowtoken.NumberCommas(hcdata.realPrices[this.x]) + 'g';
        };

        for (var x = 0; x < dta.length; x++) {
            o = {
                x: dta[x][0]*1000,
                y: dta[x][1],
            };
            hcdata.navigator.push([dta[x][0]*1000, dta[x][1]]);
            if (x != 0) {
                for (var y = x-1; y > 0; y--) {
                    if (dta[y][0] <= (dta[x][0] - 55*60)) {
                        break;
                    }
                }
                z = x;
                hcdata.pct[o.x] = ((dta[z][1] - dta[y][1]) / dta[y][1]) / ((dta[z][0] - dta[y][0])/(60*60));
                hcdata.pctchart.push([o.x, hcdata.pct[o.x] * 100]);
            }
            showLabel = false;
            if (x + 1 < dta.length) {
                if (o.y != dta[x+1][1]) {
                    newDirection = o.y > dta[x+1][1] ? -1 : 1;
                    if (newDirection != direction) {
                        showLabel |= direction != 0;
                        direction = newDirection;
                    }
                }
            }
            showLabel &= ((lastLabel == -1) || (lastLabel + 5 < x));
            showLabel &= (lastLabel == -1) || (Math.abs((dta[x][1] - dta[lastLabel][1]) / dta[x][1]) > 0.05);
            if (showLabel) {
                lastLabel = x;
                o.dataLabels = {
                    enabled: true,
                    formatter: labelFormatter,
                    x: 0,
                    y: -5,
                    color: 'black',
                    rotation: 360-45,
                    align: 'left',
                    crop: false,
                };
                if (direction == 1) {
                    o.dataLabels.y = 10;
                    o.dataLabels.rotation = 45;
                }
                o.marker = {
                    enabled: true,
                    radius: 3,
                }
            }
            hcdata.realPrices[o.x] = o.y;
            o.y = o.y * 32 / priceUpperBound;
            hcdata.buy.push(o);
            /*
            hcdata.timeleft[dta[x][0]*1000] = wowtoken.timeLeftMap.names[dta[x][2]];
            if (dta[x][3] != null) {
                hcdata.timeleft[dta[x][0]*1000] = wowtoken.PrettySeconds(dta[x][3]);
            }
            */
            if (maxPrice < dta[x][1]) {
                maxPrice = dta[x][1];
            }
        }

        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });

        $(dest).highcharts('StockChart', {
            chart: {
                zoomType: 'x',
                backgroundColor: '#f6fff6'
            },
            rangeSelector: {
                buttons: [
                    {
                        type: 'day',
                        count: 3,
                        text: '3d'
                    },
                    {
                        type: 'week',
                        count: 1,
                        text: '1w'
                    },
                    {
                        type: 'week',
                        count: 2,
                        text: '2w'
                    },
                    {
                        type: 'month',
                        count: 1,
                        text: '1m'
                    },
                    {
                        type: 'month',
                        count: 3,
                        text: '3m'
                    },
                    {
                        type: 'all',
                        text: 'all'
                    },
                ],
                selected: 0,
                inputEnabled: false
            },
            navigator: {
                series: {
                    type: 'area',
                    name: 'Market Price',
                    color: colors.line,
                    lineColor: colors.line,
                    fillColor: colors.fill,
                    data: hcdata.navigator
                }
            },
            title: {
                text: null
            },
            subtitle: {
                text: document.ontouchstart === undefined ?
                    'Click and drag in the plot area to zoom in' :
                    'Pinch the chart to zoom in',
                style: {
                    color: 'black'
                }
            },
            xAxis: {
                type: 'datetime',
                minRange: 4 * 3600000, // four hours
                title: {
                    text: 'WoWToken.info'
                },
                labels: {
                    style: {
                        color: 'black'
                    }
                },
                units: [['hour',[6,12]],['day',[1]]],
                ordinal: false,
            },
            yAxis: [
                {
                    title: {
                        enabled: false
                    },
                    labels: {
                        enabled: true,
                        align: 'left',
                        x: 2,
                        y: 0,
                        formatter: function ()
                        {
                            return '' + Math.floor(this.value*priceUpperBound/32/1000) + 'k' ;
                        },
                        style: {
                            color: 'black'
                        }
                    },
                    min: priceLowerBound / priceUpperBound * 32,
                    max: 32,
                    floor: 0,
                    tickInterval: 1,
                    tickAmount: 5,
                },
                {
                    title: {
                        enabled: false,
                    },
                    labels: {
                        enabled: true,
                        align: 'right',
                        x: -2,
                        y: 0,
                        formatter: function ()
                        {
                            return this.value.toFixed(1) + '%' ;
                        },
                        style: {
                            color: 'black'
                        }
                    },
                    opposite: false,
                    min: -4,
                    max: 4,
                    tickInterval: 1,
                    tickAmount: 5,
                }
            ],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function ()
                {
                    var tr = '<b>' + Highcharts.dateFormat('%a %b %e %Y, %l:%M%P', this.x) + '</b>';
                    tr += '<br><span style="color: ' + colors.text + '">Price: ' + wowtoken.NumberCommas(hcdata.realPrices[this.x]) + 'g</span>';
                    if (hcdata.pct.hasOwnProperty(this.x)) {
                        tr += '<br><span style="color: #444">Rate: ' + (hcdata.pct[this.x] > 0 ? '+' : '') + (hcdata.pct[this.x]*100).toFixed(2) + '%/hr</span>';
                    }
                    //tr += '<br><span style="color: ' + colors.text + '">Sells in: ' + hcdata.timeleft[this.x] + '</span>';
                    return tr;
                }
            },
            plotOptions: {
                area: {
                    dataGrouping: {
                        enabled: false
                    }
                },
                series: {
                    lineWidth: 2,
                    marker: {
                        enabled: false,
                        radius: 1,
                        states: {
                            hover: {
                                enabled: true
                            }
                        }
                    },
                    turboThreshold: 0,
                }
            },
            series: [
                {
                    type: 'area',
                    name: 'Market Price',
                    color: colors.line,
                    lineColor: colors.line,
                    fillColor: colors.fill,
                    data: hcdata.buy,
                },
                {
                    type: 'line',
                    name: 'Price Change',
                    color: '#999999',
                    lineColor: '#999999',
                    data: hcdata.pctchart,
                    enableMouseTracking: false,
                    yAxis: 1,
                    dashStyle: 'Dash',
                    lineWidth: 1,
                }
            ]
        });
    }
}

$(document).ready(wowtoken.Main);
