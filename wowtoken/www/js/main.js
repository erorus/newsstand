$(document).ready((new function() {
var wowtoken = {

    timeLeftMap: {
        names: ['',
            'less than 30 mins',
            '30 mins to 2 hours',
            '2 to 12 hours',
            'over 12 hours'
        ]
    },

    regions: {
        'na': 'NA',
        'eu': 'EU',
        'cn': 'CN',
        'tw': 'TW',
        'kr': 'KR',
    },

    NumberCommas: function(v) {
        return v.toFixed().split("").reverse().join("").replace(/(\d{3})(?=\d)/g, '$1,').split("").reverse().join("");
    },

    PrettySeconds: function(s) {
        s = parseInt(s, 10);

        if (s <= 0) {
            return 'Immediately';
        }
        if (s <= 90) {
            return '' + s + " seconds";
        }
        var m = Math.round(s/60);
        if (m <= 90) {
            return '' + m + ' minutes';
        }
        var h = Math.floor(m/60);
        m = m % 60;
        return '' + h + ' hours, ' + m + ' minutes';
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
        var abg;

        var fail = function() {
            var e = document.getElementsByClassName('realm-panel');
            for (var x = e.length - 1; x >= 0; x--) {
                e[x].parentNode.removeChild(e[x]);
            }
            document.getElementById('block-warn').style.display = '';
            return false;
        };

        var pixelRegex = /^\d+px$/;

        var test = function() {
            if (abg && abg != window.adsbygoogle) {
                return fail();
            }

            var divs = $('ins.adsbygoogle:not([class~="adsbygoogle-noablate"])');
            if (divs.length != 1) {
                return fail();
            }

            var s = window.getComputedStyle(divs[0]);
            if (s.display != 'block') {
                return fail();
            }
            if (s.position != 'static') {
                return fail();
            }
            if (s.overflowY != 'visible' || s.overflowX != 'visible' || s.overflow != 'visible') {
                return fail();
            }
            if (s.opacity != '1') {
                return fail();
            }
            if (!pixelRegex.test(s.height) || parseInt(s.height,10) < 50) {
                return fail();
            }
            if (!pixelRegex.test(s.width) || parseInt(s.width,10) < 100) {
                return fail();
            }

            return true;
        };

        window.setTimeout(test, 500);
        window.setTimeout(test, 1000);
        window.setTimeout(test, 2500);
        window.setTimeout(test, 5000);
        window.setTimeout(test, 10000);

        var s = document.createElement('script');
        s.src = '//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js';
        $(s).on('error', fail);
        $(s).on('load', function() {
            abg = window.adsbygoogle;

            if (!abg.loaded || !abg.push || abg.push.name == 'push') fail();

            if (abg.push.toString().replace(/\s+/g,'').length < 25) fail();

            try {
                window.adsbygoogle.push({});
            } catch (e) {
                fail();
            }

            if (test()) {
                wowtoken.LoadHistory();
            }
        });
        document.getElementsByTagName('head')[0].appendChild(s);

        wowtoken.LastVisitCheck();
        wowtoken.EUCheck();
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
            success: wowtoken.ShowHistory,
            url: ((location.hostname.indexOf('wowtoken.info') >= 0) ? '//data.wowtoken.info/' : '') + 'wowtoken.json'
        });
    },

    ShowHistory: function (d)
    {
        wowtoken.ParseUpdate(d.update);

        var dest;
        for (var region in d.history) {
            if (d.history[region].length) {
                if (dest = document.getElementById('hc-'+region.toLowerCase())) {
                    dest.className = 'hc';
                    wowtoken.ShowChart(region, d.history[region], dest);
                }
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
                $('#'+region+'-'+attrib+'-left').css('left', d[region].formatted[attrib] + '%');
            }
        }
    },

    ShowChart: function(region, dta, dest) {
        var hcdata = { buy: [], timeleft: {}, navigator: [], pct: {}, pctchart: [], realPrices: [] };
        var maxPrice = 0;
        var o, showLabel, direction = 0, newDirection = 0, lastLabel = -1;
        var priceUpperBound = 0;
        var colors = {
            'line': '#000000',
            'fill': 'rgba(51,51,51,0.6)',
            'text': '#000000',
        };

        switch (region) {
            case 'NA':
                colors = {
                    'line': '#0000ff',
                    'fill': 'rgba(204,204,255,0.6)',
                    'text': '#000099',
                };
                break;
            case 'EU':
                colors = {
                    'line': '#ff0000',
                    'fill': 'rgba(255,204,204,0.6)',
                    'text': '#990000',
                }
                break;
            case 'CN':
                colors = {
                    'line': '#00cc00',
                    'fill': 'rgba(178,230,178,0.6)',
                    'text': '#009900',
                }
                break;
            case 'TW':
                colors = {
                    'line': '#cccc00',
                    'fill': 'rgba(230,230,178,0.6)',
                    'text': '#999900',
                }
                break;
            case 'KR':
                colors = {
                    'line': '#00cccc',
                    'fill': 'rgba(178,230,230,0.6)',
                    'text': '#009999',
                }
                break;
        }
        var labelFormatter = function() {
            return wowtoken.NumberCommas(hcdata.realPrices[this.x]) + 'g';
        };

        for (var x = 0; x < dta.length; x++) {
            if (priceUpperBound < dta[x][1]) {
                priceUpperBound = dta[x][1];
            }
        }
        priceUpperBound = (Math.round(priceUpperBound / 20000) + 1) * 20000;

        for (x = 0; x < dta.length; x++) {
            o = {
                x: dta[x][0]*1000,
                y: dta[x][1],
            };
            hcdata.navigator.push([dta[x][0]*1000, dta[x][1]]);
            if (x != 0) {
                for (var y = x-1; y > 0; y--) {
                    if (dta[y][0] <= (dta[x][0] - 55*60)) {
                        break;
                    }
                }
                z = x;
                hcdata.pct[o.x] = ((dta[z][1] - dta[y][1]) / dta[y][1]) / ((dta[z][0] - dta[y][0])/(60*60));
                hcdata.pctchart.push([o.x, hcdata.pct[o.x] * 100]);
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
            showLabel &= (lastLabel == -1) || (Math.abs((dta[x][1] - dta[lastLabel][1]) / dta[x][1]) > 0.05);
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
            /*
            hcdata.timeleft[dta[x][0]*1000] = wowtoken.timeLeftMap.names[dta[x][2]];
            if (dta[x][3] != null) {
                hcdata.timeleft[dta[x][0]*1000] = wowtoken.PrettySeconds(dta[x][3]);
            }
            */
            if (maxPrice < dta[x][1]) {
                maxPrice = dta[x][1];
            }
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
                    var tr = '<b>' + Highcharts.dateFormat('%a %b %e %Y, %l:%M%P', this.x) + '</b>';
                    tr += '<br><span style="color: ' + colors.text + '">Price: ' + wowtoken.NumberCommas(hcdata.realPrices[this.x]) + 'g</span>';
                    if (hcdata.pct.hasOwnProperty(this.x)) {
                        tr += '<br><span style="color: #444">Rate: ' + (hcdata.pct[this.x] > 0 ? '+' : '') + (hcdata.pct[this.x]*100).toFixed(2) + '%/hr</span>';
                    }
                    //tr += '<br><span style="color: ' + colors.text + '">Sells in: ' + hcdata.timeleft[this.x] + '</span>';
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
                }
            ]
        });
    }
};

this.Main = wowtoken.Main;

}).Main);
