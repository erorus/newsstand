var wowtoken = {

    timeLeftMap: {
        names: ['',
            'less than 30 mins',
            '30 mins to 2 hours',
            '2 to 12 hours',
            'over 12 hours'
        ]
    },

    NumberCommas: function(v) {
        return v.toFixed().split("").reverse().join("").replace(/(\d{3})(?=\d)/g, '$1,').split("").reverse().join("");
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

    Main: function ()
    {
        wowtoken.LastVisitCheck();
        wowtoken.EUCheck();
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
            success: function (d)
            {
                wowtoken.ShowHistory(d.history);
                wowtoken.ParseUpdate(d.update);
                // window.setTimeout(wowtoken.LoadHistory, 60000*5);
            },
            url: '/wowtoken.json'
        });
    },

    ShowHistory: function (d)
    {
        var dest;
        for (var region in d) {

            if (d[region].length) {
                dest = document.getElementById('hc-'+region.toLowerCase());
                dest.className = 'hc';
                wowtoken.ShowChart(region, d[region], dest);
            }
        }
    },

    ParseUpdate: function (d)
    {
        for (var region in d) {
            if (!d[region].hasOwnProperty('formatted')) {
                continue;
            }
            for (var attrib in d[region].formatted) {
                $('#'+region+'-'+attrib).html(d[region].formatted[attrib]);
            }
        }
    },

    ShowChart: function(region, dta, dest) {
        var hcdata = { buy: [], timeleft: {}, navigator: [], zones: [], pct: {}, pctchart: [], realPrices: [] };
        var maxPrice = 0;
        var o, showLabel, direction = 0, newDirection = 0, lastLabel = -1;
        var lastTimeLeft = -1;
        var priceUpperBound = (region == 'NA' ? 40000 : 60000);
        var labelFormatter = function() {
            return wowtoken.NumberCommas(hcdata.realPrices[this.x]) + 'g';
        };

        var colors = {
            'line': '#0000ff',
            'fill': 'rgba(204,204,255,0.6)',
            'text': '#000099',
            'timeleft': [
                'rgba(204,204,255,0.6)',
                'rgba(204,204,255,0.6)',
                'rgba(178,178,229,0.6)',
                'rgba(153,153,204,0.6)',
                'rgba(127,127,178,0.6)',
            ],
        }
        if (region == 'EU') {
            colors = {
                'line': '#ff0000',
                'fill': 'rgba(255,204,204,0.6)',
                'text': '#990000',
                'timeleft': [
                    'rgba(255,204,204,0.6)',
                    'rgba(255,204,204,0.6)',
                    'rgba(229,178,178,0.6)',
                    'rgba(204,153,153,0.6)',
                    'rgba(178,127,127,0.6)',
                ],
            }
        }
        if (region == 'CN') {
            colors = {
                'line': '#00ff00',
                'fill': 'rgba(204,255,204,0.6)',
                'text': '#009900',
                'timeleft': [
                    'rgba(204,255,204,0.6)',
                    'rgba(204,255,204,0.6)',
                    'rgba(178,229,178,0.6)',
                    'rgba(153,204,153,0.6)',
                    'rgba(127,178,127,0.6)',
                ],
            }
        }
        for (var x = 0; x < dta.length; x++) {
            o = {
                x: dta[x][0]*1000,
                y: dta[x][1],
                //color: wowtoken.timeLeftMap.colors[dta[x][2]]
            };
            hcdata.navigator.push([dta[x][0]*1000, dta[x][1]]);
            if (x != 0) {
                for (var y = x-1; y > 0; y--) {
                    if (dta[y][0] <= (dta[x][0] - 55*60)) {
                        break;
                    }
                }
                /*for (var z = x+1; z < dta.length; z++) {
                    if (dta[z][0] >= (dta[x][0] + 40*60)) {
                        break;
                    }
                }
                if (z >= dta.length) {
                    z = dta.length - 1;
                }
                 */
                z = x;
                hcdata.pct[o.x] = ((dta[z][1] - dta[y][1]) / dta[y][1]) / ((dta[z][0] - dta[y][0])/(60*60));
                hcdata.pctchart.push([o.x, hcdata.pct[o.x] * 100]);
            }
            if (lastTimeLeft != dta[x][2]) {
                if (lastTimeLeft != -1) {
                    hcdata.zones.push({
                        value: o.x,
                        fillColor: colors.timeleft[lastTimeLeft],
                    });
                }
                lastTimeLeft = dta[x][2];
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
            hcdata.timeleft[dta[x][0]*1000] = dta[x][2];
            if (maxPrice < dta[x][1]) {
                maxPrice = dta[x][1];
            }
        }
        if (hcdata.zones.length) {
            hcdata.zones.push({
                fillColor: colors.timeleft[lastTimeLeft]
            });
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
                    min: 0,
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
                    var tr = '<b>' + Highcharts.dateFormat('%a %b %d, %I:%M%P', this.x) + '</b>';
                    tr += '<br><span style="color: ' + colors.text + '">Price: ' + wowtoken.NumberCommas(hcdata.realPrices[this.x]) + 'g</span>';
                    if (hcdata.pct.hasOwnProperty(this.x)) {
                        tr += '<br><span style="color: #444">Rate: ' + (hcdata.pct[this.x] > 0 ? '+' : '') + (hcdata.pct[this.x]*100).toFixed(2) + '%/hr</span>';
                    }
                    tr += '<br><span style="color: ' + colors.text + '">Sells in: ' + wowtoken.timeLeftMap.names[hcdata.timeleft[this.x]] + '</span>';
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
                    //zoneAxis: 'x',
                    //zones: hcdata.zones
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
                    //zoneAxis: 'x',
                    //zones: hcdata.zones
                }

            ]
        });
    }
}

$(document).ready(wowtoken.Main);
