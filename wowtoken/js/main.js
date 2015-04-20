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
        return (''+v).split("").reverse().join("").replace(/(\d{3})(?=\d)/g, '$1,').split("").reverse().join("");
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
        wowtoken.LoadHistory();
        window.setTimeout(wowtoken.UpdateCheck, 60000*5);
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
                wowtoken.ShowHistory(d);
            },
            url: '/history.json'
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

    UpdateCheck: function ()
    {
        $.ajax({
            success: function (d)
            {
                wowtoken.ParseUpdate(d);
            },
            url: '/now.json'
        });
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
        window.setTimeout(wowtoken.UpdateCheck, 60000*5);
    },

    ShowChart: function(region, dta, dest) {
        var hcdata = { buy: [], timeleft: {}, navigator: [], zones: [] };
        var maxPrice = 0;
        var o, showLabel, direction = 0, newDirection = 0, lastLabel = -1;
        var lastTimeLeft = -1;
        var labelFormatter = function() {
            return wowtoken.NumberCommas(this.y) + 'g';
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
        for (var x = 0; x < dta.length; x++) {
            o = {
                x: dta[x][0]*1000,
                y: dta[x][1],
                //color: wowtoken.timeLeftMap.colors[dta[x][2]]
            };
            hcdata.navigator.push([dta[x][0]*1000, dta[x][1]]);
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
                selected: 1,
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
                    text: null
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
                        formatter: function ()
                        {
                            return document.ontouchstart === undefined ?
                                '' + wowtoken.NumberCommas(this.value) + 'g' :
                                '' + Math.floor(this.value/1000) + 'k' ;
                        },
                        style: {
                            color: 'black'
                        }
                    },
                    min: 0,
                    max: maxPrice
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
                    tr += '<br><span style="color: ' + colors.text + '">Price: ' + wowtoken.NumberCommas(this.points[0].y) + 'g</span>';
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
                    zoneAxis: 'x',
                    zones: hcdata.zones
                }
            ]
        });
    }
}

$(document).ready(wowtoken.Main);
