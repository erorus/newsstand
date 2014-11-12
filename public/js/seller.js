
var TUJ_Seller = function()
{
    var params;
    var lastResults = [];

    this.load = function(inParams)
    {
        params = {};
        for (var p in inParams)
            if (inParams.hasOwnProperty(p))
                params[p] = inParams[p];

        var qs = {
            realm: params.realm,
            seller: params.id
        };
        var hash = JSON.stringify(qs);

        for (var x = 0; x < lastResults.length; x++)
            if (lastResults[x].hash == hash)
            {
                SellerResult(false, lastResults[x].data);
                return;
            }

        var sellerPage = $('#seller-page')[0];
        if (!sellerPage)
        {
            sellerPage = libtuj.ce();
            sellerPage.id = 'seller-page';
            sellerPage.className = 'page';
            $('#main').append(sellerPage);
        }

        $('#progress-page').show();

        $.ajax({
            data: qs,
            success: function(d) {
                if (d.captcha)
                    tuj.AskCaptcha(d.captcha);
                else
                    SellerResult(hash, d);
            },
            complete: function() {
                $('#progress-page').hide();
            },
            url: 'api/seller.php'
        });
    }

    function SellerResult(hash, dta)
    {
        if (hash)
        {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10)
                lastResults.shift();
        }

        var sellerPage = $('#seller-page');
        sellerPage.empty();
        sellerPage.show();

        if (!dta.stats)
        {
            $('#page-title').empty().append(document.createTextNode('Seller: ' + params.id));
            tuj.SetTitle('Seller: ' + params.id);

            var h2 = libtuj.ce('h2');
            sellerPage.append(h2);
            h2.appendChild(document.createTextNode('Seller '+ params.id + ' not found.'));

            return;
        }

        $('#page-title').empty().append(document.createTextNode('Seller: ' + dta.stats.name));
        tuj.SetTitle('Seller: ' + dta.stats.name);

        var d, cht, h;

        sellerPage.append(libtuj.Ads.Add('3896661119'));

        d = libtuj.ce();
        d.className = 'chart-section';
        h = libtuj.ce('h2');
        d.appendChild(h);
        $(h).text('Snapshots');
        d.appendChild(document.createTextNode('Here you\'ll find the amount of new and total auctions by this seller at each snapshot.'))
        cht = libtuj.ce();
        cht.className = 'chart history';
        d.appendChild(cht);
        sellerPage.append(d);
        SellerHistoryChart(dta, cht);

        if (dta.history.length >= 14)
        {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Posting Heat Map');
            cht = libtuj.ce();
            cht.className = 'chart heatmap';
            d.appendChild(cht);
            sellerPage.append(d);
            SellerPostingHeatMap(dta, cht);
        }

        if (dta.auctions.length)
        {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Current Auctions');
            cht = libtuj.ce();
            cht.className = 'auctionlist';
            d.appendChild(cht);
            sellerPage.append(d);
            SellerAuctions(dta, cht);
        }

        if (dta.petAuctions.length)
        {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Current Pet Auctions');
            cht = libtuj.ce();
            cht.className = 'auctionlist';
            d.appendChild(cht);
            sellerPage.append(d);
            SellerPetAuctions(dta, cht);
        }

        libtuj.Ads.Show();
    }

    function SellerHistoryChart(data, dest)
    {
        var hcdata = {total: [], newAuc: [], max: 0};

        for (var x = 0; x < data.history.length; x++)
        {
            hcdata.total.push([data.history[x].snapshot*1000, data.history[x].total]);
            hcdata.newAuc.push([data.history[x].snapshot*1000, data.history[x]['new']]);
            if (data.history[x].total > hcdata.max)
                hcdata.max = data.history[x].total;
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
            yAxis: [{
                title: {
                    text: 'Number of Auctions',
                    style: {
                        color: tujConstants.siteColors[tuj.colorTheme].bluePrice
                    }
                },
                labels: {
                    enabled: true,
                    formatter: function() { return ''+libtuj.FormatQuantity(this.value, true); },
                    style: {
                        color: tujConstants.siteColors[tuj.colorTheme].text
                    }
                },
                min: 0,
                max: hcdata.max
            }],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function() {
                    var tr = '<b>'+Highcharts.dateFormat('%a %b %d, %I:%M%P', this.x)+'</b>';
                    tr += '<br><span style="color: #000099">Total: '+libtuj.FormatQuantity(this.points[0].y, true)+'</span>';
                    tr += '<br><span style="color: #990000">New: '+libtuj.FormatQuantity(this.points[1].y, true)+'</span>';
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
            series: [{
                type: 'area',
                name: 'Total',
                color: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                lineColor: tujConstants.siteColors[tuj.colorTheme].bluePrice,
                fillColor: tujConstants.siteColors[tuj.colorTheme].bluePriceFill,
                data: hcdata.total
            },{
                type: 'line',
                name: 'New',
                color: tujConstants.siteColors[tuj.colorTheme].redQuantity,
                data: hcdata.newAuc
            }]
        });
    }

    function SellerPostingHeatMap(data, dest)
    {
        var hcdata = {minVal: undefined, maxVal: 0, days: {}, heat: [], categories: {
            x: ['Midnight - 3am','3am - 6am','6am - 9am','9am - Noon','Noon - 3pm','3pm - 6pm','6pm - 9pm','9pm - Midnight'],
            y: ['Saturday','Friday','Thursday','Wednesday','Tuesday','Monday','Sunday']
        }};

        var CalcAvg = function(a)
        {
            if (a.length == 0)
                return null;
            var s = 0;
            for (var x = 0; x < a.length; x++)
                s += a[x];
            return s/a.length;
        }

        var d, wkdy, hr;
        for (wkdy = 0; wkdy <= 6; wkdy++)
        {
            hcdata.days[wkdy] = {};
            for (hr = 0; hr <= 7; hr++)
                hcdata.days[wkdy][hr] = [];
        }

        for (var x = 0; x < data.history.length; x++)
        {
            var d = new Date(data.history[x].snapshot*1000);
            wkdy = 6-d.getDay();
            hr = Math.floor(d.getHours()/3);
            hcdata.days[wkdy][hr].push(data.history[x]['new']);
        }

        var p;
        for (wkdy = 0; wkdy <= 6; wkdy++)
            for (hr = 0; hr <= 7; hr++)
            {
                if (hcdata.days[wkdy][hr].length == 0)
                    p = 0;
                else
                    p = Math.round(CalcAvg(hcdata.days[wkdy][hr]));

                hcdata.heat.push([hr, wkdy, p]);
                hcdata.minVal = (typeof hcdata.minVal == 'undefined' || hcdata.minVal > p) ? p : hcdata.minVal;
                hcdata.maxVal = hcdata.maxVal < p ? p : hcdata.maxVal;
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

            series: [{
                name: 'New Auctions',
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
                    formatter: function() { return ''+libtuj.FormatQuantity(this.point.value, true); }
                }
            }]

        });
    }

    function SellerAuctions(data, dest)
    {
        var t,tr,td;
        t = libtuj.ce('table');
        t.className = 'auctionlist';

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'quantity';
        $(td).text('Quantity');

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'name';
        td.colSpan = 2;
        $(td).text('Item');

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
        td.className = 'quantity';
        $(td).text('Cheaper');

        data.auctions.sort(function(a,b){
            return tujConstants.itemClassOrder[a['class']] - tujConstants.itemClassOrder[b['class']] ||
                a.name.localeCompare(b.name) ||
                Math.floor(a.buy / a.quantity) - Math.floor(b.buy / b.quantity) ||
                Math.floor(a.bid / a.quantity) - Math.floor(b.bid / b.quantity) ||
                a.quantity - b.quantity;
        });

        var s, a, stackable, i;
        for (var x = 0, auc; auc = data.auctions[x]; x++)
        {
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
            i.src = 'https://cdn.theunderminejournal.com/icon/medium/' + auc.icon + '.jpg';

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'name';
            a = libtuj.ce('a');
            a.rel = 'item=' + auc.item + (auc.rand ? '&rand=' + auc.rand : '');
            a.href = tuj.BuildHash({page: 'item', id: auc.item});
            td.appendChild(a);
            $(a).text('[' + auc.name + ((auc.rand && tujConstants.randEnchants.hasOwnProperty(auc.rand)) ? (' ' + tujConstants.randEnchants[auc.rand].name) : '') + ']');

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatFullPrice(auc.bid / auc.quantity);
            if (stackable && auc.quantity > 1)
            {
                a = libtuj.ce('abbr');
                a.title = libtuj.FormatFullPrice(auc.bid, true) + ' total';
                a.appendChild(s);
            }
            else
                a = s;
            td.appendChild(a);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'price';
            s = libtuj.FormatFullPrice(auc.buy / auc.quantity);
            if (stackable && auc.quantity > 1 && auc.buy)
            {
                a = libtuj.ce('abbr');
                a.title = libtuj.FormatFullPrice(auc.buy, true) + ' total';
                a.appendChild(s);
            }
            else if (!auc.buy)
                a = libtuj.ce('span');
            else
                a = s;
            if (a)
                td.appendChild(a);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'quantity';
            if (auc.cheaper) {
                td.appendChild(libtuj.FormatQuantity(auc.cheaper));
            }
        }

        dest.appendChild(t);
    }

    function SellerPetAuctions(data, dest)
    {
        var t,tr,td;
        t = libtuj.ce('table');
        t.className = 'auctionlist';

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'name';
        td.colSpan = 2;
        $(td).text('Species');

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
        td.className = 'quantity';
        $(td).text('Cheaper');

        data.petAuctions.sort(function(a,b){
            return a.name.localeCompare(b.name) ||
                tujConstants.breeds[a.breed].localeCompare(tujConstants.breeds[b.breed]) ||
                a.quality - b.quality ||
                a.buy - b.buy ||
                a.bid - b.bid;
        });

        var s, a, i;
        for (var x = 0, auc; auc = data.petAuctions[x]; x++)
        {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('td');
            td.className = 'icon';
            tr.appendChild(td);
            i = libtuj.ce('img');
            td.appendChild(i);
            i.className = 'icon';
            i.src = 'https://cdn.theunderminejournal.com/icon/medium/' + auc.icon + '.jpg';

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'name';
            a = libtuj.ce('a');
            a.rel = 'npc=' + auc.npc;
            a.href = tuj.BuildHash({page: 'battlepet', id: auc.species});
            td.appendChild(a);
            $(a).text('[' + auc.name + ']');

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
            if (!auc.buy)
                a = libtuj.ce('span');
            else
                a = s;
            if (a)
                td.appendChild(a);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'quantity';
            if (auc.cheaper) {
                td.appendChild(libtuj.FormatQuantity(auc.cheaper));
            }
        }

        dest.appendChild(t);
    }

    this.load(tuj.params);
}

tuj.page_seller = new TUJ_Seller();
