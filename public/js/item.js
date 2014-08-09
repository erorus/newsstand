
var TUJ_Item = function()
{
    var params;

    this.load = function(inParams)
    {
        params = inParams;

        var itemPage = $('#item-page')[0];
        if (!itemPage)
        {
            itemPage = libtuj.ce();
            itemPage.id = 'item-page';
            itemPage.className = 'page';
            $('#realm-header').after(itemPage);
        }
        $.ajax({
            data: {
                house: tuj.realms[params.realm].house * tuj.validFactions[params.faction],
                item: params.id
            },
            success: ItemResult,
            url: 'api/item.php'
        });
    }

    function ItemResult(dta)
    {
        var itemPage = $('#item-page');
        itemPage.empty();

        var h = libtuj.ce();
        h.className = 'header';
        itemPage.append(h);
        $(h).text('Item: '+dta.stats.name);

        itemPage.show();

        if (dta.history)
        {
            var d = libtuj.ce();
            d.className = 'chart history';
            itemPage.append(d);
            ItemHistoryChart(dta, d);
        }
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
        var q3 = allPrices[Math.ceil(allPrices.length * 0.75)];
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
                maxZoom: 4 * 3600000, // one hour
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
                    formatter: function() { return ''+this.value+'g'; }
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
                    lineWidth: 1,
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
                            lineWidth: 1
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

    this.load(tuj.params);
}

tuj.page_item = new TUJ_Item();
