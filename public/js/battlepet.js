var TUJ_BattlePet = function ()
{
    var params;
    var lastResults = [];
    var speciesId;
    var breedId;

    this.load = function (inParams)
    {
        params = {};
        for (var p in inParams) {
            if (inParams.hasOwnProperty(p)) {
                params[p] = inParams[p];
            }
        }

        speciesId = '' + params.id;
        breedId = 0;
        if (speciesId.indexOf('.') > 0) {
            speciesId = ('' + params.id).substr(0, ('' + params.id).indexOf('.'));
            breedId = ('' + params.id).substr(('' + params.id).indexOf('.') + 1);
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

        $.ajax({
            data: qs,
            success: function (d)
            {
                if (d.captcha) {
                    tuj.AskCaptcha(d.captcha);
                }
                else {
                    BattlePetResult(hash, d);
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
            url: 'api/battlepet.php'
        });
    }

    function BattlePetResult(hash, dtaAll)
    {
        if (hash) {
            lastResults.push({hash: hash, data: dtaAll});
            while (lastResults.length > 10) {
                lastResults.shift();
            }
        }

        var battlePetPage = $('#battlepet-page');
        battlePetPage.empty();
        battlePetPage.show();

        if (breedId && !dtaAll.stats.hasOwnProperty(breedId)) {
            breedId = 0;
        }

        var x, y, breeds = [];
        for (x in dtaAll.stats) {
            if (dtaAll.stats.hasOwnProperty(x)) {
                breeds.push(x);
            }
        }

        if (breeds.length == 0) {
            $('#page-title').empty().append(document.createTextNode('Battle Pet: ' + params.id));
            tuj.SetTitle('Battle Pet: ' + params.id);

            var h2 = libtuj.ce('h2');
            battlePetPage.append(h2);
            h2.appendChild(document.createTextNode('Battle Pet ' + params.id + ' not found.'));

            return;
        }

        var dta = BattlePetBreedData(dtaAll, breeds);

        var ta = libtuj.ce('a');
        ta.href = 'http://www.wowhead.com/npc=' + dta.stats.npc;
        ta.target = '_blank';
        ta.className = 'battlepet'
        var timg = libtuj.ce('img');
        ta.appendChild(timg);
        timg.src = libtuj.IconURL(dta.stats.icon, 'large');
        var ttl = '[' + dta.stats.name + ']' + (breedId && breeds.length > 1 ? ' ' + tujConstants.breeds[breedId] : '');
        ta.appendChild(document.createTextNode(ttl));

        $('#page-title').empty().append(ta);
        tuj.SetTitle(ttl);

        var d, cht, h, a;

        if (breeds.length > 1) {
            d = libtuj.ce();
            d.className = 'battlepet-breeds';
            battlePetPage.append(d);

            a = libtuj.ce('a');
            d.appendChild(a);
            a.href = tuj.BuildHash({page: 'battlepet', id: '' + speciesId});
            a.appendChild(document.createTextNode(tujConstants.breeds[0]));
            if (breedId == 0) {
                a.className = 'selected';
            }

            for (var x = 0; x < breeds.length; x++) {
                a = libtuj.ce('a');
                d.appendChild(a);
                a.href = tuj.BuildHash({page: 'battlepet', id: '' + speciesId + '.' + breeds[x]});
                a.appendChild(document.createTextNode(tujConstants.breeds[breeds[x]]));
                if (breedId == breeds[x]) {
                    a.className = 'selected';
                }
            }
        }

        d = libtuj.ce();
        d.className = 'battlepet-stats';
        battlePetPage.append(d);
        BattlePetStats(dta, d);

        if (dta.history.length >= 4) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Snapshots');
            d.appendChild(document.createTextNode('Here is the available quantity and market price of the battle pet for every ' + tuj.validRegions[params.region] + ' ' + tuj.realms[params.realm].name + ' auction house snapshot seen recently.'));
            cht = libtuj.ce();
            cht.className = 'chart history';
            d.appendChild(cht);
            battlePetPage.append(d);
            BattlePetHistoryChart(dta, cht);
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
            battlePetPage.append(d);
            BattlePetPriceHeatMap(dta, cht);

            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Quantity Heat Map');
            d.appendChild(document.createTextNode('This heat map shows you the average available quantity at different times of the day.'));
            cht = libtuj.ce();
            cht.className = 'chart heatmap';
            d.appendChild(cht);
            battlePetPage.append(d);
            BattlePetQuantityHeatMap(dta, cht);
        }

        if (dta.globalnow.length > 0) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Current Regional Prices');
            d.appendChild(document.createTextNode('The Regional Prices chart is sorted by price, and shows the price and quantity available of this battle pet on all realms in the ' + tuj.validRegions[params.region] + '.'));
            cht = libtuj.ce();
            cht.className = 'chart columns';
            d.appendChild(cht);
            battlePetPage.append(d);
            BattlePetGlobalNowColumns(dta, cht);

            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Price/Population Scatter Plot');
            d.appendChild(document.createTextNode('This scatter plot has the same data as above, but shows the price relative to the realm population, provided by '));
            var a = libtuj.ce('a');
            d.appendChild(a);
            d.appendChild(document.createTextNode('.'));
            a.href = 'https://realmpop.com/' + tuj.validRegions[params.region].toLowerCase() + '.html';
            a.style.textDecoration = 'underline';
            a.appendChild(document.createTextNode('Realm Pop'));

            cht = libtuj.ce();
            cht.className = 'chart scatter';
            d.appendChild(cht);
            battlePetPage.append(d);
            BattlePetGlobalNowScatter(dta, cht);
        }

        if (dta.auctions.length) {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Current Auctions');
            d.appendChild(document.createTextNode('Here is the full list of auctions for this battle pet from the latest snapshot. Click a seller name for details on that seller.'));
            d.appendChild(libtuj.ce('br'));
            d.appendChild(libtuj.ce('br'));
            cht = libtuj.ce();
            cht.className = 'auctionlist';
            d.appendChild(cht);
            battlePetPage.append(d);
            BattlePetAuctions(dta, cht);
        }

        libtuj.Ads.Show();
    }

    function BattlePetBreedData(dtaAll, breeds)
    {
        var dta = {};
        if (breedId) {
            if (dtaAll.stats.hasOwnProperty(breedId)) {
                dta.stats = dtaAll.stats[breedId];
                dta.history = dtaAll.history[breedId] || [];
                dta.auctions = dtaAll.auctions[breedId] || [];
                dta.globalnow = dtaAll.globalnow[breedId] || [];
                return dta;
            }
            breedId = 0;
        }

        dta.stats = {};
        for (x in dtaAll.stats[breeds[0]]) {
            if (dtaAll.stats[breeds[0]].hasOwnProperty(x)) {
                dta.stats[x] = dtaAll.stats[breeds[0]][x];
            }
        }
        for (x = 1; x < breeds.length; x++) {
            if (dta.stats.quantity == 0) {
                if (dtaAll.stats[breeds[x]].quantity || (dtaAll.stats[breeds[x]].price < dta.stats.price)) {
                    dta.stats.price = dtaAll.stats[breeds[x]].price;
                }
            }
            else {
                if (dtaAll.stats[breeds[x]].quantity && (dtaAll.stats[breeds[x]].price < dta.stats.price)) {
                    dta.stats.price = dtaAll.stats[breeds[x]].price;
                }
            }
            dta.stats.quantity += dtaAll.stats[breeds[x]].quantity;
            if (!dta.stats.lastseen || dta.stats.lastseen < dtaAll.stats[breeds[x]].lastseen) {
                dta.stats.lastseen = dtaAll.stats[breeds[x]].lastseen;
            }
        }
        delete dta.stats.breed;

        var h, o, baseBreed, cur, grp, z, a;
        if (breeds.length > 1) {
            dta.history = {};
            baseBreed = -1;
            for (x = 0; x < breeds.length && baseBreed == -1; x++) {
                if (dtaAll.history.hasOwnProperty(breeds[x])) {
                    baseBreed = x;
                    for (y = 0; h = dtaAll.history[breeds[0]][y]; y++) {
                        o = {};
                        for (x in h) {
                            if (h.hasOwnProperty(x)) {
                                o[x] = h[x];
                            }
                        }
                        delete o.breed;
                        dta.history[o.snapshot] = o;
                    }
                }
            }

            if (baseBreed != -1) {
                for (x = 0; x < breeds.length; x++) {
                    if (x == baseBreed) {
                        continue;
                    }
                    if (!dtaAll.history.hasOwnProperty(breeds[x])) {
                        continue;
                    }

                    for (y = 0; y < dtaAll.history[breeds[x]].length; y++) {
                        cur = dtaAll.history[breeds[x]][y];
                        if (!dta.history.hasOwnProperty(cur.snapshot)) {
                            o = {};
                            for (z in cur) {
                                if (cur.hasOwnProperty(z)) {
                                    o[z] = cur[z];
                                }
                            }
                            delete o.breed;
                            dta.history[o.snapshot] = o;
                            continue;
                        }
                        grp = dta.history[cur.snapshot];
                        if (grp.quantity == 0) {
                            if (cur.quantity || (cur.price < grp.price)) {
                                grp.price = cur.price;
                            }
                        }
                        else {
                            if (cur.quantity && (cur.price < grp.price)) {
                                grp.price = cur.price;
                            }
                        }
                        grp.quantity += cur.quantity;
                    }
                }
            }

            a = [];
            for (x in dta.history) {
                if (dta.history.hasOwnProperty(x)) {
                    a.push(dta.history[x]);
                }
            }
            a.sort(function (b, c)
            {
                return b.snapshot - c.snapshot;
            });

            dta.history = a;
        }
        else {
            dta.history = dtaAll.history[breeds[0]] || [];
        }

        dta.auctions = [];
        for (x = 0; x < breeds.length; x++) {
            if (dtaAll.auctions.hasOwnProperty(breeds[x])) {
                dta.auctions = dta.auctions.concat(dtaAll.auctions[breeds[x]]);
            }
        }

        if (breeds.length > 1) {
            dta.globalnow = {};
            baseBreed = -1;
            for (x = 0; x < breeds.length && baseBreed == -1; x++) {
                if (dtaAll.globalnow.hasOwnProperty(breeds[x])) {
                    baseBreed = x;
                    for (y = 0; h = dtaAll.globalnow[breeds[0]][y]; y++) {
                        o = {};
                        for (x in h) {
                            if (h.hasOwnProperty(x)) {
                                o[x] = h[x];
                            }
                        }
                        dta.globalnow[o.house] = o;
                    }
                }
            }

            if (baseBreed != -1) {
                for (x = 0; x < breeds.length; x++) {
                    if (x == baseBreed) {
                        continue;
                    }
                    if (!dtaAll.globalnow.hasOwnProperty(breeds[x])) {
                        continue;
                    }

                    for (y = 0; y < dtaAll.globalnow[breeds[x]].length; y++) {
                        cur = dtaAll.globalnow[breeds[x]][y];
                        if (!dta.globalnow.hasOwnProperty(cur.house)) {
                            o = {};
                            for (z in cur) {
                                if (cur.hasOwnProperty(z)) {
                                    o[z] = cur[z];
                                }
                            }
                            dta.globalnow[o.house] = o;
                            continue;
                        }

                        grp = dta.globalnow[cur.house];
                        if (grp.quantity == 0) {
                            if (cur.quantity || (cur.price < grp.price)) {
                                grp.price = cur.price;
                            }
                        }
                        else {
                            if (cur.quantity && (cur.price < grp.price)) {
                                grp.price = cur.price;
                            }
                        }
                        grp.quantity += cur.quantity;
                        if (!grp.lastseen || grp.lastseen < cur.lastseen) {
                            grp.lastseen = cur.lastseen;
                        }
                    }
                }
            }

            a = [];
            for (x in dta.globalnow) {
                if (dta.globalnow.hasOwnProperty(x)) {
                    a.push(dta.globalnow[x]);
                }
            }
            dta.globalnow = a;
        }
        else {
            dta.globalnow = dtaAll.globalnow[breeds[0]] || [];
        }

        return dta;
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
        td.appendChild(document.createTextNode('Available Quantity'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatQuantity(data.stats.quantity));

        if (data.stats.quantity == 0) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);
            tr.className = 'last-seen';
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.appendChild(document.createTextNode('Last Seen'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatDate(data.stats.lastseen));
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
        td.appendChild(libtuj.FormatPrice(data.stats.price));

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
            td.appendChild(document.createTextNode('Median Price'));
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
            td.appendChild(document.createTextNode('Mean Price'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(mn));

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
            td.appendChild(document.createTextNode(headerPrefix + 'Quantity'));
            td = libtuj.ce('td');
            tr.appendChild(td);
            td.appendChild(libtuj.FormatQuantity(globalStats.quantity));

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
        }

        dest.appendChild(libtuj.Ads.Add('2419927914', 'box'));
    }

    function BattlePetHistoryChart(data, dest)
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
                backgroundColor: tujConstants.siteColors[tuj.colorTheme].background,
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
                    var tr = '<b>' + Highcharts.dateFormat('%a %b %e %Y, %l:%M%P', this.x) + '</b>';
                    tr += '<br><span style="color: #000099">Market Price: ' + libtuj.FormatPrice(this.points[0].y, true) + '</span>';
                    tr += '<br><span style="color: #990000">Quantity: ' + libtuj.FormatQuantity(this.points[1].y, true) + '</span>';
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

    function BattlePetPriceHeatMap(data, dest)
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

    function BattlePetQuantityHeatMap(data, dest)
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

    function BattlePetGlobalNowScatter(data, dest)
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
        $(td).text('Breed');

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'quality';
        $(td).text('Quality');

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'level';
        $(td).text('Level');

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
                (a.sellername && b.sellername ? a.sellername.localeCompare(b.sellername) : 0);
        });

        var s, a;
        for (var x = 0, auc; auc = data.auctions[x]; x++) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'breed';
            td.appendChild(document.createTextNode(tujConstants.breeds[auc.breed]));

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'quality';
            td.appendChild(document.createTextNode(tujConstants.qualities[auc.quality]));

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

tuj.page_battlepet = new TUJ_BattlePet();
