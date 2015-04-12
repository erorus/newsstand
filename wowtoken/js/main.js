var wowtoken = {

    timeLeftMap: {
        names: ['',
            'less than 30 mins',
            '30 mins to 2 hours',
            '2 to 12 hours',
            'over 12 hours'
        ],
        colors: ['',
            'red',
            'orange',
            'yellow',
            'green'
        ]
    },

    NumberCommas: function(v) {
        return (''+v).split("").reverse().join("").replace(/(\d{3})(?=\d)/g, '$1,').split("").reverse().join("");
    },

    Main: function ()
    {
        wowtoken.LoadHistory();
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

    ShowChart: function(region, dta, dest) {
        var hcdata = { buy: [], timeleft: {} };
        var maxPrice = 0;
        var o, showLabel, direction = 0, newDirection = 0;
        var labelFormatter = function() {
            return wowtoken.NumberCommas(this.y) + 'g';
        };
        var colors = {
            'line': '#0000ff',
            'fill': 'rgba(204,204,255,0.6)',
            'text': '#000099',
        }
        if (region == 'EU') {
            colors = {
                'line': '#ff0000',
                'fill': 'rgba(255,204,204,0.6)',
                'text': '#990000',
            }
        }
        for (var x = 0; x < dta.length; x++) {
            o = {
                x: dta[x][0]*1000,
                y: dta[x][1],
                //color: wowtoken.timeLeftMap.colors[dta[x][2]]
            };
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
            if (showLabel) {
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

        Highcharts.setOptions({
            global: {
                useUTC: false
            }
        });

        $(dest).highcharts({
            chart: {
                zoomType: 'x',
                backgroundColor: '#f6fff6'
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
                maxZoom: 4 * 3600000, // four hours
                title: {
                    text: null
                },
                labels: {
                    style: {
                        color: 'black'
                    }
                }
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
                    }
                }
            },
            series: [
                {
                    type: 'area',
                    name: 'Market Price',
                    color: colors.line,
                    lineColor: colors.line,
                    fillColor: colors.fill,
                    data: hcdata.buy
                }
            ]
        });
    }
}

/*!
 * contentloaded.js
 *
 * Author: Diego Perini (diego.perini at gmail.com)
 * Summary: cross-browser wrapper for DOMContentLoaded
 * Updated: 20101020
 * License: MIT
 * Version: 1.2
 *
 * URL:
 * http://javascript.nwbox.com/ContentLoaded/
 * http://javascript.nwbox.com/ContentLoaded/MIT-LICENSE
 *
 */
// @win window reference
// @fn function reference
wowtoken.ContentLoaded = function(win, fn) {
    var done = false, top = true,
        doc = win.document,
        root = doc.documentElement,
        modern = doc.addEventListener,
        add = modern ? 'addEventListener' : 'attachEvent',
        rem = modern ? 'removeEventListener' : 'detachEvent',
        pre = modern ? '' : 'on',
        init = function(e) {
            if (e.type == 'readystatechange' && doc.readyState != 'complete') return;
            (e.type == 'load' ? win : doc)[rem](pre + e.type, init, false);
            if (!done && (done = true)) fn.call(win, e.type || e);
        },
        poll = function() {
            try { root.doScroll('left'); } catch(e) { setTimeout(poll, 50); return; }
            init('poll');
        };
    if (doc.readyState == 'complete') fn.call(win, 'lazy');
    else {
        if (!modern && root.doScroll) {
            try { top = !win.frameElement; } catch(e) { }
            if (top) poll();
        }
        doc[add](pre + 'DOMContentLoaded', init, false);
        doc[add](pre + 'readystatechange', init, false);
        win[add](pre + 'load', init, false);
    }
}

wowtoken.ContentLoaded(window, wowtoken.Main);