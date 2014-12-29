var TUJ_Item = function ()
{
    var params;
    var lastResults = [];
    var itemId;
    var bonusSet, bonusUrl;
    var bonusSets;

    this.load = function (inParams)
    {
        params = {};
        for (var p in inParams) {
            if (inParams.hasOwnProperty(p)) {
                params[p] = inParams[p];
            }
        }

        itemId = '' + params.id;
        bonusSet = 0;
        bonusUrl = '';

        if (itemId.indexOf('.') > 0) {
            itemId = ('' + params.id).substr(0, ('' + params.id).indexOf('.'));
            bonusUrl = ('' + params.id).substr(('' + params.id).indexOf('.') + 1);
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
            complete: function ()
            {
                $('#progress-page').hide();
            },
            url: 'api/item.php'
        });
    }

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

        console.log(dta);

        if (!dta.stats) {
            $('#page-title').empty().append(document.createTextNode('Item: ' + itemId));
            tuj.SetTitle('Item: ' + params.id);

            var h2 = libtuj.ce('h2');
            itemPage.append(h2);
            h2.appendChild(document.createTextNode('Item ' + itemId + ' not found.'));

            return;
        }

        bonusSets = [];
        for (var bset in dta.stats) {
            bonusSets.push(bset);
            if (bonusSets.length == 1) {
                bonusSet = bset;
            }
            if (dta.stats[bset].bonusurl == bonusUrl) {
                bonusSet = bset;
            }
        }
        if (dta.stats[bonusSet].bonusurl != bonusUrl) {
            tuj.SetParams({id: '' + itemId + (dta.stats[bonusSet].bonusurl ? ('.' + dta.stats[bonusSet].bonusurl) : '')});
            bonusUrl = dta.stats[bonusSet].bonusurl;
        }

        var ta = libtuj.ce('a');
        ta.href = 'http://www.wowhead.com/item=' + itemId + (bonusUrl ? '&bonus=' + bonusUrl.replace('.', ':') : '');
        ta.target = '_blank';
        ta.className = 'item'
        var timg = libtuj.ce('img');
        ta.appendChild(timg);
        timg.src = tujCDNPrefix + 'icon/large/' + dta.stats[bonusSet].icon + '.jpg';
        ta.appendChild(document.createTextNode('[' + dta.stats[bonusSet].name + ']' + (dta.stats[bonusSet].bonustag ? ' ' + dta.stats[bonusSet].bonustag : '')));

        $('#page-title').empty().append(ta);
        tuj.SetTitle('[' + dta.stats[bonusSet].name + ']' + (dta.stats[bonusSet].bonustag ? ' ' + dta.stats[bonusSet].bonustag : ''));

        var d, cht, h;

        d = libtuj.ce();
        d.className = 'item-stats';
        itemPage.append(d);
        ItemStats(dta, d);

        return;

        if (dta.history.length >= 4) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Snapshots');
            d.appendChild(document.createTextNode('Here is the available quantity and market price of the item for every ' + tuj.region + ' ' + tuj.realms[params.realm].name + ' auction house snapshot seen recently.'));
            cht = libtuj.ce();
            cht.className = 'chart history';
            d.appendChild(cht);
            itemPage.append(d);
            ItemHistoryChart(dta, cht);
        }

        if (dta.monthly.length >= 14) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Daily Summary');
            d.appendChild(document.createTextNode('Here is the maximum available quantity, and the market price at that time, for the item each day. The regional average price for each day is included for comparison.'));
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
            $(h).text('Daily Details');
            d.appendChild(document.createTextNode('This chart is similar to the Daily Summary, but includes the "OHLC" market prices for the item each day, along with the minimum, average, and maximum available quantity.'));
            cht = libtuj.ce();
            cht.className = 'chart daily';
            d.appendChild(cht)
            itemPage.append(d);
            ItemDailyChart(dta, cht);
        }

        if (dta.history.length >= 14) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Pricing Heat Map');
            d.appendChild(document.createTextNode('This heat map helps to identify if prices have a pattern based on the time of day.'));
            cht = libtuj.ce();
            cht.className = 'chart heatmap';
            d.appendChild(cht);
            itemPage.append(d);
            ItemPriceHeatMap(dta, cht);

            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Quantity Heat Map');
            d.appendChild(document.createTextNode('This heat map shows you the average available quantity at different times of the day.'));
            cht = libtuj.ce();
            cht.className = 'chart heatmap';
            d.appendChild(cht);
            itemPage.append(d);
            ItemQuantityHeatMap(dta, cht);

            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Age Heat Map');
            d.appendChild(document.createTextNode('This heat map shows you the average age of auctions at or below the market price, at different times of the day. Younger age means more posting activity by sellers.'));
            cht = libtuj.ce();
            cht.className = 'chart heatmap';
            d.appendChild(cht);
            itemPage.append(d);
            ItemAgeHeatMap(dta, cht);
        }

        itemPage.append(libtuj.Ads.Add('3753400314'));

        if (dta.globalmonthly.length >= 28) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Regional Daily Summary');
            d.appendChild(document.createTextNode('Here is the total available quantity, and the average market price, for the item each day across all realms in the ' + tuj.region + '.'));
            cht = libtuj.ce();
            cht.className = 'chart monthly';
            d.appendChild(cht);
            itemPage.append(d);
            ItemGlobalMonthlyChart(dta, cht);
        }

        if (dta.globalnow.length > 0) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Current Regional Prices');
            d.appendChild(document.createTextNode('The Regional Prices chart is sorted by price, and shows the price and quantity available of this item on all realms in the ' + tuj.region + '.'));
            cht = libtuj.ce();
            cht.className = 'chart columns';
            d.appendChild(cht);
            itemPage.append(d);
            ItemGlobalNowColumns(dta, cht);

            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Price/Population Scatter Plot');
            d.appendChild(document.createTextNode('This scatter plot has the same data as above, but shows the price relative to the realm population, provided by '));
            var a = libtuj.ce('a');
            d.appendChild(a);
            d.appendChild(document.createTextNode('.'));
            a.href = 'https://realmpop.com/' + tuj.region.toLowerCase() + '.html';
            a.style.textDecoration = 'underline';
            a.appendChild(document.createTextNode('Realm Pop'));

            cht = libtuj.ce();
            cht.className = 'chart scatter';
            d.appendChild(cht);
            itemPage.append(d);
            ItemGlobalNowScatter(dta, cht);

        }

        if (dta.auctions.length) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);

            var a = libtuj.ce('a');
            h.appendChild(a);
            a.href = 'https://' + tuj.region.toLowerCase() + '.battle.net/wow/en/vault/character/auction/browse?sort=unitBuyout&itemId=' + params.id + '&start=0&end=40';
            $(a).text('Current Auctions');
            d.appendChild(document.createTextNode('Here is the full list of auctions for this item from the latest snapshot. Click a seller name for details on that seller.'));
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

    function ItemStats(data, dest)
    {
        var t, tr, td, abbr;

        var stack = data.stats[bonusSet].stacksize > 1 ? data.stats[bonusSet].stacksize : 0;
        var spacerColSpan = stack ? 3 : 2;

        stack = 0; // disable stack size since they're an unusable "200"

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
            td.appendChild(document.createTextNode('One'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.style.whiteSpace = 'nowrap';
            td.appendChild(document.createTextNode('Stack of ' + stack));
        }

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'available';
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode('Available Quantity'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatQuantity(data.stats[bonusSet].quantity));
        if (stack) {
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatQuantity(Math.floor(data.stats[bonusSet].quantity / stack)));
        }

        if (data.stats[bonusSet].quantity == 0) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'last-seen';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode('Last Seen'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.colSpan = stack ? 2 : 1;
            td.appendChild(libtuj.FormatDate(data.stats[bonusSet].lastseen));
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
        td.appendChild(document.createTextNode('Current Price'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatPrice(data.stats[bonusSet].price));
        if (stack) {
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(data.stats[bonusSet].price * stack));
        }

        var prices = [], ages = [], x;

        if (data.history.length > 8) {
            for (x = 0; x < data.history.length; x++) {
                prices.push(data.history[x].price);
                ages.push(data.history[x].age);
            }
        }

        if (prices.length) {
            var median;
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'median-price';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode('Median Price'));
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
            td.appendChild(document.createTextNode('Mean Price'));
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
            td.appendChild(document.createTextNode('Standard Deviation'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            if (std / mn > 0.33) {
                abbr = libtuj.ce('abbr');
                abbr.title = 'Market price is highly volatile!';
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

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'spacer';
            td = libtuj.ce('td');
            td.colSpan = spacerColSpan;
            tr.appendChild(td);

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'mean-age';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode('Typical Auction Age'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.colSpan = stack ? 2 : 1;
            td.appendChild(libtuj.FormatAge(libtuj.Mean(ages)));

            ages.sort(function (a, b)
            {
                return a - b;
            });

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'max-age';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode('Max Auction Age'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.colSpan = stack ? 2 : 1;
            td.appendChild(libtuj.FormatAge(ages[ages.length - 1]));
        }

        if (data.globalnow.length) {
            var globalStats = {
                quantity: 0,
                prices: [],
                lastseen: 0
            };

            var headerPrefix = tuj.region + ' ';
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
            td.appendChild(document.createTextNode(headerPrefix + 'Quantity'));
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
            td.appendChild(document.createTextNode(headerPrefix + 'Median Price'));
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
            td.appendChild(document.createTextNode(headerPrefix + 'Mean Price'));
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

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        tr.className = 'vendor';
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode('Sell to Vendor'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(data.stats[bonusSet].selltovendor ? libtuj.FormatPrice(data.stats[bonusSet].selltovendor) : document.createTextNode('Cannot'));
        if (stack) {
            if (data.stats[bonusSet].selltovendor) {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(data.stats[bonusSet].selltovendor * stack));
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
        td.appendChild(document.createTextNode('48hr Listing Fee'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatPrice(Math.max(100, data.stats[bonusSet].selltovendor ? data.stats[bonusSet].selltovendor * 0.6 : 0)));
        if (stack) {
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(Math.max(100, data.stats[bonusSet].selltovendor ? data.stats[bonusSet].selltovendor * 0.6 * stack : 0)));
        }

        dest.appendChild(libtuj.Ads.Add('9943194718', 'box'));
    }

    function ItemHistoryChart(data, dest)
    {
        var hcdata = {price: [], priceMaxVal: 0, quantity: [], quantityMaxVal: 0};

        var allPrices = [];
        for (var x = 0; x < data.history.length; x++) {
            hcdata.price.push([data.history[x].snapshot * 1000, data.history[x].price]);
            hcdata.quantity.push([data.history[x].snapshot * 1000, data.history[x].quantity]);
            if (data.history[x].quantity > hcdata.quantityMaxVal) {
                hcdata.quantityMaxVal = data.history[x].quantity;
            }
            allPrices.push(data.history[x].price);
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
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },
            title: {
                text: null
            },
            subtitle: {
                text: document.ontouchstart === undefined ?
                    'Click and drag in the plot area to zoom in' :
                    'Pinch the chart to zoom in',
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
                        text: 'Market Price',
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
                        text: 'Quantity Available',
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
                    var tr = '<b>' + Highcharts.dateFormat('%a %b %d, %I:%M%P', this.x) + '</b>';
                    tr += '<br><span style="color: #000099">Market Price: ' + libtuj.FormatPrice(this.points[0].y, true) + '</span>';
                    tr += '<br><span style="color: #990000">Quantity: ' + libtuj.FormatQuantity(this.points[1].y, true) + '</span>';
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
                    name: 'Market Price',
                    color: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    lineColor: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    fillColor: tujConstants.siteColors[tuj.colorTheme].bluePriceFill,
                    data: hcdata.price
                },
                {
                    type: 'line',
                    name: 'Quantity Available',
                    yAxis: 1,
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantity,
                    data: hcdata.quantity
                }
            ]
        });
    }

    function ItemMonthlyChart(data, dest)
    {
        var hcdata = {price: [], priceMaxVal: 0, quantity: [], quantityMaxVal: 0, globalprice: []};

        var allPrices = [], dt, dtParts;
        var offset = (new Date()).getTimezoneOffset() * 60 * 1000;
        var earliestDate = Date.now();
        for (var x = 0; x < data.monthly.length; x++) {
            dtParts = data.monthly[x].date.split('-');
            dt = Date.UTC(dtParts[0], parseInt(dtParts[1], 10) - 1, dtParts[2]) + offset;
            if (dt < earliestDate) {
                earliestDate = dt;
            }
            hcdata.price.push([dt, data.monthly[x].silver * 100]);
            hcdata.quantity.push([dt, data.monthly[x].quantity]);
            if (data.monthly[x].quantity > hcdata.quantityMaxVal) {
                hcdata.quantityMaxVal = data.monthly[x].quantity;
            }
            allPrices.push(data.monthly[x].silver * 100);
        }
        for (var x = 0; x < data.globalmonthly.length; x++) {
            dtParts = data.globalmonthly[x].date.split('-');
            dt = Date.UTC(dtParts[0], parseInt(dtParts[1], 10) - 1, dtParts[2]) + offset;
            if (dt < earliestDate) {
                continue;
            }
            hcdata.globalprice.push([dt, data.globalmonthly[x].silver * 100]);
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
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },
            title: {
                text: null
            },
            subtitle: {
                text: document.ontouchstart === undefined ?
                    'Click and drag in the plot area to zoom in' :
                    'Pinch the chart to zoom in',
                style: {
                    color: tujConstants.siteColors[tuj.colorTheme].text
                }
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
                        text: 'Market Price',
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
                        text: 'Quantity Available',
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
                    var tr = '<b>' + Highcharts.dateFormat('%a %b %d', this.x) + '</b>';
                    if (this.points[1]) {
                        tr += '<br><span style="color: #000099">Market Price: ' + libtuj.FormatPrice(this.points[1].y, true) + '</span>';
                    }
                    if (this.points[0]) {
                        tr += '<br><span style="color: #009900">Region Price: ' + libtuj.FormatPrice(this.points[0].y, true) + '</span>';
                    }
                    if (this.points[2]) {
                        tr += '<br><span style="color: #990000">Quantity: ' + libtuj.FormatQuantity(this.points[2].y, true) + '</span>';
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
                    name: 'Market Price',
                    color: tujConstants.siteColors[tuj.colorTheme].greenPrice,
                    lineColor: tujConstants.siteColors[tuj.colorTheme].greenPrice,
                    fillColor: tujConstants.siteColors[tuj.colorTheme].greenPriceFill,
                    data: hcdata.globalprice
                },
                {
                    type: 'area',
                    name: 'Market Price',
                    color: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    lineColor: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    fillColor: tujConstants.siteColors[tuj.colorTheme].bluePriceFillAlpha,
                    data: hcdata.price
                },
                {
                    type: 'line',
                    name: 'Quantity Available',
                    yAxis: 1,
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantity,
                    data: hcdata.quantity
                }
            ]
        });
    }

    function ItemGlobalMonthlyChart(data, dest)
    {
        var hcdata = {price: [], priceMaxVal: 0, quantity: [], quantityMaxVal: 0};

        var allPrices = [], dt, dtParts;
        var offset = (new Date()).getTimezoneOffset() * 60 * 1000;
        var earliestDate = Date.now();
        for (var x = 0; x < data.globalmonthly.length; x++) {
            dtParts = data.globalmonthly[x].date.split('-');
            dt = Date.UTC(dtParts[0], parseInt(dtParts[1], 10) - 1, dtParts[2]) + offset;
            if (dt < earliestDate) {
                earliestDate = dt;
            }
            hcdata.price.push([dt, data.globalmonthly[x].silver * 100]);
            hcdata.quantity.push([dt, data.globalmonthly[x].quantity]);
            if (data.globalmonthly[x].quantity > hcdata.quantityMaxVal) {
                hcdata.quantityMaxVal = data.globalmonthly[x].quantity;
            }
            allPrices.push(data.globalmonthly[x].silver * 100);
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
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background
            },
            title: {
                text: null
            },
            subtitle: {
                text: document.ontouchstart === undefined ?
                    'Click and drag in the plot area to zoom in' :
                    'Pinch the chart to zoom in',
                style: {
                    color: tujConstants.siteColors[tuj.colorTheme].text
                }
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
                        text: 'Market Price',
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
                        text: 'Quantity Available',
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
                    var tr = '<b>' + Highcharts.dateFormat('%a %b %d', this.x) + '</b>';
                    if (this.points[0]) {
                        tr += '<br><span style="color: #000099">Region Price: ' + libtuj.FormatPrice(this.points[0].y, true) + '</span>';
                    }
                    if (this.points[1]) {
                        tr += '<br><span style="color: #990000">Quantity: ' + libtuj.FormatQuantity(this.points[1].y, true) + '</span>';
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
                    name: 'Market Price',
                    color: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    lineColor: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    fillColor: tujConstants.siteColors[tuj.colorTheme].bluePriceFillAlpha,
                    data: hcdata.price
                },
                {
                    type: 'line',
                    name: 'Quantity Available',
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
                enabled: false
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
            subtitle: {
                text: document.ontouchstart === undefined ?
                    'Click and drag in the plot area to zoom in' :
                    'Pinch the chart to zoom in',
                style: {
                    color: tujConstants.siteColors[tuj.colorTheme].text
                }

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
                        text: 'Market Price',
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
                    min: 0,
                    max: hcdata.ohlcMaxVal
                },
                {
                    title: {
                        text: 'Quantity Available',
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
                    var tr = '<b>' + Highcharts.dateFormat('%a %b %d', this.x) + '</b>';
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
                    name: 'Market Price',
                    upColor: tujConstants.siteColors[tuj.colorTheme].background,
                    color: tujConstants.siteColors[tuj.colorTheme].bluePriceFill,
                    lineColor: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    data: hcdata.ohlc
                },
                {
                    type: 'line',
                    name: 'Quantity',
                    yAxis: 1,
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantity,
                    data: hcdata.quantity,
                    lineWidth: 2
                },
                {
                    type: 'arearange',
                    name: 'Quantity Range',
                    yAxis: 1,
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantityFillLight,
                    data: hcdata.quantityRange
                },
                {
                    type: 'line',
                    name: 'Market Price',
                    color: tujConstants.siteColors[tuj.colorTheme].greenPriceDim,
                    data: hcdata.price
                }
            ]
        });
    }

    function ItemPriceHeatMap(data, dest)
    {
        var hcdata = {minVal: undefined, maxVal: 0, days: {}, heat: [], categories: {
            x: [
                'Midnight - 3am', '3am - 6am', '6am - 9am', '9am - Noon', 'Noon - 3pm', '3pm - 6pm', '6pm - 9pm',
                '9pm - Midnight'
            ],
            y: ['Saturday', 'Friday', 'Thursday', 'Wednesday', 'Tuesday', 'Monday', 'Sunday']
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

        var d, wkdy, hr, lastprice;
        for (wkdy = 0; wkdy <= 6; wkdy++) {
            hcdata.days[wkdy] = {};
            for (hr = 0; hr <= 7; hr++) {
                hcdata.days[wkdy][hr] = [];
            }
        }

        for (var x = 0; x < data.history.length; x++) {
            if (typeof lastprice == 'undefined') {
                lastprice = data.history[x].price;
            }

            var d = new Date(data.history[x].snapshot * 1000);
            wkdy = 6 - d.getDay();
            hr = Math.floor(d.getHours() / 3);
            hcdata.days[wkdy][hr].push(data.history[x].price);
        }

        var p;
        for (wkdy = 0; wkdy <= 6; wkdy++) {
            for (hr = 0; hr <= 7; hr++) {
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
                    name: 'Market Price',
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
                            return '' + libtuj.FormatPrice(this.point.value * 10000, true);
                        }
                    }
                }
            ]

        });
    }

    function ItemQuantityHeatMap(data, dest)
    {
        var hcdata = {minVal: undefined, maxVal: 0, days: {}, heat: [], categories: {
            x: [
                'Midnight - 3am', '3am - 6am', '6am - 9am', '9am - Noon', 'Noon - 3pm', '3pm - 6pm', '6pm - 9pm',
                '9pm - Midnight'
            ],
            y: ['Saturday', 'Friday', 'Thursday', 'Wednesday', 'Tuesday', 'Monday', 'Sunday']
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
        for (wkdy = 0; wkdy <= 6; wkdy++) {
            hcdata.days[wkdy] = {};
            for (hr = 0; hr <= 7; hr++) {
                hcdata.days[wkdy][hr] = [];
            }
        }

        for (var x = 0; x < data.history.length; x++) {
            if (typeof lastqty == 'undefined') {
                lastqty = data.history[x].quantity;
            }

            var d = new Date(data.history[x].snapshot * 1000);
            wkdy = 6 - d.getDay();
            hr = Math.floor(d.getHours() / 3);
            hcdata.days[wkdy][hr].push(data.history[x].quantity);
        }

        var p;
        for (wkdy = 0; wkdy <= 6; wkdy++) {
            for (hr = 0; hr <= 7; hr++) {
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
                    name: 'Quantity',
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

    function ItemAgeHeatMap(data, dest)
    {
        var hcdata = {minVal: undefined, maxVal: 0, days: {}, heat: [], categories: {
            x: [
                'Midnight - 3am', '3am - 6am', '6am - 9am', '9am - Noon', 'Noon - 3pm', '3pm - 6pm', '6pm - 9pm',
                '9pm - Midnight'
            ],
            y: ['Saturday', 'Friday', 'Thursday', 'Wednesday', 'Tuesday', 'Monday', 'Sunday']
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
        for (wkdy = 0; wkdy <= 6; wkdy++) {
            hcdata.days[wkdy] = {};
            for (hr = 0; hr <= 7; hr++) {
                hcdata.days[wkdy][hr] = [];
            }
        }

        for (var x = 0; x < data.history.length; x++) {
            if (typeof lastqty == 'undefined') {
                lastqty = data.history[x].quantity;
            }

            var d = new Date(data.history[x].snapshot * 1000);
            wkdy = 6 - d.getDay();
            hr = Math.floor(d.getHours() / 3);
            hcdata.days[wkdy][hr].push(data.history[x].age);
        }

        var p;
        for (wkdy = 0; wkdy <= 6; wkdy++) {
            for (hr = 0; hr <= 7; hr++) {
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
                maxColor: tujConstants.siteColors[tuj.colorTheme].greenPriceBackground
            },

            legend: {
                enabled: false
            },

            tooltip: {
                enabled: false
            },

            series: [
                {
                    name: 'Age',
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
                            return '' + libtuj.FormatAge(this.point.value, true);
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
                    'Click and drag in the plot area to zoom in' :
                    'Pinch the chart to zoom in',
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
                        text: 'Market Price',
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
                        text: 'Quantity',
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
                    tr += '<br><span style="color: #000099">Market Price: ' + libtuj.FormatPrice(this.points[0].y, true) + '</span>';
                    tr += '<br><span style="color: #990000">Quantity: ' + libtuj.FormatQuantity(this.points[1].y, true) + '</span>';
                    tr += '<br><span style="color: #990000">Last seen: ' + libtuj.FormatDate(hcdata.lastseen[this.x], true) + '</span>';
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
                    name: 'Market Price',
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
                    name: 'Quantity',
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
        for (var x = 0; x < data.globalnow.length; x++) {
            if (data.globalnow[x].house == tuj.realms[params.realm].house) {
                o = {
                    x: libtuj.GetHousePopulation(data.globalnow[x].house),
                    y: data.globalnow[x].price,
                    id: x,
                    marker: {
                        symbol: 'diamond'
                    },
                    color: tujConstants.siteColors[tuj.colorTheme].redQuantity
                };
            } else {
                o = {
                    x: libtuj.GetHousePopulation(data.globalnow[x].house),
                    y: data.globalnow[x].price,
                    id: x
                };
                if (data.globalnow[x].quantity == 0) {
                    o.color = tujConstants.siteColors[tuj.colorTheme].bluePriceFill;
                }
            }

            hcdata.price.push(o);
            hcdata.houses[x] = data.globalnow[x].house;
            hcdata.quantity[x] = data.globalnow[x].quantity;
            hcdata.lastseen[x] = data.globalnow[x].lastseen;

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
                    text: 'Population',
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
                    text: 'Market Price',
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
                    tr += '<br><span style="color: #000099">Market Price: ' + libtuj.FormatPrice(this.point.y, true) + '</span>';
                    tr += '<br><span style="color: #990000">Quantity: ' + libtuj.FormatQuantity(hcdata.quantity[this.point.id], true) + '</span>';
                    tr += '<br><span style="color: #990000">Last seen: ' + libtuj.FormatDate(hcdata.lastseen[this.point.id], true) + '</span>';
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
                    name: 'Market Price',
                    color: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                    data: hcdata.price,
                    yAxis: 0,
                    zIndex: 2
                }
            ]
        });
    }

    function ItemAuctions(data, dest)
    {
        var hasRand = false, x, auc;
        for (x = 0; (!hasRand) && (auc = data.auctions[x]); x++) {
            hasRand |= !!auc.rand;
        }

        var t, tr, td;
        t = libtuj.ce('table');
        t.className = 'auctionlist';

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        if (hasRand) {
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.className = 'name';
            $(td).text('Name');
        }

        if (data.stats[bonusSet].stacksize > 1) {
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.className = 'quantity';
            $(td).text('Quantity');
        }

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'price';
        $(td).text('Bid Each');

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'price';
        $(td).text('Buyout Each');

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'seller';
        $(td).text('Seller');

        data.auctions.sort(function (a, b)
        {
            return Math.floor(a.buy / a.quantity) - Math.floor(b.buy / b.quantity) ||
                Math.floor(a.bid / a.quantity) - Math.floor(b.bid / b.quantity) ||
                a.quantity - b.quantity ||
                (tuj.realms[a.sellerrealm] ? tuj.realms[a.sellerrealm].name : '').localeCompare(tuj.realms[b.sellerrealm] ? tuj.realms[b.sellerrealm].name : '') ||
                a.sellername.localeCompare(b.sellername);
        });

        var s, a, stackable = data.stats[bonusSet].stacksize > 1;
        for (x = 0; auc = data.auctions[x]; x++) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            if (hasRand) {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.className = 'name';
                a = libtuj.ce('a');
                a.rel = 'item=' + data.stats[bonusSet].id + (auc.rand ? '&rand=' + auc.rand : '');
                a.href = tuj.BuildHash({page: 'item', id: data.stats[bonusSet].id});
                td.appendChild(a);
                $(a).text('[' + data.stats[bonusSet].name + ((auc.rand && tujConstants.randEnchants.hasOwnProperty(auc.rand)) ? (' ' + tujConstants.randEnchants[auc.rand].name) : '') + ']');
            }

            if (data.stats[bonusSet].stacksize > 1) {
                td = libtuj.ce('td');
                tr.appendChild(td);
                td.className = 'quantity';
                td.appendChild(libtuj.FormatQuantity(auc.quantity));
            }

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatFullPrice(auc.bid / auc.quantity);
            if (stackable && auc.quantity > 1) {
                a = libtuj.ce('abbr');
                a.title = libtuj.FormatFullPrice(auc.bid, true) + ' total';
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
                a.title = libtuj.FormatFullPrice(auc.buy, true) + ' total';
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
            td.className = 'seller';
            if (auc.sellerrealm) {
                a = libtuj.ce('a');
                a.href = tuj.BuildHash({realm: auc.sellerrealm, page: 'seller', id: auc.sellername});
            }
            else {
                a = libtuj.ce('span');
            }
            td.appendChild(a);
            $(a).text(auc.sellername + (auc.sellerrealm && auc.sellerrealm != params.realm ? (' - ' + tuj.realms[auc.sellerrealm].name) : ''));
        }

        dest.appendChild(t);
    }

    this.load(tuj.params);
}

tuj.page_item = new TUJ_Item();
