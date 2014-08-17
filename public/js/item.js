
var TUJ_Item = function()
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
            house: tuj.realms[params.realm].house * tuj.validFactions[params.faction],
            item: params.id
        };
        var hash = JSON.stringify(qs);

        for (var x = 0; x < lastResults.length; x++)
            if (lastResults[x].hash == hash)
            {
                ItemResult(false, lastResults[x].data);
                return;
            }

        var itemPage = $('#item-page')[0];
        if (!itemPage)
        {
            itemPage = libtuj.ce();
            itemPage.id = 'item-page';
            itemPage.className = 'page';
            $('#main').append(itemPage);
        }

        $.ajax({
            data: qs,
            success: function(d) { ItemResult(hash, d); },
            url: 'api/item.php'
        });
    }

    function ItemResult(hash, dta)
    {
        if (hash)
        {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10)
                lastResults.shift();
        }

        var ta = libtuj.ce('a');
        ta.href = 'http://www.wowhead.com/item=' + dta.stats.id;
        ta.target = '_blank';
        ta.className = 'item'
        var timg = libtuj.ce('img');
        ta.appendChild(timg);
        timg.src = 'icon/large/' + dta.stats.icon + '.jpg';
        ta.appendChild(document.createTextNode('[' + dta.stats.name + ']'));

        $('#page-title').empty().append(ta);

        var itemPage = $('#item-page');
        itemPage.empty();
        itemPage.show();

        var d, cht, h;

        d = libtuj.ce();
        d.className = 'item-stats';
        itemPage.append(d);
        ItemStats(dta, d);

        if (dta.history.length >= 4)
        {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Snapshots');
            d.appendChild(document.createTextNode('Here is the available quantity and market price of the item for every auction house snapshot seen recently.'))
            cht = libtuj.ce();
            cht.className = 'chart history';
            d.appendChild(cht);
            itemPage.append(d);
            ItemHistoryChart(dta, cht);
        }

        if (dta.monthly.length >= 7)
        {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Daily Summary');
            d.appendChild(document.createTextNode('Here is the maximum available quantity, and the market price at that time, for the item each day.'))
            cht = libtuj.ce();
            cht.className = 'chart monthly';
            d.appendChild(cht);
            itemPage.append(d);
            ItemMonthlyChart(dta, cht);
        }

        if (dta.daily.length >= 7)
        {
            d = libtuj.ce();
            d.className = 'chart-section';
            h = libtuj.ce('h2');
            d.appendChild(h);
            $(h).text('Daily Details');
            d.appendChild(document.createTextNode('This chart is similar to the Daily Summary, but includes the "OHLC" market prices for the item each day, along with the minimum, average, and maximum available quantity.'))
            cht = libtuj.ce();
            cht.className = 'chart daily';
            d.appendChild(cht)
            itemPage.append(d);
            ItemDailyChart(dta, cht);
        }
    }

    function ItemStats(data, dest)
    {
        var t, tr, td;

        t = libtuj.ce('table');
        dest.appendChild(t);

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode('Market Price:'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatPrice(data.stats.price));

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode('Available:'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatQuantity(data.stats.quantity));

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode('Last Seen:'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(libtuj.FormatDate(data.stats.lastseen));

        //t = libtuj.ce('table');
        //dest.appendChild(t);
        tr = libtuj.ce('tr');
        t.appendChild(tr);
        td = libtuj.ce('td');
        td.colSpan = 2;
        td.style.height = '0.5em';
        tr.appendChild(td);

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode('Stack Size:'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(data.stats.stacksize ? data.stats.stacksize : '?'));

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode('Sell to Vendor:'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(data.stats.selltovendor ? libtuj.FormatPrice(data.stats.selltovendor) : document.createTextNode('(Does not buy)'));

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.appendChild(document.createTextNode('48hr Listing Fee:'));
        td = libtuj.ce('td');
        tr.appendChild(td);
        if (data.stats.stacksize)
        {
            var abbr = libtuj.ce('abbr');
            abbr.title = 'Each';
            abbr.appendChild(libtuj.FormatPrice(Math.max(100, data.stats.selltovendor ? data.stats.selltovendor * 0.6 : 0)));
            td.appendChild(abbr);

            td.appendChild(document.createTextNode(' / '));;

            abbr = libtuj.ce('abbr');
            abbr.title = 'Stack';
            abbr.appendChild(libtuj.FormatPrice(Math.max(100, data.stats.selltovendor ? data.stats.selltovendor * 0.6 * data.stats.stacksize : 0)));
            td.appendChild(abbr);
        }
        else
            td.appendChild(libtuj.FormatPrice(Math.max(100, data.stats.selltovendor ? data.stats.selltovendor * 0.6 : 0)));

        var ad = libtuj.ce();
        ad.className = 'ad box';
        dest.appendChild(ad);
    }

    function ItemHistoryChart(data, dest)
    {
        var hcdata = {price: [], priceMaxVal: 0, quantity: [], quantityMaxVal: 0};

        var allPrices = [];
        for (var x = 0; x < data.history.length; x++)
        {
            hcdata.price.push([data.history[x].snapshot*1000, data.history[x].price]);
            hcdata.quantity.push([data.history[x].snapshot*1000, data.history[x].quantity]);
            if (data.history[x].quantity > hcdata.quantityMaxVal)
                hcdata.quantityMaxVal = data.history[x].quantity;
            allPrices.push(data.history[x].price);
        }

        allPrices.sort(function(a,b){ return a - b; });
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
                    text: 'Market Price',
                    style: {
                        color: '#0000FF'
                    }
                },
                labels: {
                    enabled: true,
                    formatter: function() { return ''+libtuj.FormatPrice(this.value, true); }
                },
                min: 0,
                max: hcdata.priceMaxVal
            }, {
                title: {
                    text: 'Quantity Available',
                    style: {
                        color: '#FF3333'
                    }
                },
                labels: {
                    enabled: true
                },
                opposite: true,
                min: 0,
                max: hcdata.quantityMaxVal
            }],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function() {
                    var tr = '<b>'+Highcharts.dateFormat('%a %b %d, %I:%M%P', this.x)+'</b>';
                    tr += '<br><span style="color: #000099">Market Price: '+libtuj.FormatPrice(this.points[0].y, true)+'</span>';
                    tr += '<br><span style="color: #990000">Quantity: '+libtuj.FormatQuantity(this.points[1].y, true)+'</span>';
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
            series: [{
                type: 'area',
                name: 'Market Price',
                color: '#0000FF',
                lineColor: '#0000FF',
                fillColor: '#CCCCFF',
                data: hcdata.price
            },{
                type: 'line',
                name: 'Quantity Available',
                yAxis: 1,
                color: '#FF3333',
                data: hcdata.quantity
            }]
        });
    }

    function ItemMonthlyChart(data, dest)
    {
        var hcdata = {price: [], priceMaxVal: 0, quantity: [], quantityMaxVal: 0};

        var allPrices = [], dt, dtParts;
        var offset = (new Date()).getTimezoneOffset() * 60 * 1000;
        for (var x = 0; x < data.monthly.length; x++)
        {
            dtParts = data.monthly[x].date.split('-');
            dt = Date.UTC(dtParts[0], parseInt(dtParts[1],10)-1, dtParts[2]) + offset;
            hcdata.price.push([dt, data.monthly[x].silver * 100]);
            hcdata.quantity.push([dt, data.monthly[x].quantity]);
            if (data.monthly[x].quantity > hcdata.quantityMaxVal)
                hcdata.quantityMaxVal = data.monthly[x].quantity;
            allPrices.push(data.monthly[x].silver * 100);
        }

        allPrices.sort(function(a,b){ return a - b; });
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
                maxZoom: 4 * 24 * 3600000, // four days
                title: {
                    text: null
                }
            },
            yAxis: [{
                title: {
                    text: 'Market Price',
                    style: {
                        color: '#0000FF'
                    }
                },
                labels: {
                    enabled: true,
                    formatter: function() { return ''+libtuj.FormatPrice(this.value, true); }
                },
                min: 0,
                max: hcdata.priceMaxVal
            }, {
                title: {
                    text: 'Quantity Available',
                    style: {
                        color: '#FF3333'
                    }
                },
                labels: {
                    enabled: true
                },
                opposite: true,
                min: 0,
                max: hcdata.quantityMaxVal
            }],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function() {
                    var tr = '<b>'+Highcharts.dateFormat('%a %b %d', this.x)+'</b>';
                    tr += '<br><span style="color: #000099">Market Price: '+libtuj.FormatPrice(this.points[0].y, true)+'</span>';
                    tr += '<br><span style="color: #990000">Quantity: '+libtuj.FormatQuantity(this.points[1].y, true)+'</span>';
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
            series: [{
                type: 'area',
                name: 'Market Price',
                color: '#0000FF',
                lineColor: '#0000FF',
                fillColor: '#CCCCFF',
                data: hcdata.price
            },{
                type: 'line',
                name: 'Quantity Available',
                yAxis: 1,
                color: '#FF3333',
                data: hcdata.quantity
            }]
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
        for (var x = 0; x < data.daily.length; x++)
        {
            dtParts = data.daily[x].date.split('-');
            dt = Date.UTC(dtParts[0], parseInt(dtParts[1],10)-1, dtParts[2]) + offset;

            hcdata.ohlc.push([dt,
                data.daily[x].silverstart * 100,
                data.daily[x].silvermax * 100,
                data.daily[x].silvermin * 100,
                data.daily[x].silverend * 100
            ]);
            allPrices.push(data.daily[x].silvermax * 100);

            hcdata.price.push([dt, data.daily[x].silveravg * 100]);

            hcdata.quantity.push([dt, data.daily[x].quantityavg]);
            hcdata.quantityRange.push([dt, data.daily[x].quantitymin, data.daily[x].quantitymax]);
            if (data.daily[x].quantityavg > hcdata.quantityMaxVal)
                hcdata.quantityMaxVal = data.daily[x].quantityavg;
        }

        allPrices.sort(function(a,b){ return a - b; });
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
                zoomType: 'x'
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
                    'Pinch the chart to zoom in'
            },
            xAxis: {
                type: 'datetime',
                maxZoom: 4 * 24 * 3600000, // four days
                title: {
                    text: null
                }
            },
            yAxis: [{
                title: {
                    text: 'Market Price',
                    style: {
                        color: '#0000FF'
                    }
                },
                labels: {
                    enabled: true,
                    formatter: function() { return ''+libtuj.FormatPrice(this.value, true); },
                },
                height: '60%',
                min: 0,
                max: hcdata.ohlcMaxVal
            }, {
                title: {
                    text: 'Quantity Available',
                    style: {
                        color: '#FF3333'
                    }
                },
                labels: {
                    enabled: true
                },
                top: '65%',
                height: '35%',
                min: 0,
                max: hcdata.quantityMaxVal,
                offset: -25
            }],
            legend: {
                enabled: false
            },
            tooltip: {
                shared: true,
                formatter: function() {
                    var tr = '<b>'+Highcharts.dateFormat('%a %b %d', this.x)+'</b>';
                    tr += '<br><table class="highcharts-tuj-tooltip" style="color: #000099;" cellspacing="0" cellpadding="0">';
                    tr += '<tr><td>Open:</td><td align="right">'+libtuj.FormatPrice(this.points[0].point.open, true)+'</td></tr>';
                    tr += '<tr><td>High:</td><td align="right">'+libtuj.FormatPrice(this.points[0].point.high, true)+'</td></tr>';
                    tr += '<tr style="color: #009900"><td>Avg:</td><td align="right">'+libtuj.FormatPrice(this.points[3].y, true)+'</td></tr>';
                    tr += '<tr><td>Low:</td><td align="right">'+libtuj.FormatPrice(this.points[0].point.low, true)+'</td></tr>';
                    tr += '<tr><td>Close:</td><td align="right">'+libtuj.FormatPrice(this.points[0].point.close, true)+'</td></tr>';
                    tr += '</table>';
                    tr += '<br><table class="highcharts-tuj-tooltip" style="color: #FF3333;" cellspacing="0" cellpadding="0">';
                    tr += '<tr><td>Min&nbsp;Qty:</td><td align="right">'+libtuj.FormatQuantity(this.points[2].point.low, true)+'</td></tr>';
                    tr += '<tr><td>Avg&nbsp;Qty:</td><td align="right">'+libtuj.FormatQuantity(this.points[1].y, true)+'</td></tr>';
                    tr += '<tr><td>Max&nbsp;Qty:</td><td align="right">'+libtuj.FormatQuantity(this.points[2].point.high, true)+'</td></tr>';
                    tr += '</table>';
                    return tr;
                    // &lt;br/&gt;&lt;span style="color: #990000"&gt;Quantity: '+this.points[1].y+'&lt;/span&gt;<xsl:if test="itemgraphs/d[@matsprice != '']">&lt;br/&gt;&lt;span style="color: #999900"&gt;Materials Price: '+this.points[2].y.toFixed(2)+'g&lt;/span&gt;</xsl:if>';
                },
                useHTML: true,
                positioner: function(w,h,p)
                {
                    var x = p.plotX, y = p.plotY;
                    if (y < 0)
                        y = 0;
                    if (x < (this.chart.plotWidth/2))
                        x += w/2;
                    else
                        x -= w*1.25;
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
            series: [{
                type: 'candlestick',
                name: 'Market Price',
                color: '#CCCCFF',
                lineColor: '#0000FF',
                data: hcdata.ohlc
            },{
                type: 'line',
                name: 'Quantity',
                yAxis: 1,
                color: '#FF3333',
                data: hcdata.quantity,
                lineWidth: 2
            },{
                type: 'arearange',
                name: 'Quantity Range',
                yAxis: 1,
                color: '#FFCCCC',
                data: hcdata.quantityRange
            },{
                type: 'line',
                name: 'Market Price',
                color: '#009900',
                data: hcdata.price
            }]
        });
    }

    this.load(tuj.params);
}

tuj.page_item = new TUJ_Item();
