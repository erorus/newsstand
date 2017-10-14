var TUJ_Item = function ()
{
    var params;
    var lastResults = [];
    var itemId;
    var levels;
    var level;

    this.load = function (inParams)
    {
        params = {};
        for (var p in inParams) {
            if (inParams.hasOwnProperty(p)) {
                params[p] = inParams[p];
            }
        }

        itemId = '' + params.id;

        if (itemId.indexOf('.') > 0) {
            itemId = ('' + params.id).substr(0, ('' + params.id).indexOf('.'));
        }

        var qs = {
            house: tuj.realms[params.realm].house,
            item: itemId
        };
        var hash = JSON.stringify(qs);

        for (var x = 0; x < lastResults.length; x++) {
            if (lastResults[x].hash == hash) {
                ItemResult(false, lastResults[x].data);
                return;
            }
        }

        var itemPage = $('#item-page')[0];
        if (!itemPage) {
            itemPage = libtuj.ce();
            itemPage.id = 'item-page';
            itemPage.className = 'page';
            $('#main').append(itemPage);
        }

        $('#progress-page').show();

        $.ajax({
            data: qs,
            success: function (d)
            {
                if (d.captcha) {
                    tuj.AskCaptcha(d.captcha);
                }
                else {
                    ItemResult(hash, d);
                }
            },
            error: function (xhr, stat, er)
            {
                if ((xhr.status == 503) && xhr.hasOwnProperty('responseJSON') && xhr.responseJSON && xhr.responseJSON.hasOwnProperty('maintenance')) {
                    tuj.APIMaintenance(xhr.responseJSON.maintenance);
                } else {
                    alert('Error fetching page data: ' + stat + ' ' + er);
                }
            },
            complete: function ()
            {
                $('#progress-page').hide();
            },
            url: 'api/item.php'
        });
    };

    function ItemResult(hash, dta)
    {
        if (hash) {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10) {
                lastResults.shift();
            }
        }

        var itemPage = $('#item-page');
        itemPage.empty();
        itemPage.show();

        if (!dta.stats) {
            $('#page-title').empty().append(document.createTextNode(tuj.lang.item + ': ' + itemId));
            tuj.SetTitle(tuj.lang.item + ': ' + itemId);

            var h2 = libtuj.ce('h2');
            itemPage.append(h2);
            h2.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.notFound, tuj.lang.item + ' ' + itemId)));

            return;
        }

        levels = [];
        level = false;

        for (var lvl in dta.stats) {
            if (!dta.stats.hasOwnProperty(lvl)) {
                continue;
            }
            levels.push(parseInt(lvl,10));
            if (level === false) {
                level = lvl;
            }
        }
        levels.sort();

        var fullItemName = '[' + dta.stats[level]['name_' + tuj.locale] + ']';

        var ta = libtuj.ce('a');
        ta.href = 'http://' + tuj.lang.wowheadDomain + '.wowhead.com/item=' + itemId;
        ta.target = '_blank';
        ta.rel = 'noopener noreferrer';
        ta.className = 'item';
        var timg = libtuj.ce('img');
        ta.appendChild(timg);
        timg.src = libtuj.IconURL(dta.stats[level].icon, 'large');
        ta.appendChild(document.createTextNode(fullItemName));

        $('#page-title').empty().append(ta);
        tuj.SetTitle(fullItemName);

        var d, cht, h;

        d = libtuj.ce();
        d.className = 'item-stats';
        itemPage.append(d);
        ItemStats(dta, d);

        if (dta.history.hasOwnProperty(level) && dta.history[level].length >= 4) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.snapshots);
            d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.snapshotsDesc, tuj.lang.item, tuj.validRegions[params.region] + ' ' + tuj.realms[params.realm].name)));
            cht = libtuj.ce();
            cht.className = 'chart history';
            d.appendChild(cht);
            itemPage.append(d);
            ItemHistoryChart(dta, cht);
        }

        if (dta.monthly.hasOwnProperty(level) && dta.monthly[level].length >= 14) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.dailySummary);
            d.appendChild(document.createTextNode(tuj.lang.dailySummaryDesc));
            cht = libtuj.ce();
            cht.className = 'chart monthly';
            d.appendChild(cht);
            itemPage.append(d);
            ItemMonthlyChart(dta, cht);
        }

        if (dta.daily.length >= 14) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.dailyDetails);
            d.appendChild(document.createTextNode(tuj.lang.dailyDetailsDesc));
            cht = libtuj.ce();
            cht.className = 'chart daily';
            d.appendChild(cht)
            itemPage.append(d);
            ItemDailyChart(dta, cht);
        }

        if (dta.hasOwnProperty('expired') && dta.expired.hasOwnProperty(level) && dta.expired[level].length >= 7) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.auctionCount);
            d.appendChild(document.createTextNode(tuj.lang.auctionCountDesc));
            cht = libtuj.ce();
            cht.className = 'chart monthly';
            d.appendChild(cht);
            itemPage.append(d);
            ItemExpiredChart(dta, cht);
        }

        if (dta.history.hasOwnProperty(level) && dta.history[level].length >= 14) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.pricingHeatMap);
            d.appendChild(document.createTextNode(tuj.lang.pricingHeatMapDesc));
            cht = libtuj.ce();
            cht.className = 'chart heatmap priceheatmap';
            d.appendChild(cht);
            itemPage.append(d);
            ItemPriceHeatMap(dta, cht);

            var doHeatMap = false;
            for (var x = 0; !doHeatMap && (x < dta.history[level].length); x++) {
                doHeatMap |= !!dta.history[level][x].quantity;
            }
            if (doHeatMap) {
                d = libtuj.ce();
                d.className = 'chart-section';
                h = libtuj.ce('h2');
                d.appendChild(h);
                $(h).text(tuj.lang.quantityHeatMap);
                d.appendChild(document.createTextNode(tuj.lang.quantityHeatMapDesc));
                cht = libtuj.ce();
                cht.className = 'chart heatmap';
                d.appendChild(cht);
                itemPage.append(d);
                ItemQuantityHeatMap(dta, cht);
            }
        }

        itemPage.append(libtuj.Ads.Add('3753400314'));

        if (dta.globalmonthly.hasOwnProperty(level) && dta.globalmonthly[level].length >= 28) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.regionalDailySummary);
            d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.regionalDailySummaryDesc, tuj.validRegions[params.region])));
            cht = libtuj.ce();
            cht.className = 'chart monthly';
            d.appendChild(cht);
            itemPage.append(d);
            ItemGlobalMonthlyChart(dta, cht);
        }

        if (dta.globalnow.hasOwnProperty(level) && dta.globalnow[level].length > 0) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.regionalPrices);
            d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.regionalPricesDesc, tuj.lang.item, tuj.validRegions[params.region])));
            cht = libtuj.ce();
            cht.className = 'chart columns';
            d.appendChild(cht);
            itemPage.append(d);
            ItemGlobalNowColumns(dta, cht);

            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.pricePopScatter);

            var a = libtuj.ce('a');
            a.href = 'https://realmpop.com/' + tuj.validRegions[params.region].toLowerCase() + '.html';
            a.style.textDecoration = 'underline';
            a.appendChild(document.createTextNode('Realm Pop'));
            $(d).append(libtuj.sprintf(tuj.lang.pricePopScatterDesc, a.outerHTML));

            cht = libtuj.ce();
            cht.className = 'chart scatter';
            d.appendChild(cht);
            itemPage.append(d);
            ItemGlobalNowScatter(dta, cht);
        }

        if (dta.hasOwnProperty('sellers') && dta.sellers.length > 2) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.recentSellers);
            d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.recentSellersDesc, '[' + dta.stats[level]['name_' + tuj.locale] + ']')));
            cht = libtuj.ce();
            cht.className = 'chart columns';
            d.appendChild(cht);
            itemPage.append(d);
            ItemRecentSellersColumns(dta, cht);
        }

        itemPage.append(MakeNotificationsSection(dta, fullItemName));

        dta.auctions = libtuj.HydrateData(dta.auctions);

        if (dta.auctions.length) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);

            var a = libtuj.ce('a');
            h.appendChild(a);
            a.href = 'https://' + tuj.validRegions[params.region].toLowerCase() + '.battle.net/wow/en/vault/character/auction/browse?sort=unitBuyout&itemId=' + itemId + '&start=0&end=40';
            $(a).text(tuj.lang.currentAuctions);
            d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.currentAuctionsDesc, tuj.lang.item)));
            d.appendChild(libtuj.ce('br'));
            d.appendChild(libtuj.ce('br'));

            cht = libtuj.ce();
            cht.className = 'auctionlist';
            d.appendChild(cht);
            itemPage.append(d);
            ItemAuctions(dta, cht);
        }

        libtuj.Ads.Show();
    }

    function MakeNotificationsSection(data, fullItemName)
    {
        var d = libtuj.ce();
        d.className = 'chart-section';
        var h = libtuj.ce('h2');
        d.appendChild(h);
        $(h).text(tuj.lang.marketNotifications);
        if (tuj.LoggedInUserName()) {
            d.style.display = 'none';
            d.appendChild(document.createTextNode(tuj.lang.marketNotificationsDesc));
            var cht = libtuj.ce();
            cht.className = 'notifications-insert';
            d.appendChild(cht);
            GetItemNotificationsList(itemId, levels.length > 1 ? level : -1, d);
        } else {
            d.className += ' logged-out-only';

            var globalQty = 0;
            if (data.globalnow.hasOwnProperty(level) && data.globalnow[level].length) {
                for (var x = 0, row; row = data.globalnow[level][x]; x++) {
                    globalQty += row.quantity;
                }
            }

            if (globalQty < 200) {
                d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.wantToKnowAvailAnywhere, fullItemName) + ' '));
            } else if (data.stats.hasOwnProperty(level)) {
                if (data.stats[level].quantity < 5) {
                    d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.wantToKnowAvail, fullItemName) + ' '));
                } else if (data.history.hasOwnProperty(level) && data.history[level].length > 8) {
                    var prices = [];
                    for (var x = 0; x < data.history[level].length; x++) {
                        prices.push(data.history[level][x].silver * 100);
                    }
                    var priceMean = libtuj.Mean(prices);
                    var priceStdDev = libtuj.StdDev(prices, priceMean);

                    if (priceMean > 10000 && priceMean > (priceStdDev / 2)) {
                        d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.wantToKnowPrice, fullItemName, libtuj.FormatPrice(priceMean - (priceStdDev / 2), true, true)) + ' '));
                    }
                }
            }

            var a = libtuj.ce('a');
            a.href = tuj.BuildHash({'page': 'subscription', 'id': undefined});
            a.className = 'highlight';
            a.appendChild(document.createTextNode(tuj.lang.logInToFreeSub));
            d.appendChild(a);
        }

        return d;
    }

    function ItemLevelChange(sel, data, statsDest)
    {
        level = sel.options[sel.selectedIndex].value;
        ItemStats(data, statsDest);

        var priceHeatMapDest = document.getElementsByClassName('priceheatmap');
        if (priceHeatMapDest.length) {
            ItemPriceHeatMap(data, priceHeatMapDest[0]);
        }
    }

    function ItemStats(data, dest)
    {
        var t, tr, td, abbr;

        var stack = data.stats[level].stacksize > 1 ? data.stats[level].stacksize : 0;
        var spacerColSpan = stack ? 3 : 2;

        stack = 0; // disable stack size since they're an unusable "200"

        $(dest).empty();

        t = libtuj.ce('table');
        dest.appendChild(t);

        if (stack) {
            t.className = 'with-stack';
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'stack-header';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(tuj.lang.one));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.style.whiteSpace = 'nowrap';
            td.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.stackof, stack)));
        }

        if (levels.length > 1) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'level-select';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(tuj.lang.level));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.colSpan = stack ? 2 : 1;
            var sel = libtuj.ce('select');
            $(sel).on('change', ItemLevelChange.bind(this, sel, data, dest));
            for (var l, x=0; l = levels[x]; x++) {
                var opt = libtuj.ce('option');
                opt.value = l;
                opt.label = l;
                if (l == level) {
                    opt.selected = true;
                }
                opt.appendChild(document.createTextNode(l));
                sel.appendChild(opt);
            }
            td.appendChild(sel);

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'spacer';
            td = libtuj.ce('td');
            td.colSpan = spacerColSpan;
            tr.appendChild(td);
        }

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'available';
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(tuj.lang.availableQuantity));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatQuantity(data.stats[level].quantity));
        if (stack) {
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatQuantity(Math.floor(data.stats[level].quantity / stack)));
        }

        if (data.stats[level].quantity == 0) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'last-seen';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(tuj.lang.lastSeen));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.colSpan = stack ? 2 : 1;
            td.appendChild(libtuj.FormatDate(data.stats[level].lastseen));
        }

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'spacer';
        td = libtuj.ce('td');
        td.colSpan = spacerColSpan;
        tr.appendChild(td);

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'current-price';
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(tuj.lang.currentPrice));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatPrice(data.stats[level].price));
        if (stack) {
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(data.stats[level].price * stack));
        }

        var prices = [], x;

        if (data.history.hasOwnProperty(level) && data.history[level].length > 8) {
            for (x = 0; x < data.history[level].length; x++) {
                prices.push(data.history[level][x].silver * 100);
            }
        }

        if (prices.length) {
            var median;
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'median-price';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(tuj.lang.medianPrice));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(median = libtuj.Median(prices)));
            if (stack) {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(median * stack));
            }

            var mn = libtuj.Mean(prices);
            var std = libtuj.StdDev(prices, mn);
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'mean-price';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(tuj.lang.meanPrice));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(mn));
            if (stack) {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(mn * stack));
            }

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'standard-deviation';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(tuj.lang.standardDeviation));
            td = libtuj.ce('td');
            tr.appendChild(td);
            if (std / mn > 0.33) {
                abbr = libtuj.ce('abbr');
                abbr.title = tuj.lang.volatilePrice;
                abbr.style.fontSize = '80%';
                abbr.appendChild(document.createTextNode('(!)'));
                td.appendChild(abbr);
                td.appendChild(document.createTextNode(' '));
            }
            td.appendChild(libtuj.FormatPrice(std));
            if (stack) {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(std * stack));
            }

            if (data.stats[level].hasOwnProperty('reagentprice') && data.stats[level].reagentprice) {
                tr = libtuj.ce('tr');
                t.appendChild(tr);
                tr.className = 'spacer';
                td = libtuj.ce('td');
                td.colSpan = spacerColSpan;
                tr.appendChild(td);

                tr = libtuj.ce('tr');
                t.appendChild(tr);
                tr.className = 'reagent-price';
                td = libtuj.ce('th');
                tr.appendChild(td);
                td.appendChild(document.createTextNode(tuj.lang.craftingCost));
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(data.stats[level].reagentprice));
                if (stack) {
                    td = libtuj.ce('td');
                    tr.appendChild(td);
                    td.appendChild(libtuj.FormatPrice(data.stats[level].reagentprice * stack));
                }
            }
        }

        if (data.globalnow.hasOwnProperty(level) && data.globalnow[level].length) {
            var globalStats = {
                quantity: 0,
                prices: [],
                lastseen: 0
            };

            var headerPrefix = tuj.validRegions[params.region] + ' ';
            var row;
            for (x = 0; row = data.globalnow[level][x]; x++) {
                globalStats.quantity += row.quantity;
                globalStats.prices.push(row.price);
                globalStats.lastseen = (globalStats.lastseen < row.lastseen) ? row.lastseen : globalStats.lastseen;
            }

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'spacer';
            td = libtuj.ce('td');
            td.colSpan = spacerColSpan;
            tr.appendChild(td);

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'available';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(headerPrefix + tuj.lang.quantity));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatQuantity(globalStats.quantity));
            if (stack) {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatQuantity(Math.floor(globalStats.quantity / stack)));
            }

            var median;
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'median-price';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(headerPrefix + tuj.lang.medianPrice));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(median = libtuj.Median(globalStats.prices)));
            if (stack) {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(median * stack));
            }

            var mn = libtuj.Mean(globalStats.prices);
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'mean-price';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(headerPrefix + tuj.lang.meanPrice));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(mn));
            if (stack) {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(mn * stack));
            }
        }

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'spacer';
        td = libtuj.ce('td');
        td.colSpan = spacerColSpan;
        tr.appendChild(td);

        if (data.stats[level].vendorprice) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'vendor';
            td = libtuj.ce('th');
            tr.appendChild(td);
            if (data.stats[level].vendornpccount) {
                var a = libtuj.ce('a');
                a.href = 'http://' + tuj.lang.wowheadDomain + '.wowhead.com/item=' + data.stats[level].id + '#sold-by';
                a.rel = 'np';
                if (data.stats[level].vendornpccount == 1) {
                    a.appendChild(document.createTextNode(tuj.lang.soldByVendor));
                } else {
                    a.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.soldByVendorPlural, data.stats[level].vendornpccount)));
                }
                td.appendChild(a);
            } else {
                td.appendChild(document.createTextNode(tuj.lang.soldByVendor));
            }
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(data.stats[level].vendorprice));
            if (stack) {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(data.stats[level].vendorprice * stack));
            }
        }

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'vendor';
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(tuj.lang.sellToVendor));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(data.stats[level].selltovendor ? libtuj.FormatPrice(data.stats[level].selltovendor) : document.createTextNode(tuj.lang.cannot));
        if (stack) {
            if (data.stats[level].selltovendor) {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(data.stats[level].selltovendor * stack));
            }
            else {
                td.colSpan = 2;
            }
        }

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'listing';
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(tuj.lang.listingFee));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatPrice(Math.max(100, data.stats[level].selltovendor ? data.stats[level].selltovendor * 0.6 : 0)));
        if (stack) {
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(Math.max(100, data.stats[level].selltovendor ? data.stats[level].selltovendor * 0.6 * stack : 0)));
        }

        var showThumb = false;
        switch (data.stats[level].classid) {
            case 2:
            case 4:
                showThumb = true;
                break;
            default:
                showThumb = false;
        }
        if (showThumb && data.stats[level].hasOwnProperty('display') && data.stats[level].display) {
            var i = libtuj.ce();
            i.className = 'transmog-img';
            i.style.backgroundImage = 'url(' + tujCDNPrefix + 'models/' + data.stats[level].display + '.png)';
            dest.appendChild(i);
        }

        dest.appendChild(libtuj.Ads.Add('9943194718', 'box'));
    }

    function GetItemNotificationsList(itemId, lvl, mainDiv)
    {
        var self = this;
        tuj.SendCSRFProtectedRequest({
            data: {'getitem': itemId},
            success: ItemNotificationsList.bind(self, itemId, lvl, mainDiv),
        });
    }

    function ItemNotificationsList(itemId, lvl, mainDiv, dta)
    {
        var dest = $(mainDiv).find('.notifications-insert');
        dest.empty();
        dest = dest[0];

        var ids = [];
        for (var k in dta.watches) {
            if (dta.watches.hasOwnProperty(k)) {
                if (lvl == -1 || dta.watches[k].level === null || lvl == dta.watches[k].level) {
                    ids.push(k);
                }
            }
        }
        if (ids.length) {
            // show current notifications
            var ul = libtuj.ce('ul');
            dest.appendChild(ul);

            for (var kx = 0, k; k = ids[kx]; kx++) {
                var li = libtuj.ce('li');
                ul.appendChild(li);

                var n = dta.watches[k];

                var btn = libtuj.ce('input');
                btn.type = 'button';
                btn.value = tuj.lang.delete;
                $(btn).on('click', ItemNotificationsDel.bind(btn, mainDiv, itemId, lvl, n.seq));
                li.appendChild(btn);

                if (n.house) {
                    li.appendChild(document.createTextNode(libtuj.GetRealmsForHouse(n.house) + ': '));
                } else if (n.region) {
                    li.appendChild(document.createTextNode(tuj.lang['realms' + n.region] + ': '));
                }
                if (n.price === null) {
                    li.appendChild(document.createTextNode(tuj.lang.availableQuantity + ' '));
                } else {
                    if (n.quantity === null) {
                        li.appendChild(document.createTextNode(tuj.lang.marketPrice + ' '));
                    } else {
                        li.appendChild(document.createTextNode(tuj.lang.priceToBuy + ' ' + n.quantity + ' '));
                    }
                }
                li.appendChild(document.createTextNode((n.direction == 'Over' ? tuj.lang.over : tuj.lang.under) + ' '));
                if (n.price === null) {
                    li.appendChild(document.createTextNode(n.quantity));
                } else {
                    li.appendChild(libtuj.FormatPrice(n.price));
                }
            }
        }

        if (ids.length >= dta.maximum) {
            $(mainDiv).show();
            return;
        }

        // add new notifications
        var newNotif = libtuj.ce('div');
        newNotif.className = 'notifications-add';
        dest.appendChild(newNotif);

        newNotif.appendChild(document.createTextNode(tuj.lang.notifyMeWhen));

        var regionBox = libtuj.ce('select');
        opt = libtuj.ce('option');
        opt.value = 'house';
        opt.label = tuj.validRegions[params.region] + ' ' + tuj.realms[params.realm].name;
        opt.appendChild(document.createTextNode(tuj.validRegions[params.region] + ' ' + tuj.realms[params.realm].name));
        regionBox.appendChild(opt);
        opt = libtuj.ce('option');
        opt.value = 'region';
        opt.label = tuj.lang['realms' + tuj.validRegions[params.region]];
        opt.appendChild(document.createTextNode(tuj.lang['realms' + tuj.validRegions[params.region]]));
        regionBox.appendChild(opt);
        newNotif.appendChild(regionBox);

        var selBox = libtuj.ce('select');
        var opt, optionList = [tuj.lang.availableQuantity, tuj.lang.marketPrice, tuj.lang.priceToBuy];
        for (var x = 0; x < optionList.length; x++) {
            opt = libtuj.ce('option');
            opt.value = x;
            opt.label = optionList[x];
            opt.appendChild(document.createTextNode(optionList[x]));
            selBox.appendChild(opt);
        }
        $(selBox).on('change', ItemNotificationsTypeChange);
        newNotif.appendChild(selBox);

        // available quantity
        var d = libtuj.ce('span');
        d.className = 'notification-type-form notification-type-form-0';
        newNotif.appendChild(d);

        var underOver = libtuj.ce('select');
        opt = libtuj.ce('option');
        opt.value = 'Over';
        opt.label = tuj.lang.over;
        opt.appendChild(document.createTextNode(tuj.lang.over));
        underOver.appendChild(opt);
        opt = libtuj.ce('option');
        opt.value = 'Under';
        opt.label = tuj.lang.under;
        opt.appendChild(document.createTextNode(tuj.lang.under));
        underOver.appendChild(opt);
        d.appendChild(underOver);

        var qty = libtuj.ce('input');
        qty.className = 'input-quantity';
        qty.type = 'number';
        qty.min = 0;
        qty.max = 65000;
        qty.value = 0;
        qty.maxLength = 5;
        qty.size = "8";
        qty.autocomplete = 'off';
        d.appendChild(qty);

        var btn = libtuj.ce('input');
        btn.type = 'button';
        btn.value = tuj.lang.add;
        $(btn).on('click', ItemNotificationsAdd.bind(btn, mainDiv, itemId, lvl, regionBox, underOver, qty, false));
        d.appendChild(btn);

        // market price
        var d = libtuj.ce('span');
        d.className = 'notification-type-form notification-type-form-1';
        d.style.display = 'none';
        newNotif.appendChild(d);

        var underOver = libtuj.ce('select');
        opt = libtuj.ce('option');
        opt.value = 'Under';
        opt.label = tuj.lang.under;
        opt.appendChild(document.createTextNode(tuj.lang.under));
        underOver.appendChild(opt);
        opt = libtuj.ce('option');
        opt.value = 'Over';
        opt.label = tuj.lang.over;
        opt.appendChild(document.createTextNode(tuj.lang.over));
        underOver.appendChild(opt);
        d.appendChild(underOver);

        var price = libtuj.ce('input');
        price.className = 'input-price';
        price.type = 'number';
        price.min = 0;
        price.max = 999999;
        price.value = 0;
        price.maxLength = 6;
        price.size = "10";
        price.autocomplete = 'off';
        d.appendChild(price);

        var s = libtuj.ce('span');
        s.className = 'input-price-unit';
        s.appendChild(document.createTextNode(tuj.lang.suffixGold));
        d.appendChild(s);

        var btn = libtuj.ce('input');
        btn.type = 'button';
        btn.value = tuj.lang.add;
        $(btn).on('click', ItemNotificationsAdd.bind(btn, mainDiv, itemId, lvl, regionBox, underOver, null, price));
        d.appendChild(btn);

        // price to buy X
        var d = libtuj.ce('span');
        d.className = 'notification-type-form notification-type-form-2';
        d.style.display = 'none';
        newNotif.appendChild(d);

        var qty = libtuj.ce('input');
        qty.className = 'input-quantity';
        qty.type = 'number';
        qty.min = 0;
        qty.max = 65000;
        qty.value = 0;
        qty.maxLength = 5;
        qty.size = "8";
        qty.autocomplete = 'off';
        d.appendChild(qty);

        var underOver = libtuj.ce('select');
        opt = libtuj.ce('option');
        opt.value = 'Under';
        opt.label = tuj.lang.under;
        opt.appendChild(document.createTextNode(tuj.lang.under));
        underOver.appendChild(opt);
        opt = libtuj.ce('option');
        opt.value = 'Over';
        opt.label = tuj.lang.over;
        opt.appendChild(document.createTextNode(tuj.lang.over));
        underOver.appendChild(opt);
        d.appendChild(underOver);

        var price = libtuj.ce('input');
        price.className = 'input-price';
        price.type = 'number';
        price.min = 0;
        price.max = 999999;
        price.value = 0;
        price.maxLength = 6;
        price.size = "10";
        price.autocomplete = 'off';
        d.appendChild(price);

        var s = libtuj.ce('span');
        s.className = 'input-price-unit';
        s.appendChild(document.createTextNode(tuj.lang.suffixGold));
        d.appendChild(s);

        var btn = libtuj.ce('input');
        btn.type = 'button';
        btn.value = tuj.lang.add;
        $(btn).on('click', ItemNotificationsAdd.bind(btn, mainDiv, itemId, lvl, regionBox, underOver, qty, price));
        d.appendChild(btn);

        $(mainDiv).show();
    }

    function ItemNotificationsTypeChange()
    {
        var $parent = $(this.parentNode);
        $parent.find('.notification-type-form').hide();
        $parent.find('.notification-type-form-'+this.options[this.selectedIndex].value).show();
    }

    function ItemNotificationsAdd(mainDiv, itemId, lvl, regionBox, directionBox, qtyBox, priceBox)
    {
        var self = this;
        var o = {
            'setwatch': 'item',
            'id': itemId,
            'subid': lvl,
            'region': regionBox.options[regionBox.selectedIndex].value == 'region' ? tuj.validRegions[params.region] : '',
            'house': regionBox.options[regionBox.selectedIndex].value == 'house' ? tuj.realms[params.realm].house : '',
            'direction': directionBox.options[directionBox.selectedIndex].value,
            'quantity': qtyBox ? parseInt(qtyBox.value, 10) : -1,
            'price': priceBox ? parseInt(parseFloat(priceBox.value, 10) * 10000, 10) : -1
        };
        if (o.quantity < 0) {
            o.quantity = -1;
        }
        if (o.price < 0) {
            o.price = -1;
        }

        if (o.quantity >= 0) {
            if (o.price < 0) {
                // qty available query
                if (o.quantity == 0 && o.direction == 'Under') {
                    // qty never under 0
                    alert(tuj.lang.quantityUnderZero);
                    return;
                }
            } else {
                // cost to buy $quantity is $direction $price
                if (o.quantity == 0) {
                    alert(tuj.lang.buyMoreThanZero);
                    return;
                }
                if (o.price == 0) {
                    alert(tuj.lang.priceAboveZero);
                    return;
                }
            }
        } else {
            // market price queries
            if (o.price <= 0) {
                alert(tuj.lang.priceAboveZero);
                return;
            }
        }

        tuj.SendCSRFProtectedRequest({
            data: o,
            success: ItemNotificationsList.bind(self, itemId, lvl, mainDiv),
        });
    }

    function ItemNotificationsDel(mainDiv, itemId, lvl, id)
    {
        var self = this;
        tuj.SendCSRFProtectedRequest({
            data: {'deletewatch': id},
            success: ItemNotificationsList.bind(self, itemId, lvl, mainDiv),
        });
    }

    function ItemHistoryChart(data, dest)
    {
        var hcdata = {price: [], priceMaxVal: 0, quantity: [], quantityMaxVal: 0, reagentPrice: [], tooltip: {}};

        var allPrices = [];
        for (var x = 0; x < data.history[level].length; x++) {
            hcdata.tooltip[data.history[level][x].snapshot * 1000] = [data.history[level][x].silver * 100, data.history[level][x].quantity];
            hcdata.price.push([data.history[level][x].snapshot * 1000, data.history[level][x].silver * 100]);
            hcdata.quantity.push([data.history[level][x].snapshot * 1000, data.history[level][x].quantity]);
            if (hcdata.reagentPrice.length || data.history[level][x].hasOwnProperty('reagentprice')) {
                hcdata.reagentPrice.push([data.history[level][x].snapshot * 1000, data.history[level][x].reagentprice]);
                hcdata.tooltip[data.history[level][x].snapshot * 1000].push(data.history[level][x].reagentprice);
            }
            if (data.history[level][x].quantity > hcdata.quantityMaxVal) {
                hcdata.quantityMaxVal = data.history[level][x].quantity;
            }
            allPrices.push(data.history[level][x].silver * 100);
        }

        allPrices.sort(function (a, b)
        {
            return a - b;
        });
        var q1 = allPrices[Math.floor(allPrices.length * 0.25)];
        var q3 = allPrices[Math.floor(allPrices.length * 0.75)];
        var iqr = q3 - q1;
        hcdata.priceMaxVal = q3 + (1.5 * iqr);

        var chartSeries = [
            {
                type: 'area',
                name: tuj.lang.marketPrice,
                color: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                lineColor: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                fillColor: tujConstants.siteColors[tuj.colorTheme].bluePriceFill,
                data: hcdata.price
            },
            {
                type: 'line',
                name: tuj.lang.availableQuantity,
                yAxis: 1,
                color: tujConstants.siteColors[tuj.colorTheme].redQuantity,
                data: hcdata.quantity
            }
        ];

        if (hcdata.reagentPrice.length) {
            chartSeries.splice(0,0,{
                type: 'area',
                name: tuj.lang.craftingCost,
                color: tujConstants.siteColors[tuj.colorTheme].greenPrice,
                lineColor: tujConstants.siteColors[tuj.colorTheme].greenPrice,
                fillColor: tujConstants.siteColors[tuj.colorTheme].greenPriceFill,
                data: hcdata.reagentPrice
            });
            chartSeries[1].fillColor = tujConstants.siteColors[tuj.colorTheme].bluePriceFillAlpha;
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
                    tuj.lang.zoomPinch
                    ,
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
                        text: tuj.lang.marketPrice,
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].bluePrice
                        }
                    },
                    labels: {
                        enabled: true,
                        formatter: function ()
                        {
                            return '' + libtuj.FormatPrice(this.value, true);
                        },
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].text
                        }
                    },
                    min: 0,
                    max: hcdata.priceMaxVal
                },
                {
                    title: {
                        text: tuj.lang.availableQuantity,
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].redQuantity
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
                    opposite: true,
                    min: 0,
                    max: hcdata.quantityMaxVal
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
                    tr += '<br><span style="color: #000099">' + tuj.lang.marketPrice + ': ' + libtuj.FormatPrice(hcdata.tooltip[this.x][0], true) + '</span>';
                    tr += '<br><span style="color: #990000">' + tuj.lang.quantity + ': ' + libtuj.FormatQuantity(hcdata.tooltip[this.x][1], true) + '</span>';
                    if (hcdata.tooltip[this.x].length > 2) {
                        tr += '<br><span style="color: #009900">Crafting Cost: ' + libtuj.FormatPrice(hcdata.tooltip[this.x][2], true) + '</span>';
                    }
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
            series: chartSeries
        });
    }

    function ItemMonthlyChart(data, dest)
    {
        var hcdata = {price: [], priceMaxVal: 0, quantity: [], quantityMaxVal: 0, globalprice: [], ttLookup: {}};

        var allPrices = [], dt, dtParts;
        var offset = (new Date()).getTimezoneOffset() * 60 * 1000;
        var earliestDate = Date.now();
        for (var x = 0; x < data.monthly[level].length; x++) {
            dtParts = data.monthly[level][x].date.split('-');
            dt = Date.UTC(dtParts[0], parseInt(dtParts[1], 10) - 1, dtParts[2]) + offset;
            if (dt < earliestDate) {
                earliestDate = dt;
            }
            hcdata.price.push([dt, data.monthly[level][x].silver * 100]);
            hcdata.quantity.push([dt, data.monthly[level][x].quantity]);
            if (data.monthly[level][x].quantity > hcdata.quantityMaxVal) {
                hcdata.quantityMaxVal = data.monthly[level][x].quantity;
            }
            allPrices.push(data.monthly[level][x].silver * 100);
            hcdata.ttLookup[dt] = {
                'market': data.monthly[level][x].silver * 100,
                'quantity': data.monthly[level][x].quantity,
            };
        }
        for (var x = 0; x < data.globalmonthly[level].length; x++) {
            dtParts = data.globalmonthly[level][x].date.split('-');
            dt = Date.UTC(dtParts[0], parseInt(dtParts[1], 10) - 1, dtParts[2]) + offset;
            if (dt < earliestDate) {
                continue;
            }
            hcdata.globalprice.push([dt, data.globalmonthly[level][x].silver * 100]);
            if (!hcdata.ttLookup.hasOwnProperty(dt)) {
                hcdata.ttLookup[dt] = {};
            }
            hcdata.ttLookup[dt].region = data.globalmonthly[level][x].silver * 100;
        }

        allPrices.sort(function (a, b)
        {
            return a - b;
        });
        var q1 = allPrices[Math.floor(allPrices.length * 0.25)];
        var q3 = allPrices[Math.floor(allPrices.length * 0.75)];
        var iqr = q3 - q1;
        hcdata.priceMaxVal = q3 + (1.5 * iqr);

        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });

        $(dest).highcharts("StockChart", {
            chart: {
                zoomType: 'x',
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },
            rangeSelector: {
                buttons: [
                    {
                        type: 'week',
                        count: 2,
                        text: tuj.lang.rangeButtons['2week']
                    },
                    {
                        type: 'month',
                        count: 1,
                        text: tuj.lang.rangeButtons['1month']
                    },
                    {
                        type: 'month',
                        count: 3,
                        text: tuj.lang.rangeButtons['3month']
                    },
                    {
                        type: 'month',
                        count: 6,
                        text: tuj.lang.rangeButtons['6month']
                    },
                    {
                        type: 'year',
                        count: 1,
                        text: tuj.lang.rangeButtons['1year']
                    },
                    {
                        type: 'all',
                        text: tuj.lang.all
                    },
                ],
                selected: 4,
                inputEnabled: false
            },
            navigator: {
                enabled: false,
            },
            scrollbar: {
                enabled: false,
            },
            title: {
                text: null
            },
            xAxis: {
                type: 'datetime',
                maxZoom: 4 * 24 * 3600000, // four days
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
                        text: tuj.lang.marketPrice,
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].bluePrice
                        }
                    },
                    labels: {
                        enabled: true,
                        formatter: function ()
                        {
                            return '' + libtuj.FormatPrice(this.value, true);
                        },
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].text
                        }
                    },
                    min: 0,
                    max: hcdata.priceMaxVal,
                    opposite: false,
                },
                {
                    title: {
                        text: tuj.lang.availableQuantity,
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].redQuantity
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
                    opposite: true,
                    min: 0,
                    max: hcdata.quantityMaxVal
                }
            ],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function ()
                {
                    var tr = '<b>' + Highcharts.dateFormat('%a %b %e %Y', this.x) + '</b>';
                    if (!hcdata.ttLookup.hasOwnProperty(this.x)) {
                        return tr;
                    }

                    var points = hcdata.ttLookup[this.x];
                    if (points.hasOwnProperty('market')) {
                        tr += '<br><span style="color: #000099">' + tuj.lang.marketPrice + ': ' + libtuj.FormatPrice(points.market, true) + '</span>';
                    }
                    if (points.hasOwnProperty('region')) {
                        tr += '<br><span style="color: #009900">' + tuj.lang.regionPrice + ': ' + libtuj.FormatPrice(points.region, true) + '</span>';
                    }
                    if (points.hasOwnProperty('quantity')) {
                        tr += '<br><span style="color: #990000">' + tuj.lang.quantity + ': ' + libtuj.FormatQuantity(points.quantity, true) + '</span>';
                    }
                    return tr;
                    // &lt;br/&gt;&lt;span style="color: #990000"&gt;Quantity: '+this.points[1].y+'&lt;/span&gt;<xsl:if test="itemgraphs/d[@matsprice != '']">&lt;br/&gt;&lt;span style="color: #999900"&gt;Materials Price: '+this.points[2].y.toFixed(2)+'g&lt;/span&gt;</xsl:if>';
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
                    name: tuj.lang.marketPrice,
                    color: tujConstants.siteColors[tuj.colorTheme].greenPrice,
                    lineColor: tujConstants.siteColors[tuj.colorTheme].greenPrice,
                    fillColor: tujConstants.siteColors[tuj.colorTheme].greenPriceFill,
                    data: hcdata.globalprice
                },
                {
                    type: 'area',
                    name: tuj.lang.marketPrice,
                    color: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    lineColor: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    fillColor: tujConstants.siteColors[tuj.colorTheme].bluePriceFillAlpha,
                    data: hcdata.price
                },
                {
                    type: 'line',
                    name: tuj.lang.availableQuantity,
                    yAxis: 1,
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantity,
                    data: hcdata.quantity
                }
            ]
        });
    }

    function ItemGlobalMonthlyChart(data, dest)
    {
        var hcdata = {price: [], priceMaxVal: 0, quantity: [], quantityMaxVal: 0, tooltip: {}};

        var allPrices = [], dt, dtParts;
        var offset = (new Date()).getTimezoneOffset() * 60 * 1000;
        var earliestDate = Date.now();
        for (var x = 0; x < data.globalmonthly[level].length; x++) {
            dtParts = data.globalmonthly[level][x].date.split('-');
            dt = Date.UTC(dtParts[0], parseInt(dtParts[1], 10) - 1, dtParts[2]) + offset;
            if (dt < earliestDate) {
                earliestDate = dt;
            }
            hcdata.tooltip[dt] = [data.globalmonthly[level][x].silver * 100, data.globalmonthly[level][x].quantity];
            hcdata.price.push([dt, data.globalmonthly[level][x].silver * 100]);
            hcdata.quantity.push([dt, data.globalmonthly[level][x].quantity]);
            if (data.globalmonthly[level][x].quantity > hcdata.quantityMaxVal) {
                hcdata.quantityMaxVal = data.globalmonthly[level][x].quantity;
            }
            allPrices.push(data.globalmonthly[level][x].silver * 100);
        }

        allPrices.sort(function (a, b)
        {
            return a - b;
        });
        var q1 = allPrices[Math.floor(allPrices.length * 0.25)];
        var q3 = allPrices[Math.floor(allPrices.length * 0.75)];
        var iqr = q3 - q1;
        hcdata.priceMaxVal = q3 + (1.5 * iqr);

        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });

        $(dest).highcharts("StockChart", {
            chart: {
                zoomType: 'x',
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },
            rangeSelector: {
                buttons: [
                    {
                        type: 'week',
                        count: 2,
                        text: tuj.lang.rangeButtons['2week']
                    },
                    {
                        type: 'month',
                        count: 1,
                        text: tuj.lang.rangeButtons['1month']
                    },
                    {
                        type: 'month',
                        count: 3,
                        text: tuj.lang.rangeButtons['3month']
                    },
                    {
                        type: 'month',
                        count: 6,
                        text: tuj.lang.rangeButtons['6month']
                    },
                    {
                        type: 'year',
                        count: 1,
                        text: tuj.lang.rangeButtons['1year']
                    },
                    {
                        type: 'all',
                        text: tuj.lang.all
                    },
                ],
                selected: 4,
                inputEnabled: false
            },
            navigator: {
                enabled: false,
            },
            scrollbar: {
                enabled: false,
            },
            title: {
                text: null
            },
            xAxis: {
                type: 'datetime',
                maxZoom: 4 * 24 * 3600000, // four days
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
                        text: tuj.lang.marketPrice,
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].bluePrice
                        }
                    },
                    labels: {
                        enabled: true,
                        formatter: function ()
                        {
                            return '' + libtuj.FormatPrice(this.value, true);
                        },
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].text
                        }
                    },
                    opposite: false,
                    min: 0,
                    max: hcdata.priceMaxVal
                },
                {
                    title: {
                        text: tuj.lang.availableQuantity,
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].redQuantity
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
                    opposite: true,
                    min: 0,
                    max: hcdata.quantityMaxVal
                }
            ],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function ()
                {
                    var tr = '<b>' + Highcharts.dateFormat('%a %b %e %Y', this.x) + '</b>';
                    tr += '<br><span style="color: #000099">' + tuj.lang.regionPrice + ': ' + libtuj.FormatPrice(hcdata.tooltip[this.x][0], true) + '</span>';
                    tr += '<br><span style="color: #990000">' + tuj.lang.quantity + ': ' + libtuj.FormatQuantity(hcdata.tooltip[this.x][1], true) + '</span>';
                    return tr;
                    // &lt;br/&gt;&lt;span style="color: #990000"&gt;Quantity: '+this.points[1].y+'&lt;/span&gt;<xsl:if test="itemgraphs/d[@matsprice != '']">&lt;br/&gt;&lt;span style="color: #999900"&gt;Materials Price: '+this.points[2].y.toFixed(2)+'g&lt;/span&gt;</xsl:if>';
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
                    name: tuj.lang.marketPrice,
                    color: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    lineColor: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    fillColor: tujConstants.siteColors[tuj.colorTheme].bluePriceFillAlpha,
                    data: hcdata.price
                },
                {
                    type: 'line',
                    name: tuj.lang.availableQuantity,
                    yAxis: 1,
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantity,
                    data: hcdata.quantity
                }
            ]
        });
    }

    function ItemDailyChart(data, dest)
    {
        var hcdata = {
            ohlc: [],
            ohlcMaxVal: 0,
            price: [],
            quantity: [],
            quantityRange: [],
            quantityMaxVal: 0
        };

        var allPrices = [], dt, dtParts;
        var offset = (new Date()).getTimezoneOffset() * 60 * 1000;
        for (var x = 0; x < data.daily.length; x++) {
            dtParts = data.daily[x].date.split('-');
            dt = Date.UTC(dtParts[0], parseInt(dtParts[1], 10) - 1, dtParts[2]) + offset;

            hcdata.ohlc.push([
                dt,
                data.daily[x].silverstart * 100,
                data.daily[x].silvermax * 100,
                data.daily[x].silvermin * 100,
                data.daily[x].silverend * 100
            ]);
            allPrices.push(data.daily[x].silvermax * 100);

            hcdata.price.push([dt, data.daily[x].silveravg * 100]);

            hcdata.quantity.push([dt, data.daily[x].quantityavg]);
            hcdata.quantityRange.push([dt, data.daily[x].quantitymin, data.daily[x].quantitymax]);
            if (data.daily[x].quantityavg > hcdata.quantityMaxVal) {
                hcdata.quantityMaxVal = data.daily[x].quantityavg;
            }
        }

        allPrices.sort(function (a, b)
        {
            return a - b;
        });
        var q1 = allPrices[Math.floor(allPrices.length * 0.25)];
        var q3 = allPrices[Math.floor(allPrices.length * 0.75)];
        var iqr = q3 - q1;
        hcdata.ohlcMaxVal = q3 + (1.5 * iqr);

        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });

        $(dest).highcharts('StockChart', {
            chart: {
                zoomType: 'x',
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },
            rangeSelector: {
                buttons: [
                    {
                        type: 'week',
                        count: 2,
                        text: tuj.lang.rangeButtons['2week']
                    },
                    {
                        type: 'month',
                        count: 1,
                        text: tuj.lang.rangeButtons['1month']
                    },
                    {
                        type: 'all',
                        text: tuj.lang.rangeButtons['3month']
                    },
                ],
                selected: 2,
                inputEnabled: false
            },
            navigator: {
                enabled: false
            },
            scrollbar: {
                enabled: false
            },
            title: {
                text: null
            },
            xAxis: {
                type: 'datetime',
                maxZoom: 4 * 24 * 3600000, // four days
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
                        text: tuj.lang.marketPrice,
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].bluePrice
                        }
                    },
                    labels: {
                        enabled: true,
                        formatter: function ()
                        {
                            return '' + libtuj.FormatPrice(this.value, true);
                        },
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].text
                        }
                    },
                    height: '60%',
                    opposite: false,
                    min: 0,
                    max: hcdata.ohlcMaxVal
                },
                {
                    title: {
                        text: tuj.lang.availableQuantity,
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].redQuantity
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
                    top: '65%',
                    height: '35%',
                    min: 0,
                    max: hcdata.quantityMaxVal,
                    offset: -25
                }
            ],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function ()
                {
                    var tr = '<b>' + Highcharts.dateFormat('%a %b %e %Y', this.x) + '</b>';
                    tr += '<br><table class="highcharts-tuj-tooltip" style="color: #000099;" cellspacing="0" cellpadding="0">';
                    tr += '<tr><td>Open:</td><td align="right">' + libtuj.FormatPrice(this.points[0].point.open, true) + '</td></tr>';
                    tr += '<tr><td>High:</td><td align="right">' + libtuj.FormatPrice(this.points[0].point.high, true) + '</td></tr>';
                    tr += '<tr style="color: #009900"><td>Avg:</td><td align="right">' + libtuj.FormatPrice(this.points[3].y, true) + '</td></tr>';
                    tr += '<tr><td>Low:</td><td align="right">' + libtuj.FormatPrice(this.points[0].point.low, true) + '</td></tr>';
                    tr += '<tr><td>Close:</td><td align="right">' + libtuj.FormatPrice(this.points[0].point.close, true) + '</td></tr>';
                    tr += '</table>';
                    tr += '<br><table class="highcharts-tuj-tooltip" style="color: #FF3333;" cellspacing="0" cellpadding="0">';
                    tr += '<tr><td>Min&nbsp;Qty:</td><td align="right">' + libtuj.FormatQuantity(this.points[2].point.low, true) + '</td></tr>';
                    tr += '<tr><td>Avg&nbsp;Qty:</td><td align="right">' + libtuj.FormatQuantity(this.points[1].y, true) + '</td></tr>';
                    tr += '<tr><td>Max&nbsp;Qty:</td><td align="right">' + libtuj.FormatQuantity(this.points[2].point.high, true) + '</td></tr>';
                    tr += '</table>';
                    return tr;
                    // &lt;br/&gt;&lt;span style="color: #990000"&gt;Quantity: '+this.points[1].y+'&lt;/span&gt;<xsl:if test="itemgraphs/d[@matsprice != '']">&lt;br/&gt;&lt;span style="color: #999900"&gt;Materials Price: '+this.points[2].y.toFixed(2)+'g&lt;/span&gt;</xsl:if>';
                },
                useHTML: true,
                positioner: function (w, h, p)
                {
                    var x = p.plotX, y = p.plotY;
                    if (y < 0) {
                        y = 0;
                    }
                    if (x < (this.chart.plotWidth / 2)) {
                        x += w / 2;
                    }
                    else {
                        x -= w * 1.25;
                    }
                    return {x: x, y: y};
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
                    type: 'candlestick',
                    name: tuj.lang.marketPrice,
                    upColor: tujConstants.siteColors[tuj.colorTheme].background,
                    color: tujConstants.siteColors[tuj.colorTheme].bluePriceFill,
                    lineColor: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    data: hcdata.ohlc
                },
                {
                    type: 'line',
                    name: tuj.lang.quantity,
                    yAxis: 1,
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantity,
                    data: hcdata.quantity,
                    lineWidth: 2
                },
                {
                    type: 'arearange',
                    name: tuj.lang.quantityRange,
                    yAxis: 1,
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantityFillLight,
                    data: hcdata.quantityRange
                },
                {
                    type: 'line',
                    name: tuj.lang.marketPrice,
                    color: tujConstants.siteColors[tuj.colorTheme].greenPriceDim,
                    data: hcdata.price
                }
            ]
        });
    }

    function ItemExpiredChart(data, dest)
    {
        var hcdata = {created: [], expired: [], maxVal: 0};

        var dt, dtParts;
        var offset = (new Date()).getTimezoneOffset() * 60 * 1000;
        var earliestDate = Date.now();
        for (var x = 0; x < data.expired[level].length; x++) {
            dtParts = data.expired[level][x].date.split('-');
            dt = Date.UTC(dtParts[0], parseInt(dtParts[1], 10) - 1, dtParts[2]) + offset;
            if (dt < earliestDate) {
                earliestDate = dt;
            }
            hcdata.created.push([dt, data.expired[level][x].created]);
            hcdata.expired.push([dt, data.expired[level][x].expired]);

            if (data.expired[level][x].created > hcdata.maxVal) {
                hcdata.maxVal = data.expired[level][x].created;
            }
        }
        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });

        $(dest).highcharts({
            chart: {
                type: 'column',
                zoomType: 'x',
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },
            title: {
                text: null
            },
            xAxis: {
                type: 'datetime',
                maxZoom: 4 * 24 * 3600000, // four days
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
                            color: tujConstants.siteColors[tuj.colorTheme].redQuantity
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
                    max: hcdata.maxVal,
                    opposite: false,
                }
            ],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function ()
                {
                    var tr = '<b>' + Highcharts.dateFormat('%a %b %e %Y', this.x) + '</b>';
                    if (this.points[0]) {
                        tr += '<br><span style="color: #900">' + tuj.lang.newAuctions + ': ' + libtuj.FormatQuantity(this.points[0].y, true) + '</span>';
                        if (this.points[1]) {
                            tr += '<br><span>' + tuj.lang.expired + ': ' + Math.round(this.points[1].y / this.points[0].y * 100) + '%</span>';
                        }
                    } else if (this.points[1]) {
                        tr += '<br><span>' + tuj.lang.expired + ': ' + libtuj.FormatQuantity(this.points[1].y, true) + '</span>';
                    }
                    return tr;
                }
            },
            plotOptions: {
                column: {
                    stacking: 'normal',
                }
            },
            series: [
                {
                    type: 'column',
                    name: 'created',
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantityFill,
                    data: hcdata.created
                },
                {
                    type: 'column',
                    name: 'expired',
                    color: tujConstants.siteColors[tuj.colorTheme].text,
                    data: hcdata.expired
                }
            ]
        });
    }

    function ItemPriceHeatMap(data, dest)
    {
        var hcdata = {minVal: undefined, maxVal: 0, days: {}, heat: [], categories: {
            x: tuj.lang.heatMapHours,
            y: tuj.lang.heatMapDays
        }};

        $(dest).empty();

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
        };

        var d, wkdy, hr, lastprice;
        for (wkdy = 0; wkdy < hcdata.categories.y.length; wkdy++) {
            hcdata.days[wkdy] = {};
            for (hr = 0; hr < hcdata.categories.x.length; hr++) {
                hcdata.days[wkdy][hr] = [];
            }
        }

        for (var x = 0; x < data.history[level].length; x++) {
            if (typeof lastprice == 'undefined') {
                lastprice = data.history[level][x].silver * 100;
            }

            var d = new Date(data.history[level][x].snapshot * 1000);
            wkdy = 6 - d.getDay();
            hr = Math.floor(d.getHours() * hcdata.categories.x.length / 24);
            hcdata.days[wkdy][hr].push(data.history[level][x].silver * 100);
        }

        var p;
        for (wkdy = 0; wkdy < hcdata.categories.y.length; wkdy++) {
            for (hr = 0; hr < hcdata.categories.x.length; hr++) {
                if (hcdata.days[wkdy][hr].length == 0) {
                    p = lastprice;
                }
                else {
                    p = Math.round(CalcAvg(hcdata.days[wkdy][hr]));
                }

                lastprice = p;
                hcdata.heat.push([hr, wkdy, p / 10000]);
                hcdata.minVal = (typeof hcdata.minVal == 'undefined' || hcdata.minVal > p / 10000) ? p / 10000 : hcdata.minVal;
                hcdata.maxVal = hcdata.maxVal < p / 10000 ? p / 10000 : hcdata.maxVal;
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
                enabled: false
            },

            tooltip: {
                enabled: false
            },

            series: [
                {
                    name: tuj.lang.marketPrice,
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
                            return '' + libtuj.FormatPrice(this.point.value * 10000, true, true);
                        }
                    }
                }
            ]

        });
    }

    function ItemQuantityHeatMap(data, dest)
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

        var d, wkdy, hr, lastqty;
        for (wkdy = 0; wkdy < hcdata.categories.y.length; wkdy++) {
            hcdata.days[wkdy] = {};
            for (hr = 0; hr < hcdata.categories.x.length; hr++) {
                hcdata.days[wkdy][hr] = [];
            }
        }

        for (var x = 0; x < data.history[level].length; x++) {
            if (typeof lastqty == 'undefined') {
                lastqty = data.history[level][x].quantity;
            }

            var d = new Date(data.history[level][x].snapshot * 1000);
            wkdy = 6 - d.getDay();
            hr = Math.floor(d.getHours() * hcdata.categories.x.length / 24);
            hcdata.days[wkdy][hr].push(data.history[level][x].quantity);
        }

        var p;
        for (wkdy = 0; wkdy < hcdata.categories.y.length; wkdy++) {
            for (hr = 0; hr < hcdata.categories.x.length; hr++) {
                if (hcdata.days[wkdy][hr].length == 0) {
                    p = lastqty;
                }
                else {
                    p = Math.round(CalcAvg(hcdata.days[wkdy][hr]));
                }

                lastqty = p;
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
                maxColor: tujConstants.siteColors[tuj.colorTheme].redQuantityBackground
            },

            legend: {
                enabled: false
            },

            tooltip: {
                enabled: false
            },

            series: [
                {
                    name: tuj.lang.quantity,
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

    function ItemGlobalNowColumns(data, dest)
    {
        var hcdata = {categories: [], price: [], quantity: [], lastseen: [], houses: []};
        var allPrices = [];
        var allQuantities = [];
        data.globalnow[level].sort(function (a, b)
        {
            return (b.price - a.price) || (b.quantity - a.quantity);
        });

        var isThisHouse = false;
        for (var x = 0; x < data.globalnow[level].length; x++) {
            isThisHouse = data.globalnow[level][x].house == tuj.realms[params.realm].house;

            hcdata.categories.push(data.globalnow[level][x].house);
            hcdata.quantity.push(data.globalnow[level][x].quantity);
            hcdata.price.push(isThisHouse ? {
                y: data.globalnow[level][x].price,
                dataLabels: {
                    enabled: true,
                    formatter: function ()
                    {
                        return '<b>' + tuj.realms[params.realm].name + '</b>';
                    },
                    backgroundColor: '#FFFFFF',
                    borderColor: '#000000',
                    borderRadius: 2,
                    borderWidth: 1
                }} : data.globalnow[level][x].price);
            hcdata.lastseen.push(data.globalnow[level][x].lastseen);
            hcdata.houses.push(data.globalnow[level][x].house);

            allQuantities.push(data.globalnow[level][x].quantity);
            allPrices.push(data.globalnow[level][x].price);
        }

        allPrices.sort(function (a, b)
        {
            return a - b;
        });
        var q1 = allPrices[Math.floor(allPrices.length * 0.25)];
        var q3 = allPrices[Math.floor(allPrices.length * 0.75)];
        var iqr = q3 - q1;
        hcdata.priceMaxVal = Math.min(allPrices.pop(), q3 + (2.5 * iqr));

        allQuantities.sort(function (a, b)
        {
            return a - b;
        });
        var q1 = allQuantities[Math.floor(allQuantities.length * 0.25)];
        var q3 = allQuantities[Math.floor(allQuantities.length * 0.75)];
        var iqr = q3 - q1;
        hcdata.quantityMaxVal = q3 + (1.5 * iqr);

        var PriceClick = function (houses, evt)
        {
            var realm;
            for (var x in tuj.realms) {
                if (tuj.realms.hasOwnProperty(x) && tuj.realms[x].house == houses[evt.point.x]) {
                    realm = tuj.realms[x].id;
                    break;
                }
            }
            if (realm) {
                tuj.SetParams({realm: realm});
            }
        };

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
                labels: {
                    enabled: false
                }
            },
            yAxis: [
                {
                    title: {
                        text: tuj.lang.marketPrice,
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].bluePrice
                        }
                    },
                    min: 0,
                    max: hcdata.priceMaxVal,
                    labels: {
                        enabled: true,
                        formatter: function ()
                        {
                            return '' + libtuj.FormatPrice(this.value, true);
                        },
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].text
                        }
                    }
                },
                {
                    title: {
                        text: tuj.lang.quantity,
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].redQuantity
                        }
                    },
                    min: 0,
                    max: hcdata.quantityMaxVal,
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
                    opposite: true
                }
            ],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function ()
                {
                    var realmNames = libtuj.GetRealmsForHouse(hcdata.houses[this.x], 40);
                    var tr = '<b>' + realmNames + '</b>';
                    tr += '<br><span style="color: #000099">' + tuj.lang.marketPrice + ': ' + libtuj.FormatPrice(this.points[0].y, true) + '</span>';
                    tr += '<br><span style="color: #990000">' + tuj.lang.quantity + ': ' + libtuj.FormatQuantity(this.points[1].y, true) + '</span>';
                    tr += '<br><span style="color: #990000">' + tuj.lang.lastSeen + ': ' + libtuj.FormatDate(hcdata.lastseen[this.x], true) + '</span>';
                    return tr;
                },
                useHTML: true
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
                    type: 'line',
                    name: tuj.lang.marketPrice,
                    color: tujConstants.siteColors[tuj.colorTheme].bluePriceFill,
                    lineColor: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    data: hcdata.price,
                    yAxis: 0,
                    zIndex: 2,
                    events: {
                        click: PriceClick.bind(null, hcdata.houses)
                    }
                },
                {
                    type: 'column',
                    name: tuj.lang.quantity,
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantityFill,
                    borderColor: tujConstants.siteColors[tuj.colorTheme].background,
                    data: hcdata.quantity,
                    zIndex: 1,
                    yAxis: 1,
                    events: {
                        click: PriceClick.bind(null, hcdata.houses)
                    }
                }
            ]
        });
    }

    function ItemGlobalNowScatter(data, dest)
    {
        var hcdata = {price: [], quantity: {}, lastseen: {}, houses: {}};
        var allPrices = [];

        var o;
        for (var x = 0; x < data.globalnow[level].length; x++) {
            if (data.globalnow[level][x].house == tuj.realms[params.realm].house) {
                o = {
                    x: libtuj.GetHousePopulation(data.globalnow[level][x].house),
                    y: data.globalnow[level][x].price,
                    id: x,
                    marker: {
                        symbol: 'diamond'
                    },
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantity
                };
            } else {
                o = {
                    x: libtuj.GetHousePopulation(data.globalnow[level][x].house),
                    y: data.globalnow[level][x].price,
                    id: x
                };
                if (data.globalnow[level][x].quantity == 0) {
                    o.color = tujConstants.siteColors[tuj.colorTheme].bluePriceFill;
                }
            }

            hcdata.price.push(o);
            hcdata.houses[x] = data.globalnow[level][x].house;
            hcdata.quantity[x] = data.globalnow[level][x].quantity;
            hcdata.lastseen[x] = data.globalnow[level][x].lastseen;

            allPrices.push(data.globalnow[level][x].price);
        }

        allPrices.sort(function (a, b)
        {
            return a - b;
        });
        var q1 = allPrices[Math.floor(allPrices.length * 0.25)];
        var q3 = allPrices[Math.floor(allPrices.length * 0.75)];
        var iqr = q3 - q1;
        hcdata.priceMaxVal = Math.min(allPrices.pop(), q3 + (2.5 * iqr));

        var PriceClick = function (houses, evt)
        {
            var realm;
            for (var x in tuj.realms) {
                if (tuj.realms.hasOwnProperty(x) && tuj.realms[x].house == houses[evt.point.id]) {
                    realm = tuj.realms[x].id;
                    break;
                }
            }
            if (realm) {
                tuj.SetParams({realm: realm});
            }
        };

        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });

        $(dest).highcharts({
            chart: {
                type: 'scatter',
                zoomType: 'xy',
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },
            title: {
                text: null
            },
            subtitle: {
                text: null,
            },
            xAxis: {
                title: {
                    text: tuj.lang.population,
                    style: {
                        color: tujConstants.siteColors[tuj.colorTheme].greenPriceDim
                    }
                },
                labels: {
                    enabled: true,
                    style: {
                        color: tujConstants.siteColors[tuj.colorTheme].text
                    }
                },
                min: 0
            },
            yAxis: {
                title: {
                    text: tuj.lang.marketPrice,
                    style: {
                        color: tujConstants.siteColors[tuj.colorTheme].bluePrice
                    }
                },
                min: 0,
                max: hcdata.priceMaxVal,
                labels: {
                    enabled: true,
                    formatter: function ()
                    {
                        return '' + libtuj.FormatPrice(this.value, true);
                    },
                    style: {
                        color: tujConstants.siteColors[tuj.colorTheme].text
                    }
                }
            },
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function ()
                {
                    var realmNames = libtuj.GetRealmsForHouse(hcdata.houses[this.point.id], 40);
                    var tr = '<b>' + realmNames + '</b>';
                    tr += '<br><span style="color: #000099">' + tuj.lang.marketPrice + ': ' + libtuj.FormatPrice(this.point.y, true) + '</span>';
                    tr += '<br><span style="color: #990000">' + tuj.lang.quantity + ': ' + libtuj.FormatQuantity(hcdata.quantity[this.point.id], true) + '</span>';
                    tr += '<br><span style="color: #990000">' + tuj.lang.lastSeen + ': ' + libtuj.FormatDate(hcdata.lastseen[this.point.id], true) + '</span>';
                    return tr;
                },
                useHTML: true
            },
            plotOptions: {
                scatter: {
                    marker: {
                        radius: 5,
                        states: {
                            hover: {
                                enabled: true
                            }
                        }
                    },
                    events: {
                        click: PriceClick.bind(null, hcdata.houses)
                    }
                }
            },
            series: [
                {
                    name: tuj.lang.marketPrice,
                    color: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    data: hcdata.price,
                    yAxis: 0,
                    zIndex: 2
                }
            ]
        });
    }

    function ItemRecentSellersColumns(data, dest)
    {
        var hcdata = {'categories': [], 'quantity': [], 'recentQuantity': [], 'links': []};
        data.sellers.sort(function (a, b) {
            return (b.quantity - a.quantity) || a.sellername.localeCompare(b.sellername);
        });

        var sellerName;
        for (var x = 0, slr; slr = data.sellers[x]; x++) {
            sellerName = slr.sellername + (slr.sellerrealm && slr.sellerrealm != params.realm ? (' - ' + tuj.realms[slr.sellerrealm].name) : '');

            hcdata.links.push({realm: slr.sellerrealm, page: 'seller', id: slr.sellername});
            hcdata.categories.push(sellerName);
            hcdata.quantity.push(slr.quantity - slr.recentquantity);
            hcdata.recentQuantity.push(slr.recentquantity);
        }

        var SellerClick = function (links, evt) {
            tuj.SetParams(links[evt.point.x]);
        };

        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });

        $(dest).highcharts({
            chart: {
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },
            title: {
                text: null
            },
            subtitle: {
                text: null
            },
            xAxis: {
                categories: hcdata.categories
            },
            yAxis: [
                {
                    title: {
                        text: tuj.lang.quantity,
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].redQuantity
                        }
                    },
                    min: 0,
                    labels: {
                        enabled: true,
                        formatter: function ()
                        {
                            return '' + libtuj.FormatQuantity(this.value, true);
                        },
                        style: {
                            color: tujConstants.siteColors[tuj.colorTheme].text
                        }
                    }
                }
            ],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function () {
                    var tr = '', x = this.points[0].point.x;
                    tr += '<b>' + this.points[0].x + '</b>';
                    tr += '<br>' + tuj.lang.lastSeen + ' ' + libtuj.FormatDate(data.sellers[x].lastseen, true) + '<br>';
                    tr += '<br><span style="color: #990000">' + libtuj.sprintf(tuj.lang.timePast, ' 0-4 ' + tuj.lang.timeDays) + ': ' + libtuj.FormatQuantity(this.points[1].y, true) + '</span>';
                    tr += '<br><span style="color: #990000">' + libtuj.sprintf(tuj.lang.timePast, '0-14 ' + tuj.lang.timeDays) + ': ' + libtuj.FormatQuantity(this.points[1].y + this.points[0].y, true) + '</span>';
                    return tr;
                },
                useHTML: true
            },
            plotOptions: {
                column: {
                    stacking: 'normal',
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
                    states: {
                        hover: {
                            lineWidth: 2
                        }
                    }
                }
            },
            series: [
                {
                    type: 'column',
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantityFillLight,
                    borderColor: tujConstants.siteColors[tuj.colorTheme].background,
                    data: hcdata.quantity,
                    events: {
                        click: SellerClick.bind(null, hcdata.links)
                    }
                },
                {
                    type: 'column',
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantityFill,
                    borderColor: tujConstants.siteColors[tuj.colorTheme].background,
                    data: hcdata.recentQuantity,
                    events: {
                        click: SellerClick.bind(null, hcdata.links)
                    }
                }

            ]
        });
    }

    function ItemAuctions(data, dest)
    {
        var hasMultipleLevels = levels.length > 1, x, auc;
        var hasRand = hasMultipleLevels;
        for (x = 0; (!hasRand) && (auc = data.auctions[x]); x++) {
            hasRand |= !!auc.rand;
            hasRand |= !!auc.bonuses;
        }

        var t, tr, td;
        t = libtuj.ce('table');
        t.className = 'auctionlist';

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'seller';
        $(td).text(tuj.lang.seller);

        if (hasRand) {
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.className = 'name';
            $(td).text(tuj.lang.Name);
        }

        if (data.stats[level].stacksize > 1) {
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.className = 'quantity';
            $(td).text(tuj.lang.quantity);
        }

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'price';
        $(td).text(tuj.lang.bidEach);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'price';
        $(td).text(tuj.lang.buyoutEach);

        data.auctions.sort(function (a, b) {
            return Math.floor(a.buy / a.quantity) - Math.floor(b.buy / b.quantity) ||
                Math.floor(a.bid / a.quantity) - Math.floor(b.bid / b.quantity) ||
                a.quantity - b.quantity ||
                (tuj.realms[a.sellerrealm] ? tuj.realms[a.sellerrealm].name : '').localeCompare(tuj.realms[b.sellerrealm] ? tuj.realms[b.sellerrealm].name : '') ||
                a.sellername.localeCompare(b.sellername) ||
                (a['bonusname_' + tuj.locale] || "").localeCompare(b['bonusname_' + tuj.locale] || "") ||
                (a['randname_' + tuj.locale] || "").localeCompare(b['randname_' + tuj.locale] || "");
        });

        var s, a, stackable = data.stats[level].stacksize > 1;
        var curRowSection, lastRowSection = false;
        var lastSellerTd = false;

        for (x = 0; auc = data.auctions[x]; x++) {
            curRowSection = (auc.sellername + (auc.sellerrealm ? auc.sellerrealm : ''));
            if (x == 0 || lastRowSection != curRowSection) {
                tr = libtuj.ce('tr');
                tr.className = 'blank';
                t.appendChild(tr);
            }

            tr = libtuj.ce('tr');
            t.appendChild(tr);

            if (lastRowSection != curRowSection) {
                lastSellerTd = td = libtuj.ce('td');
                td.rowSpan = 1;
                tr.appendChild(td);
                td.className = 'seller';
                if (auc.sellerrealm) {
                    a = libtuj.ce('a');
                    a.href = tuj.BuildHash({realm: auc.sellerrealm, page: 'seller', id: auc.sellername});
                    if (tuj.SellerIsBot(auc.sellerrealm, auc.sellername)) {
                        a.className = 'sellerbot';
                    }
                }
                else {
                    a = libtuj.ce('span');
                }
                td.appendChild(a);
                $(a).text(auc.sellername + (auc.sellerrealm && auc.sellerrealm != params.realm ? (' - ' + tuj.realms[auc.sellerrealm].name) : ''));

                lastRowSection = curRowSection;
            } else {
                lastSellerTd.rowSpan++;
                libtuj.AlsoHover(tr, lastSellerTd);
            }

            if (hasRand) {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.className = 'name';
                if (hasMultipleLevels) {
                    s = libtuj.ce('span');
                    s.className = 'level';
                    s.appendChild(document.createTextNode(auc.level));
                    td.appendChild(s);
                }
                a = libtuj.ce('a');
                a.href = 'http://' + tuj.lang.wowheadDomain + '.wowhead.com/item=' + data.stats[level].id + (auc.rand ? '&rand=' + auc.rand : '') + (auc.bonuses ? '&bonus=' + auc.bonuses : '') + (auc.lootedlevel ? '&lvl=' + auc.lootedlevel : '');
                td.appendChild(a);
                $(a).text('[' + data.stats[level]['name_' + tuj.locale] + (auc['bonusname_' + tuj.locale] ? ' ' + auc['bonusname_' + tuj.locale].substr(0, auc['bonusname_' + tuj.locale].indexOf('|') >= 0 ? auc['bonusname_' + tuj.locale].indexOf('|') : auc['bonusname_' + tuj.locale].length) : '') + (auc['randname_' + tuj.locale] ? ' ' + auc['randname_' + tuj.locale] : '') + ']');
            }

            if (data.stats[level].stacksize > 1) {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.className = 'quantity';
                td.appendChild(libtuj.FormatQuantity(auc.quantity));
            }

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatPrice(auc.bid / auc.quantity);
            if (stackable && auc.quantity > 1) {
                a = libtuj.ce('abbr');
                a.title = libtuj.FormatPrice(auc.bid, true) + ' ' + tuj.lang.total;
                a.appendChild(s);
            }
            else {
                a = s;
            }
            td.appendChild(a);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatPrice(auc.buy / auc.quantity);
            if (stackable && auc.quantity > 1 && auc.buy) {
                a = libtuj.ce('abbr');
                a.title = libtuj.FormatPrice(auc.buy, true) + ' ' + tuj.lang.total;
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
        }

        dest.appendChild(t);
    }

    this.load(tuj.params);
}

tuj.page_item = new TUJ_Item();
