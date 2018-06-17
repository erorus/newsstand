var TUJ_Seller = function ()
{
    var params;
    var lastResults = [];

    this.load = function (inParams)
    {
        params = {};
        for (var p in inParams) {
            if (inParams.hasOwnProperty(p)) {
                params[p] = inParams[p];
            }
        }

        var qs = {
            realm: params.realm,
            seller: params.id
        };
        var hash = JSON.stringify(qs);

        for (var x = 0; x < lastResults.length; x++) {
            if (lastResults[x].hash == hash) {
                SellerResult(false, lastResults[x].data);
                return;
            }
        }

        var sellerPage = $('#seller-page')[0];
        if (!sellerPage) {
            sellerPage = libtuj.ce();
            sellerPage.id = 'seller-page';
            sellerPage.className = 'page';
            $('#main').append(sellerPage);
        }

        $('#progress-page').show();

        var ajaxTries = 0;
        var ajaxSettings = {
            data: qs,
            success: function (d)
            {
                if (d.captcha) {
                    tuj.AskCaptcha(d.captcha);
                }
                else {
                    SellerResult(hash, d);
                }

                $('#progress-page').hide();
            },
            error: function (xhr, stat, er)
            {
                if (xhr.status == 429 && xhr.responseJSON && xhr.responseJSON.concurrent_retry) {
                    if (++ajaxTries >= 10) {
                        $('#progress-page').hide();

                        alert('Too many concurrent requests, please reload to try again.');
                    } else {
                        var delay = 2500 + Math.round(Math.random() * 1000) + 1000 * ajaxTries;
                        console.log('Other concurrent requests currently being processed, will retry after ' + delay + 'ms');

                        window.setTimeout(
                            function () {
                                $.ajax(ajaxSettings);
                            }, delay);
                    }

                    return;
                }

                $('#progress-page').hide();

                if ((xhr.status == 503) && xhr.responseJSON && xhr.responseJSON.maintenance) {
                    tuj.APIMaintenance(xhr.responseJSON.maintenance);
                } else {
                    alert('Error fetching page data: ' + xhr.status + ' ' + stat + ' ' + er);
                }
            },
            url: 'api/seller.php'
        };

        $.ajax(ajaxSettings);
    }

    function SellerResult(hash, dta)
    {
        if (hash) {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10) {
                lastResults.shift();
            }
        }

        var sellerPage = $('#seller-page');
        sellerPage.empty();
        sellerPage.show();

        if (!dta.stats) {
            $('#page-title').empty().append(document.createTextNode(tuj.lang.seller + ': ' + params.id));
            tuj.SetTitle(tuj.lang.seller + ': ' + params.id);

            var h2 = libtuj.ce('h2');
            sellerPage.append(h2);
            h2.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.notFound, tuj.lang.seller + ' ' + params.id)));

            return;
        }

        dta.auctions = libtuj.HydrateData(dta.auctions);
        dta.petAuctions = libtuj.HydrateData(dta.petAuctions);

        var r = tuj.realms[dta.stats.realm];
        var sellerLink = libtuj.ce('a');
        sellerLink.href = libtuj.sprintf('http://{1}.battle.net/wow/{2}/character/{3}/{4}/advanced',
            r.region.toLowerCase(), r.locale.substr(0, 2).toLowerCase(), r.slug, dta.stats.name);
        sellerLink.appendChild(document.createTextNode(dta.stats.name));

        var thumbnail, thumbnailLink = '';
        if (dta.stats.thumbnail) {
            thumbnail = libtuj.ce('img');
            thumbnail.src = libtuj.sprintf('https://render-{1}.worldofwarcraft.com/character/{2}',
                r.region.toLowerCase(), dta.stats.thumbnail);
            thumbnail.style.marginRight = '0.1em';
            thumbnail.style.verticalAlign = 'top';
            thumbnail.style.border = '0';
            thumbnailLink = libtuj.ce('a');
            thumbnailLink.href = sellerLink.href;
            thumbnailLink.appendChild(thumbnail);
        }

        $('#page-title').empty().append(thumbnailLink).append(document.createTextNode(tuj.lang.seller + ': ')).append(sellerLink);
        tuj.SetTitle(tuj.lang.seller + ': ' + dta.stats.name);

        var d, cht, h;

        if (dta.stats.hasOwnProperty('auctions')) {
            d = libtuj.ce();
            d.className = 'seller-stats';
            sellerPage.append(d);
            SellerStats(dta, d);
        }

        if (tuj.SellerIsBot(dta.stats.realm, dta.stats.name)) {
            d = libtuj.ce();
            d.className = 'news';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Probable Bot');
            d.appendChild(document.createTextNode('This seller is probably an auto-undercutting bot. Be aware if you decide to undercut his prices, because you will probably be undercut yourself in less than an hour. '));
            h = libtuj.ce('a');
            h.href = '/extra/multirealm.php';
            h.className = 'highlight';
            d.appendChild(h);
            $(h).text('See this page for more info.');
            sellerPage.append(d);
        }

        d = libtuj.ce();
        d.className = 'chart-section';
        h = libtuj.ce('h2');
        d.appendChild(h);
        $(h).text(tuj.lang.snapshots);
        d.appendChild(document.createTextNode(tuj.lang.snapshotsSellerDesc))
        cht = libtuj.ce();
        cht.className = 'chart history';
        d.appendChild(cht);
        sellerPage.append(d);
        SellerHistoryChart(dta, cht);

        if (dta.history.length >= 14) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.postingHeatMap);
            cht = libtuj.ce();
            cht.className = 'chart heatmap';
            d.appendChild(cht);
            sellerPage.append(d);
            SellerPostingHeatMap(dta, cht);
        }

        if (dta.byClass && dta.byClass.length > 2) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.auctionsByItemClass);
            d.appendChild(document.createTextNode(tuj.lang.auctionsByItemClassDesc))
            cht = libtuj.ce();
            cht.className = 'chart treemap';
            d.appendChild(cht);
            sellerPage.append(d);
            SellerByItemClass(dta, cht);
        }

        if (dta.auctions.length) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.currentAuctions);
            cht = libtuj.ce();
            cht.className = 'auctionlist';
            d.appendChild(cht);
            sellerPage.append(d);
            SellerAuctions(dta, cht);
        }

        if (dta.petAuctions.length) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.currentPetAuctions);
            cht = libtuj.ce();
            cht.className = 'auctionlist';
            d.appendChild(cht);
            sellerPage.append(d);
            SellerPetAuctions(dta, cht);
        }

        libtuj.Ads.Show();
    }

    function SellerStats(data, dest)
    {
        var t, tr, td, abbr;

        $(dest).empty();

        t = libtuj.ce('table');
        dest.appendChild(t);

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'auctions';
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(tuj.lang.numberOfAuctions));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatQuantity(data.stats.auctions));

        if (data.stats.auctions > 0) {
            td = libtuj.ce('td');
            td.className = 'rank';
            tr.appendChild(td);
            td.appendChild(document.createTextNode(data.stats.auctionsrank));

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'total-value';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(tuj.lang.price));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(data.stats.uservalue, false, true));
            td = libtuj.ce('td');
            td.className = 'rank';
            tr.appendChild(td);
            td.appendChild(document.createTextNode(data.stats.uservaluerank));

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'spacer';
            td = libtuj.ce('td');
            td.colSpan = 3;
            tr.appendChild(td);

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'total-value';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(tuj.lang.marketPrice));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(data.stats.marketvalue, false, true));
            td = libtuj.ce('td');
            td.className = 'rank';
            tr.appendChild(td);
            td.appendChild(document.createTextNode(data.stats.marketvaluerank));

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'total-value';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(tuj.lang.regionPrice));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(data.stats.regionmedian, false, true));
            td = libtuj.ce('td');
            td.className = 'rank';
            tr.appendChild(td);
            td.appendChild(document.createTextNode(data.stats.regionmedianrank));
        }

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'spacer';
        td = libtuj.ce('td');
        td.colSpan = 3;
        tr.appendChild(td);

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'last-seen';
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(tuj.lang.firstSeen));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatDate(data.stats.firstseen));

        if (data.stats.auctions == 0) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'last-seen';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(tuj.lang.lastSeen));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatDate(data.stats.lastseen));
        }

        dest.appendChild(libtuj.Ads.Add('9719254490', 'box'));
    }

    function SellerHistoryChart(data, dest)
    {
        var hcdata = {total: [], newAuc: [], max: 0};

        for (var x = 0; x < data.history.length; x++) {
            hcdata.total.push([data.history[x].snapshot * 1000, data.history[x].total]);
            hcdata.newAuc.push([data.history[x].snapshot * 1000, data.history[x]['new']]);
            if (data.history[x].total > hcdata.max) {
                hcdata.max = data.history[x].total;
            }
        }

        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });

        $(dest).highcharts({
            chart: {
                zoomType: 'x',
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },
            title: {
                text: null
            },
            subtitle: {
                text: document.ontouchstart === undefined ?
                    tuj.lang.zoomClickDrag :
                    tuj.lang.zoomPinch,
                style: {
                    color: tujConstants.siteColors[tuj.colorTheme].text
                }
            },
            xAxis: {
                type: 'datetime',
                maxZoom: 4 * 3600000, // four hours
                title: {
                    text: null
                },
                labels: {
                    style: {
                        color: tujConstants.siteColors[tuj.colorTheme].text
                    }
                }
            },
            yAxis: [
                {
                    title: {
                        text: tuj.lang.numberOfAuctions,
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].bluePrice
                        }
                    },
                    labels: {
                        enabled: true,
                        formatter: function ()
                        {
                            return '' + libtuj.FormatQuantity(this.value, true);
                        },
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].text
                        }
                    },
                    min: 0,
                    max: hcdata.max
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
                    tr += '<br><span style="color: #000099">' + tuj.lang.total + ': ' + libtuj.FormatQuantity(this.points[0].y, true) + '</span>';
                    tr += '<br><span style="color: #990000">' + tuj.lang['new'] + ': ' + libtuj.FormatQuantity(this.points[1] ? this.points[1].y : 0, true) + '</span>';
                    return tr;
                }
            },
            plotOptions: {
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
                    states: {
                        hover: {
                            lineWidth: 2
                        }
                    }
                }
            },
            series: [
                {
                    type: 'area',
                    name: tuj.lang.total,
                    color: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    lineColor: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    fillColor: tujConstants.siteColors[tuj.colorTheme].bluePriceFill,
                    data: hcdata.total
                },
                {
                    type: 'line',
                    name: tuj.lang['new'],
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantity,
                    data: hcdata.newAuc
                }
            ]
        });
    }

    function SellerPostingHeatMap(data, dest)
    {
        var hcdata = {minVal: undefined, maxVal: 0, days: {}, heat: [], categories: {
            x: tuj.lang.heatMapHours,
            y: tuj.lang.heatMapDays
        }};

        var CalcAvg = function (a)
        {
            if (a.length == 0) {
                return null;
            }
            var s = 0;
            for (var x = 0; x < a.length; x++) {
                s += a[x];
            }
            return s / a.length;
        }

        var d, wkdy, hr;
        for (wkdy = 0; wkdy < hcdata.categories.y.length; wkdy++) {
            hcdata.days[wkdy] = {};
            for (hr = 0; hr < hcdata.categories.x.length; hr++) {
                hcdata.days[wkdy][hr] = [];
            }
        }

        for (var x = 0; x < data.history.length; x++) {
            var d = new Date(data.history[x].snapshot * 1000);
            wkdy = 6 - d.getDay();
            hr = Math.floor(d.getHours() * hcdata.categories.x.length / 24);
            hcdata.days[wkdy][hr].push(data.history[x]['new']);
        }

        var p;
        for (wkdy = 0; wkdy < hcdata.categories.y.length; wkdy++) {
            for (hr = 0; hr < hcdata.categories.x.length; hr++) {
                if (hcdata.days[wkdy][hr].length == 0) {
                    p = 0;
                }
                else {
                    p = Math.round(CalcAvg(hcdata.days[wkdy][hr]));
                }

                hcdata.heat.push([hr, wkdy, p]);
                hcdata.minVal = (typeof hcdata.minVal == 'undefined' || hcdata.minVal > p) ? p : hcdata.minVal;
                hcdata.maxVal = hcdata.maxVal < p ? p : hcdata.maxVal;
            }
        }

        $(dest).highcharts({

            chart: {
                type: 'heatmap',
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },

            title: {
                text: null
            },

            xAxis: {
                categories: hcdata.categories.x,
                labels: {
                    style: {
                        color: tujConstants.siteColors[tuj.colorTheme].text
                    }
                }
            },

            yAxis: {
                categories: hcdata.categories.y,
                title: null,
                labels: {
                    style: {
                        color: tujConstants.siteColors[tuj.colorTheme].text
                    }
                }
            },

            colorAxis: {
                min: hcdata.minVal,
                max: hcdata.maxVal,
                minColor: tujConstants.siteColors[tuj.colorTheme].background,
                maxColor: tujConstants.siteColors[tuj.colorTheme].bluePriceBackground
            },

            legend: {
                align: 'right',
                layout: 'vertical',
                margin: 0,
                verticalAlign: 'top',
                y: 25,
                symbolHeight: 320
            },

            tooltip: {
                enabled: false
            },

            series: [
                {
                    name: tuj.lang.newAuctions,
                    borderWidth: 1,
                    borderColor: tujConstants.siteColors[tuj.colorTheme].background,
                    data: hcdata.heat,
                    dataLabels: {
                        enabled: true,
                        color: tujConstants.siteColors[tuj.colorTheme].data,
                        style: {
                            textShadow: 'none',
                            HcTextStroke: null
                        },
                        formatter: function ()
                        {
                            return '' + libtuj.FormatQuantity(this.point.value, true);
                        }
                    }
                }
            ]

        });
    }

    function SellerByItemClass(data, dest)
    {
        var hcdata = {
            data: [],
            classLookup: {},
            totalAucs: 0,
            classCount: {},
            totalClasses: 0,
        };

        for (var i = 0, row; row = data.byClass[i]; i++) {
            if (!hcdata.classCount.hasOwnProperty(row['class'])) {
                hcdata.classCount[row['class']] = 0;
                hcdata.totalClasses++;
            }
            hcdata.classCount[row['class']] += row.aucs;
        }

        data.byClass.sort(function(a,b) {
            return hcdata.classCount[b['class']] - hcdata.classCount[a['class']]
                || b.aucs - a.aucs;
        });

        var classesSeen = 0;
        if (hcdata.totalClasses > 4) {
            classesSeen = -1 * Math.floor(hcdata.totalClasses / 4);
        }

        for (i = 0, row; row = data.byClass[i]; i++) {
            if (!hcdata.classLookup.hasOwnProperty(row['class'])) {
                hcdata.classLookup[row['class']] = true;
                hcdata.data.push({
                    id: 'c' + row['class'],
                    name: tuj.lang.itemClasses[row['class']],
                    color: Highcharts.Color(tujConstants.siteColors[tuj.colorTheme].redQuantityBackground).brighten(classesSeen++ / (hcdata.totalClasses * 1.5)).get(),
                });
            }
            hcdata.data.push({
                name: tuj.lang.itemSubClasses[''+row['class']+'-'+row['subclass']],
                parent: 'c' + row['class'],
                value: row.aucs
            });
            hcdata.totalAucs += row.aucs;
        }

        $(dest).highcharts({
            chart: {
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },

            title: {
                text: null
            },

            yAxis: {
                title: null,
            },

            tooltip: {
                formatter: function () {
                    var className = '';
                    if (this.point.parent) {
                        var classId = this.point.parent.substr(1);
                        className = tuj.lang.itemClasses[classId] + ' - ';
                    }

                    var tr = '<b>' + className + this.point.name + '</b>';
                    tr += '<br>' + tuj.lang.numberOfAuctions + ': <b>' + this.point.node.val + '</b>';
                    if (this.point.parent) {
                        tr += '<br><b>' + Math.round(this.point.node.val / hcdata.classCount[classId] * 100) + '%</b> \u2286 ' + tuj.lang.itemClasses[classId];
                    }
                    tr += '<br><b>' + Math.round(this.point.node.val / hcdata.totalAucs * 100) + '%</b> \u2286 ' + tuj.lang.all;
                    return tr;
                }
            },

            plotOptions: {
                pie: {
                    shadow: false,
                    center: ['50%','100%'],
                    startAngle: -90,
                    endAngle: 90,
                }
            },

            series: [{
                type: 'treemap',
                layoutAlgorithm: 'squarified',
                alternateStartingDirection: true,
                allowDrillToNode: true,
                levels: [{
                    level: 1,
                    layoutAlgorithm: 'stripes',
                    dataLabels: {
                        enabled: true,
                        align: 'left',
                        verticalAlign: 'top',
                    }
                }],
                data: hcdata.data,
            }]
        });
    }

    function SellerAuctions(data, dest)
    {
        var t, tr, td;
        t = libtuj.ce('table');
        t.className = 'auctionlist';

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'quantity';
        $(td).text(tuj.lang.quantity);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'name';
        td.colSpan = 2;
        $(td).text(tuj.lang.item);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'price';
        $(td).text(tuj.lang.bidEach);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'price';
        $(td).text(tuj.lang.buyoutEach);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'quantity';
        $(td).text(tuj.lang.cheaper);

        data.auctions.sort(function (a, b)
        {
            return tujConstants.itemClassOrder[a['class']] - tujConstants.itemClassOrder[b['class']] ||
                (a['name_' + tuj.locale] ? 0 : -1) ||
                (b['name_' + tuj.locale] ? 0 : 1) ||
                a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]) ||
                a.level - b.level ||
                Math.floor(a.buy / a.quantity) - Math.floor(b.buy / b.quantity) ||
                Math.floor(a.bid / a.quantity) - Math.floor(b.bid / b.quantity) ||
                a.quantity - b.quantity;
        });

        libtuj.TableSort.Make(t);

        var s, a, stackable, i;
        for (var x = 0, auc; auc = data.auctions[x]; x++) {
            stackable = auc.stacksize > 1;

            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'quantity';
            td.appendChild(libtuj.FormatQuantity(auc.quantity));

            td = libtuj.ce('td');
            td.className = 'icon';
            tr.appendChild(td);
            i = libtuj.ce('img');
            td.appendChild(i);
            i.className = 'icon';
            i.src = libtuj.IconURL(auc.icon, 'medium');

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'name';
            a = libtuj.ce('a');
            a.rel = 'item=' + auc.item + (auc.rand ? '&rand=' + auc.rand : '') + (auc.bonuses ? '&bonus=' + auc.bonuses : '') + (auc.lootedlevel ? '&lvl=' + auc.lootedlevel : '') + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
            a.href = tuj.BuildHash({page: 'item', id: auc.item + (auc.level && auc.baselevel != auc.level ? '.' + auc.level : '')});
            td.appendChild(a);
            $(a).text('[' + auc['name_' + tuj.locale] + (auc['bonusname_' + tuj.locale] ? ' ' + auc['bonusname_' + tuj.locale].substr(0, auc['bonusname_' + tuj.locale].indexOf('|') >= 0 ? auc['bonusname_' + tuj.locale].indexOf('|') : auc['bonusname_' + tuj.locale].length) : '') + (auc['randname_' + tuj.locale] ? ' ' + auc['randname_' + tuj.locale] : '') + ']');
            if (auc.level && auc.level != auc.baselevel) {
                var s = libtuj.ce('span');
                s.className = 'level';
                s.appendChild(document.createTextNode(auc.level));
                a.appendChild(s);
                if (!auc.bonuses) {
                    a.rel += '&bonus=' + libtuj.LevelOffsetBonus(auc.level - auc.baselevel);
                }
            }
            $(a).data('sort', a.textContent);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatFullPrice(auc.bid / auc.quantity);
            if (stackable && auc.quantity > 1) {
                a = libtuj.ce('abbr');
                a.title = libtuj.FormatFullPrice(auc.bid, true) + ' ' + tuj.lang.total;
                a.appendChild(s);
            }
            else {
                a = s;
            }
            td.appendChild(a);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatFullPrice(auc.buy / auc.quantity);
            if (stackable && auc.quantity > 1 && auc.buy) {
                a = libtuj.ce('abbr');
                a.title = libtuj.FormatFullPrice(auc.buy, true) + ' ' + tuj.lang.total;
                a.appendChild(s);
            }
            else {
                if (!auc.buy) {
                    a = libtuj.ce('span');
                }
                else {
                    a = s;
                }
            }
            if (a) {
                td.appendChild(a);
            }

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'quantity';
            if (auc.cheaper) {
                td.appendChild(libtuj.FormatQuantity(auc.cheaper));
            } else {
                s = libtuj.ce('span');
                $(s).data('sort', 0);
                td.appendChild(s);
            }
        }

        dest.appendChild(t);
    }

    function SellerPetAuctions(data, dest)
    {
        var t, tr, td;
        t = libtuj.ce('table');
        t.className = 'auctionlist';

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'name';
        td.colSpan = 2;
        $(td).text(tuj.lang.species);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'breed';
        $(td).text(tuj.lang.breed);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'quality';
        $(td).text(tuj.lang.quality);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'level';
        $(td).text(tuj.lang.level);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'price';
        $(td).text(tuj.lang.bidEach);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'price';
        $(td).text(tuj.lang.buyoutEach);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'quantity';
        $(td).text(tuj.lang.cheaper);

        libtuj.TableSort.Make(t);

        data.petAuctions.sort(function (a, b)
        {
            return a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]) ||
                tuj.lang.breedsLookup[a.breed].localeCompare(tuj.lang.breedsLookup[b.breed]) ||
                a.quality - b.quality ||
                a.buy - b.buy ||
                a.bid - b.bid;
        });

        var s, a, i;
        for (var x = 0, auc; auc = data.petAuctions[x]; x++) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('td');
            td.className = 'icon';
            tr.appendChild(td);
            i = libtuj.ce('img');
            td.appendChild(i);
            i.className = 'icon';
            i.src = libtuj.IconURL(auc.icon, 'medium');

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'name';
            a = libtuj.ce('a');
            a.rel = 'npc=' + auc.npc + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
            a.href = tuj.BuildHash({page: 'battlepet', id: auc.species});
            td.appendChild(a);
            $(a).text('[' + auc['name_' + tuj.locale] + ']');
            $(a).data('sort', a.textContent);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'breed';
            td.appendChild(document.createTextNode(tuj.lang.breedsLookup[auc.breed]));

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'quality';
            td.appendChild(document.createTextNode(tuj.lang.qualities[auc.quality]));

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'level';
            td.appendChild(document.createTextNode(auc.level));

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatFullPrice(auc.bid / auc.quantity);
            td.appendChild(s);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatFullPrice(auc.buy / auc.quantity);
            if (!auc.buy) {
                a = libtuj.ce('span');
            }
            else {
                a = s;
            }
            if (a) {
                td.appendChild(a);
            }

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'quantity';
            if (auc.cheaper) {
                td.appendChild(libtuj.FormatQuantity(auc.cheaper));
            } else {
                s = libtuj.ce('span');
                $(s).data('sort', 0);
                td.appendChild(s);
            }
        }

        dest.appendChild(t);
    }

    this.load(tuj.params);
}

tuj.page_seller = new TUJ_Seller();
