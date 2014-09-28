
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

        sellerPage.append(libtuj.AddAd('3896661119'));

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
                zoomType: 'x'
            },
            title: {
                text: null
            },
            subtitle: {
                text: document.ontouchstart === undefined ?
                    'Click and drag in the plot area to zoom in' :
                    'Pinch the chart to zoom in'
            },
            xAxis: {
                type: 'datetime',
                maxZoom: 4 * 3600000, // four hours
                title: {
                    text: null
                }
            },
            yAxis: [{
                title: {
                    text: 'Number of Auctions',
                    style: {
                        color: '#0000FF'
                    }
                },
                labels: {
                    enabled: true,
                    formatter: function() { return ''+libtuj.FormatQuantity(this.value, true); }
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
                color: '#0000FF',
                lineColor: '#0000FF',
                fillColor: '#CCCCFF',
                data: hcdata.total
            },{
                type: 'line',
                name: 'New',
                color: '#FF3333',
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
                type: 'heatmap'
            },

            title: {
                text: null
            },

            xAxis: {
                categories: hcdata.categories.x
            },

            yAxis: {
                categories: hcdata.categories.y,
                title: null
            },

            colorAxis: {
                min: hcdata.minVal,
                max: hcdata.maxVal,
                minColor: '#FFFFFF',
                maxColor: '#6666FF'
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
                borderColor: '#FFFFFF',
                data: hcdata.heat,
                dataLabels: {
                    enabled: true,
                    color: 'black',
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
            i.src = 'icon/medium/' + auc.icon + '.jpg';

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.className = 'name';
            a = libtuj.ce('a');
            a.rel = 'item=' + auc.item;
            a.href = tuj.BuildHash({page: 'item', id: auc.item});
            td.appendChild(a);
            $(a).text('[' + auc.name + ']');

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
        }

        dest.appendChild(t);
    }
    this.load(tuj.params);
}

tuj.page_seller = new TUJ_Seller();
