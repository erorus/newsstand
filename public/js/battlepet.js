var TUJ_BattlePet = function ()
{
    var params;
    var lastResults = [];
    var speciesId;

    this.load = function (inParams)
    {
        params = {};
        for (var p in inParams) {
            if (inParams.hasOwnProperty(p)) {
                params[p] = inParams[p];
            }
        }

        speciesId = '' + params.id;
        if (speciesId.indexOf('.') > 0) {
            speciesId = ('' + params.id).substr(0, ('' + params.id).indexOf('.'));
        }

        var qs = {
            house: tuj.realms[params.realm].house,
            species: speciesId
        };
        var hash = JSON.stringify(qs);

        for (var x = 0; x < lastResults.length; x++) {
            if (lastResults[x].hash == hash) {
                BattlePetResult(false, lastResults[x].data);
                return;
            }
        }

        var battlePetPage = $('#battlepet-page')[0];
        if (!battlePetPage) {
            battlePetPage = libtuj.ce();
            battlePetPage.id = 'battlepet-page';
            battlePetPage.className = 'page';
            $('#main').append(battlePetPage);
        }

        $('#progress-page').show();

        if (tuj.hasApiKey()) {
            qs.e = 1;
        }
        var ajaxTries = 0;
        var ajaxSettings = {
            data: qs,
            dataFilter: qs.e ? tuj.ajaxDataFilter : undefined,
            success: function (d)
            {
                if (qs.e && (d instanceof Promise)) {
                    d.then(ajaxSettings.success);

                    return;
                }
                if (d.captcha) {
                    tuj.AskCaptcha(d.captcha);
                }
                else {
                    BattlePetResult(hash, d);
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
            url: 'api/battlepet.php'
        };

        $.ajax(ajaxSettings);
    };

    function BattlePetResult(hash, dta)
    {
        if (hash) {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10) {
                lastResults.shift();
            }
        }

        var battlePetPage = $('#battlepet-page');
        battlePetPage.empty();
        battlePetPage.show();

        var x, y;

        if (!dta.stats) {
            $('#page-title').empty().append(document.createTextNode(tuj.lang.battlepet + ': ' + params.id));
            tuj.SetTitle(tuj.lang.battlepet + ': ' + params.id);

            var h2 = libtuj.ce('h2');
            battlePetPage.append(h2);
            h2.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.notFound, tuj.lang.battlepet + ' ' + params.id)));

            return;
        }

        var ta = libtuj.ce('a');
        ta.href = 'http://' + tuj.lang.wowheadDomain + '.wowhead.com/npc=' + dta.stats.npc;
        ta.target = '_blank';
        ta.rel = 'noopener noreferrer';
        ta.className = 'battlepet'
        var timg = libtuj.ce('img');
        ta.appendChild(timg);
        timg.src = libtuj.IconURL(dta.stats.icon, 'large');
        var ttl = '[' + dta.stats['name_' + tuj.locale] + ']';
        ta.appendChild(document.createTextNode(ttl));

        $('#page-title').empty().append(ta);
        tuj.SetTitle(ttl);

        var d, cht, h, a;

        d = libtuj.ce();
        d.className = 'battlepet-stats';
        battlePetPage.append(d);
        BattlePetStats(dta, d);

        var consecSections = 0;

        if (dta.history.length >= 4) {
            d = libtuj.ce();
            d.className = 'chart-section section' + (consecSections++);
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.snapshots);
            d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.snapshotsDesc, tuj.lang.battlepet, tuj.validRegions[params.region] + ' ' + tuj.realms[params.realm].name)));
            cht = libtuj.ce();
            cht.className = 'chart history';
            d.appendChild(cht);
            battlePetPage.append(d);
            BattlePetHistoryChart(dta, cht);
        }

        if (dta.history.length >= 14) {
            d = libtuj.ce();
            d.className = 'chart-section section' + (consecSections++);
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.pricingHeatMap);
            d.appendChild(document.createTextNode(tuj.lang.pricingHeatMapDesc));
            cht = libtuj.ce();
            cht.className = 'chart heatmap';
            d.appendChild(cht);
            battlePetPage.append(d);
            BattlePetPriceHeatMap(dta, cht);

            d = libtuj.ce();
            d.className = 'chart-section section' + (consecSections++);
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.quantityHeatMap);
            d.appendChild(document.createTextNode(tuj.lang.quantityHeatMapDesc));
            cht = libtuj.ce();
            cht.className = 'chart heatmap';
            d.appendChild(cht);
            battlePetPage.append(d);
            BattlePetQuantityHeatMap(dta, cht);
        }

        if (dta.globalnow.length > 0) {
            d = libtuj.ce();
            d.className = 'chart-section section' + (consecSections++);
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.regionalPrices);
            d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.regionalPricesDesc, tuj.lang.battlepet, tuj.validRegions[params.region])));
            cht = libtuj.ce();
            cht.className = 'chart columns';
            d.appendChild(cht);
            battlePetPage.append(d);
            BattlePetGlobalNowColumns(dta, cht);
        }

        if (dta.regionbreeds) {
            var breedMap = {};
            var breedHitCount = 0;
            for (x in dta.regionbreeds) {
                if (!dta.regionbreeds.hasOwnProperty(x)) {
                    continue;
                }
                for (y in dta.regionbreeds[x]) {
                    if (!dta.regionbreeds[x].hasOwnProperty(y)) {
                        continue;
                    }
                    if (!breedMap[y]) {
                        breedMap[y] = true;
                        breedHitCount++;
                    }
                    if (breedHitCount > 1) {
                        break;
                    }
                }
                if (breedHitCount > 1) {
                    break;
                }
            }
            if (breedHitCount > 1) {
                d = libtuj.ce();
                d.className = 'chart-section section' + (consecSections++);
                h = libtuj.ce('h2');
                d.appendChild(h);
                $(h).text(tuj.lang.regionalBreeds);
                d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.regionalBreedsDesc, tuj.validRegions[params.region], tuj.lang.battlepet)));

                d.appendChild(libtuj.ce('br'));
                d.appendChild(libtuj.ce('br'));

                cht = libtuj.ce();
                cht.className = 'auctionlist';
                d.appendChild(cht);
                battlePetPage.append(d);
                BattlePetRegionalBreeds(dta, cht);
            }
        }

        battlePetPage.append(MakeNotificationsSection(dta, ttl, consecSections++));

        if (dta.auctions.length) {
            d = libtuj.ce();
            d.className = 'chart-section long';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text(tuj.lang.currentAuctions);
            d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.currentAuctionsDesc, tuj.lang.battlepet)));
            d.appendChild(libtuj.ce('br'));
            d.appendChild(libtuj.ce('br'));
            cht = libtuj.ce();
            cht.className = 'auctionlist';
            d.appendChild(cht);
            battlePetPage.append(d);
            BattlePetAuctions(dta, cht);
        }
    }

    function MakeNotificationsSection(data, fullPetName, consecSection)
    {
        var d = libtuj.ce();
        d.className = 'chart-section section' + consecSection;
        var h = libtuj.ce('h2');
        d.appendChild(h);
        $(h).text(tuj.lang.marketNotifications);
        if (tuj.LoggedInUserName()) {
            d.style.display = 'none';
            d.appendChild(document.createTextNode(tuj.lang.marketNotificationsDesc));
            var cht = libtuj.ce();
            cht.className = 'notifications-insert';
            d.appendChild(cht);
            GetBattlePetNotificationsList(speciesId, d);
        } else {
            d.className += ' logged-out-only';

            var globalQty = 0;
            for (var x = 0, row; row = data.globalnow[x]; x++) {
                globalQty += row.quantity;
            }

            if (globalQty < 100) {
                d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.wantToKnowAvailAnywhere, fullPetName) + ' '));
            } else if (data.stats) {
                if (data.stats.quantity < 5) {
                    d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.wantToKnowAvail, fullPetName) + ' '));
                } else if (data.history.length > 8) {
                    var prices = [];
                    for (var x = 0; x < data.history.length; x++) {
                        prices.push(data.history[x].price);
                    }
                    var priceMean = libtuj.Mean(prices);
                    var priceStdDev = libtuj.StdDev(prices, priceMean);

                    if (priceMean > 10000 && priceMean > (priceStdDev / 2)) {
                        d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.wantToKnowPrice, fullPetName, libtuj.FormatPrice(priceMean - (priceStdDev / 2), true, true)) + ' '));
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

    function BattlePetStats(data, dest)
    {
        var t, tr, td, abbr;

        var spacerColSpan = 2;

        t = libtuj.ce('table');
        dest.appendChild(t);

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'available';
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(tuj.lang.availableQuantity));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatQuantity(data.stats.quantity));

        if (data.stats.quantity == 0) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'last-seen';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode(tuj.lang.lastSeen));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(data.stats.lastseen ? libtuj.FormatDate(data.stats.lastseen) : document.createTextNode(tuj.lang.unknown));
        }

        if (data.stats.price) {
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
            td.appendChild(libtuj.FormatPrice(data.stats.price));
        }

        var prices = [], x;

        if (data.history.length > 8) {
            for (x = 0; x < data.history.length; x++) {
                prices.push(data.history[x].price);
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
        }

        if (data.globalnow.length) {
            var globalStats = {
                quantity: 0,
                prices: [],
                lastseen: 0
            };

            var headerPrefix = tuj.validRegions[params.region] + ' ';
            var row;
            for (x = 0; row = data.globalnow[x]; x++) {
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
        }
    }

    function GetBattlePetNotificationsList(speciesId, mainDiv)
    {
        var self = this;
        tuj.SendCSRFProtectedRequest({
            data: {'getspecies': speciesId},
            success: BattlePetNotificationsList.bind(self, speciesId, mainDiv),
        });
    }

    function BattlePetNotificationsList(speciesId, mainDiv, dta)
    {
        var dest = $(mainDiv).find('.notifications-insert');
        dest.empty();
        dest = dest[0];

        var ids = [];
        for (var k in dta.watches) {
            if (dta.watches.hasOwnProperty(k)) {
                ids.push(k);
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
                $(btn).on('click', BattlePetNotificationsDel.bind(btn, mainDiv, speciesId, n.seq));
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
        $(selBox).on('change', BattlePetNotificationsTypeChange);
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
        $(btn).on('click', BattlePetNotificationsAdd.bind(btn, mainDiv, speciesId, regionBox, underOver, qty, false));
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
        $(btn).on('click', BattlePetNotificationsAdd.bind(btn, mainDiv, speciesId, regionBox, underOver, null, price));
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
        $(btn).on('click', BattlePetNotificationsAdd.bind(btn, mainDiv, speciesId, regionBox, underOver, qty, price));
        d.appendChild(btn);

        $(mainDiv).show();
    }

    function BattlePetNotificationsTypeChange()
    {
        var $parent = $(this.parentNode);
        $parent.find('.notification-type-form').hide();
        $parent.find('.notification-type-form-'+this.options[this.selectedIndex].value).show();
    }

    function BattlePetNotificationsAdd(mainDiv, speciesId, regionBox, directionBox, qtyBox, priceBox)
    {
        var self = this;
        var o = {
            'setwatch': 'species',
            'id': speciesId,
            'subid': -1,
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
            success: BattlePetNotificationsList.bind(self, speciesId, mainDiv),
        });
    }

    function BattlePetNotificationsDel(mainDiv, speciesId, id)
    {
        var self = this;
        tuj.SendCSRFProtectedRequest({
            data: {'deletewatch': id},
            success: BattlePetNotificationsList.bind(self, speciesId, mainDiv),
        });
    }

    function BattlePetHistoryChart(data, dest)
    {
        var hcdata = {price: [], priceMaxVal: 0, quantity: [], quantityMaxVal: 0, tooltip: {}};

        var allPrices = [];
        for (var x = 0; x < data.history.length; x++) {
            hcdata.price.push([data.history[x].snapshot * 1000, data.history[x].price]);
            hcdata.quantity.push([data.history[x].snapshot * 1000, data.history[x].quantity]);
            if (data.history[x].quantity > hcdata.quantityMaxVal) {
                hcdata.quantityMaxVal = data.history[x].quantity;
            }
            allPrices.push(data.history[x].price);
            hcdata.tooltip[data.history[x].snapshot * 1000] = [data.history[x].price, data.history[x].quantity];
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

        $(dest).highcharts({
            chart: {
                zoomType: 'x',
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background,
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
                    return tr;
                    // &lt;br/&gt;&lt;span style="color: #990000"&gt;Quantity: '+this.points[1].y+'&lt;/span&gt;<xsl:if test="battlepetgraphs/d[@matsprice != '']">&lt;br/&gt;&lt;span style="color: #999900"&gt;Materials Price: '+this.points[2].y.toFixed(2)+'g&lt;/span&gt;</xsl:if>';
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
            ]
        });
    }

    function BattlePetPriceHeatMap(data, dest)
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
        };

        var d, wkdy, hr, lastprice;
        for (wkdy = 0; wkdy < hcdata.categories.y.length; wkdy++) {
            hcdata.days[wkdy] = {};
            for (hr = 0; hr < hcdata.categories.x.length; hr++) {
                hcdata.days[wkdy][hr] = [];
            }
        }

        for (var x = 0; x < data.history.length; x++) {
            if (typeof lastprice == 'undefined') {
                lastprice = data.history[x].price;
            }

            var d = new Date(data.history[x].snapshot * 1000);
            wkdy = 6 - d.getDay();
            hr = Math.floor(d.getHours() * hcdata.categories.x.length / 24);
            hcdata.days[wkdy][hr].push(data.history[x].price);
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

    function BattlePetQuantityHeatMap(data, dest)
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

        for (var x = 0; x < data.history.length; x++) {
            if (typeof lastqty == 'undefined') {
                lastqty = data.history[x].quantity;
            }

            var d = new Date(data.history[x].snapshot * 1000);
            wkdy = 6 - d.getDay();
            hr = Math.floor(d.getHours() * hcdata.categories.x.length / 24);
            hcdata.days[wkdy][hr].push(data.history[x].quantity);
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

    function BattlePetGlobalNowColumns(data, dest)
    {
        var hcdata = {categories: [], price: [], quantity: [], lastseen: [], houses: []};
        var allPrices = [];
        var allQuantities = [];
        data.globalnow.sort(function (a, b)
        {
            return (b.price - a.price) || (b.quantity - a.quantity);
        });

        var isThisHouse = false;
        for (var x = 0; x < data.globalnow.length; x++) {
            isThisHouse = data.globalnow[x].house == tuj.realms[params.realm].house;

            hcdata.categories.push(data.globalnow[x].house);
            hcdata.quantity.push(data.globalnow[x].quantity);
            hcdata.price.push(isThisHouse ? {
                y: data.globalnow[x].price,
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
                }} : data.globalnow[x].price);
            hcdata.lastseen.push(data.globalnow[x].lastseen);
            hcdata.houses.push(data.globalnow[x].house);

            allQuantities.push(data.globalnow[x].quantity);
            allPrices.push(data.globalnow[x].price);
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

    function BattlePetAuctions(data, dest)
    {
        var t, tr, td;
        t = libtuj.ce('table');
        t.className = 'auctionlist';

        tr = libtuj.ce('tr');
        t.appendChild(tr);

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

        data.auctions.sort(function (a, b)
        {
            return Math.floor(a.buy / a.quantity) - Math.floor(b.buy / b.quantity) ||
                Math.floor(a.bid / a.quantity) - Math.floor(b.bid / b.quantity) ||
                a.quantity - b.quantity;
        });

        var s, a;
        for (var x = 0, auc; auc = data.auctions[x]; x++) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

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
                s = libtuj.ce('span');
            }
            td.appendChild(s);
        }

        dest.appendChild(t);
    }

    function BattlePetRegionalBreeds(dta, dest)
    {
        var sortedBreeds = [], sortedHouses = [];
        var seenBreeds = {};
        for (var house in dta.regionbreeds) {
            if (!dta.regionbreeds.hasOwnProperty(house)) {
                continue;
            }
            sortedHouses.push(house);
            for (var breed in dta.regionbreeds[house]) {
                if (!dta.regionbreeds[house].hasOwnProperty(breed)) {
                    continue;
                }
                if (!seenBreeds.hasOwnProperty(breed)) {
                    seenBreeds[breed] = {};
                    sortedBreeds.push(parseInt(breed,10));
                }
                seenBreeds[breed] = true;
            }
        }

        var houseToRealm = {};
        for (var x in tuj.realms) {
            house = tuj.realms[x].house;
            if (!houseToRealm.hasOwnProperty(house) ||
                (tuj.realms[houseToRealm[house]].name.localeCompare(tuj.realms[x].name) > 0)) {
                houseToRealm[house] = x;
            }
        }

        sortedBreeds.sort();
        sortedHouses.sort(function(a,b){
            return tuj.realms[houseToRealm[a]].name.localeCompare(tuj.realms[houseToRealm[b]].name);
        });

        var t, tr, td;
        t = libtuj.ce('table');
        t.className = 'auctionlist';

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.addEventListener('click', BreedTableSort);
        td.style.cursor = 'pointer';
        td.className = 'realm';
        $(td).text(tuj.lang['realms' + tuj.validRegions[params.region]]);

        for (var x = 0; x < sortedBreeds.length; x++) {
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.addEventListener('click', BreedTableSort);
            td.style.cursor = 'pointer';
            td.className = 'price';
            $(td).text(tuj.lang.breedsLookup[sortedBreeds[x]]);
        }

        var s, a, realm, y = 0;
        for (house in dta.regionbreeds) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'realm';
            td.dataset.sortValue = y++;
            td.style.fontSize = '75%';

            a = libtuj.ce('a');
            td.appendChild(a);

            a.href = tuj.BuildHash({'realm': houseToRealm[house], 'page': 'battlepet', 'id': params.id})
            a.innerHTML = libtuj.GetRealmsForHouse(house);

            for (x = 0; x < sortedBreeds.length; x++) {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.className = 'price';

                if (!dta.regionbreeds[house].hasOwnProperty(sortedBreeds[x])) {
                    td.dataset.sortValue = 0;
                    continue;
                }

                td.appendChild(libtuj.FormatPrice(td.dataset.sortValue = dta.regionbreeds[house][sortedBreeds[x]]));
            }
        }

        dest.appendChild(t);
    }

    function BreedTableSort() {
        var th = this;
        var t = th.parentNode.parentNode;

        for (var cellIndex = 0; cellIndex < th.parentNode.childNodes.length && th.parentNode.childNodes[cellIndex] != th; cellIndex++);

        var trs = Array.from(t.getElementsByTagName('tr'));
        trs.splice(0, 1);

        trs.sort(function(a,b){
            return (cellIndex ? b.childNodes[cellIndex].dataset.sortValue - a.childNodes[cellIndex].dataset.sortValue : 0) ||
                a.childNodes[0].dataset.sortValue - b.childNodes[0].dataset.sortValue;
        });

        for (x = 0; x < trs.length; x++) {
            trs[x].parentNode.appendChild(trs[x]);
        }
    }

    this.load(tuj.params);
}

tuj.page_battlepet = new TUJ_BattlePet();
