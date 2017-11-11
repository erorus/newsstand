var libtuj = {
    ce: function (tag)
    {
        if (!tag) {
            tag = 'div';
        }
        return document.createElement(tag);
    },
    sprintf: function ()
    {
        var args = arguments;
        return args[0].replace(/{(\d+)}/g, function(match, number) {
            return typeof args[number] != 'undefined'
                ? args[number]
                : match
                ;
        });
    },
    Mean: function (a)
    {
        if (a.length < 1) {
            return null;
        }
        var s = 0;
        for (var x = 0; x < a.length; x++) {
            s += a[x];
        }
        return s / a.length;
    },
    Median: function (a)
    {
        if (a.length < 1) {
            return null;
        }
        if (a.length == 1) {
            return a[0];
        }

        a.sort(function (x, y)
        {
            return y - x;
        });
        if (a.length % 2 == 1) {
            return a[Math.floor(a.length / 2)];
        }
        else {
            return (a[a.length / 2 - 1] + a[a.length / 2]) / 2;
        }
    },
    StdDev: function (a, mn)
    {
        if (typeof mn == 'undefined') {
            mn = libtuj.Mean(a);
        }
        var s = 0;
        for (var x = 0; x < a.length; x++) {
            s += Math.pow(a[x] - mn, 2);
        }
        return Math.sqrt(s / a.length);
    },
    Least: function (a)
    {
        if (a.length == 0) {
            return undefined;
        }

        var tr = a[0];
        for (var x = 1; x < a.length; x++) {
            if (a[x] < tr) {
                tr = a[x];
            }
        }
        return tr;
    },
    IconURL: function(nm, size)
    {
        if (!nm) {
            nm = 'inv_misc_questionmark';
        }
        return tujCDNPrefix + 'icon/' + size + '/' + nm.replace(/ /g, '-') + (size == 'tiny' ? '.png' : '.jpg');
    },
    LevelOffsetBonus: function(offset)
    {
        offset = parseInt(offset, 10);
        if (offset >= -400 && offset < -100) {
            return 3229 + offset;
        }
        if (offset >= -100 && offset <= 200) {
            return 1472 + offset;
        }
        if (offset > 200 && offset <= 400) {
            return 2929 + offset;
        }
        return '';
    },
    FormatPrice: function (amt, justValue, shorter)
    {
        var v = '', g, c;
        if (typeof amt == 'number') {
            amt = Math.round(amt);
            if (amt >= 100) {// 1s
                if (shorter && amt >= 10000000) { // 1000g
                    g = Math.round(amt / 10000).toLocaleString();
                } else {
                    g = (amt / 10000).toFixed(2);
                }
                v = '' + g + tuj.lang.suffixGold;
            } else {
                c = amt;
                v = '' + c + tuj.lang.suffixCopper;
            }
        }
        if (justValue) {
            return v;
        }

        var s = libtuj.ce('span');
        s.className = 'price';
        $(s).data('sort', v ? amt : 0);
        if (v) {
            if (g) {
                s.appendChild(document.createTextNode(g));
                s.className = 'money-gold';
            } else {
                s.appendChild(document.createTextNode(c));
                s.className = 'money-copper';
            }
        }
        return s;
    },
    FormatFullPrice: function (amt, justValue)
    {
        var v = '';
        if (typeof amt == 'number') {
            amt = Math.round(amt);
            var g = Math.floor(amt / 10000);
            var s = Math.floor((amt % 10000) / 100);
            var c = Math.floor(amt % 100);

            if (g) {
                v += '' + g + tuj.lang.suffixGold + ' ';
            }
            if (g || s) {
                v += '' + s + tuj.lang.suffixSilver + ' ';
            }
            v += '' + c + tuj.lang.suffixCopper;
        }
        if (justValue) {
            return v;
        }

        var sp = libtuj.ce('span');
        sp.className = 'price full';
        $(sp).data('sort', v ? amt : 0);
        if (v) {
            var s2 = libtuj.ce('span');
            if (g) {
                s2 = libtuj.ce('span');
                s2.className = 'money-gold';
                s2.appendChild(document.createTextNode(g));
                sp.appendChild(s2);
            }
            if (g || s) {
                s2 = libtuj.ce('span');
                s2.className = 'money-silver';
                s2.appendChild(document.createTextNode(s));
                sp.appendChild(s2);
            }
            s2 = libtuj.ce('span');
            s2.className = 'money-copper';
            s2.appendChild(document.createTextNode(c));
            sp.appendChild(s2);
        }
        return sp;
    },
    FormatQuantity: function (amt, justValue)
    {
        var v = Number(Math.round(amt)).toLocaleString();
        if (Math.round(amt * 100) % 100 != 0) {
            v = Number(amt).toFixed(2);
        }
        if (justValue) {
            return v;
        }

        var s = libtuj.ce('span');
        $(s).data('sort', v ? Number(amt) : 0);
        if (v) {
            s.appendChild(document.createTextNode(v));
        }
        return s;
    },
    FormatDate: function (unix, justValue, stopAt, noFormat)
    {
        var v = '', n, a;
        if (stopAt) {
            stopAt = stopAt.toLowerCase().replace(/s$/, '');
        }

        if (unix) {
            var dt, now = new Date();
            if (typeof unix == 'string') {
                dt = new Date(unix.replace(/^(\d{4}-\d\d-\d\d) (\d\d:\d\d:\d\d)$/, '$1T$2.000Z'));
            }
            else {
                if (unix <= 0) {
                    unix = Math.abs(unix) + Math.floor(now.getTime() / 1000);
                }
                dt = new Date(unix * 1000);
            }

            if (dt.getTime() > 978307200000) {
                var diff = Math.floor((now.getTime() - dt.getTime()) / 1000);
                var timeFormat = noFormat ? '{1}' : (diff < 0 ? tuj.lang.timeFuture : tuj.lang.timePast);
                diff = Math.abs(diff);

                if ((diff < 60) || (stopAt == 'second')) {
                    v = libtuj.sprintf(timeFormat, '' + (n = diff) + ' ' + (n != 1 ? tuj.lang.timeSeconds : tuj.lang.timeSecond));
                }
                else {
                    if ((diff < 60 * 60) || (stopAt == 'minute')) {
                        v = libtuj.sprintf(timeFormat, '' + (n = Math.round(diff / 60)) + ' ' + (n != 1 ? tuj.lang.timeMinutes : tuj.lang.timeMinute));
                    }
                    else {
                        if ((diff < 24 * 60 * 60) || (stopAt == 'hour')) {
                            v = libtuj.sprintf(timeFormat, '' + (n = Math.round(diff / (60 * 60))) + ' ' + (n != 1 ? tuj.lang.timeHours : tuj.lang.timeHour));
                        }
                        else {
                            if ((diff < 10 * 24 * 60 * 60) || (stopAt == 'day')) {
                                v = libtuj.sprintf(timeFormat, '' + (n = Math.round(diff / (24 * 60 * 60))) + ' ' + (n != 1 ? tuj.lang.timeDays : tuj.lang.timeDay));
                            }
                            else {
                                v = dt.toLocaleDateString();
                            }
                        }
                    }
                }
            } else {
                v = tuj.lang.unknown;
            }
        }
        if (justValue) {
            return v;
        }

        var s = libtuj.ce('span');
        $(s).data('sort', v ? dt.valueOf() : 0);
        if (v) {
            a = libtuj.ce('abbr');
            a.className = 'full-date';
            a.title = dt.toLocaleString();
            a.appendChild(document.createTextNode(v));
            s.appendChild(a);
        }
        return s;
    },
    FormatAge: function (diffByte, justValue)
    {
        var v = '', n, a;

        var diff = diffByte * 48 / 255;
        if (!isNaN(diff)) {
            v = '' + (n = diff.toFixed(1)) + ' ' + (n != '1.0' ? tuj.lang.timeHoursAbbrev : tuj.lang.timeHourAbbrev);
        }

        if (justValue) {
            return v;
        }

        var s = libtuj.ce('span');
        $(s).data('sort', v ? diff : 0);
        if (v) {
            s.className = 'age';
            s.appendChild(document.createTextNode(v));
        }
        return s;
    },
    GetRealmsForHouse: function (house, maxLineLength, includeRegion)
    {
        var lineLength = 0;
        var realmNames = '', realmName;

        for (var regionId in tuj.validRegions) {
            if (!tuj.validRegions.hasOwnProperty(regionId)) {
                continue;
            }

            for (var x in tuj.allRealms[regionId]) {
                if (tuj.allRealms[regionId].hasOwnProperty(x) && tuj.allRealms[regionId][x].house == house) {
                    realmName = tuj.allRealms[regionId][x].name;
                    if (includeRegion || (tuj.realms != tuj.allRealms[regionId])) {
                        realmName = tuj.validRegions[regionId] + ' ' + realmName;
                    }

                    if (maxLineLength && lineLength > 0 && lineLength + realmName.length > maxLineLength) {
                        realmNames += '<br>';
                        lineLength = 0;
                    }
                    lineLength += 2 + realmName.length;
                    realmNames += realmName + ', ';
                }
            }
        }

        if (realmNames == '') {
            realmNames = '(House ' + house + ')';
        }
        else {
            realmNames = realmNames.substr(0, realmNames.length - 2);
        }

        return realmNames;
    },
    GetHousePopulation: function (house)
    {
        var pop = 0;

        for (var r in tuj.realms) {
            if (!tuj.realms.hasOwnProperty(r)) {
                continue;
            }

            if ((tuj.realms[r].house == house) && tuj.realms[r].hasOwnProperty('population') && tuj.realms[r].population) {
                pop += tuj.realms[r].population;
            }
        }

        return pop;
    },
    AlsoHover: function(eventTarget, applyTarget)
    {
        $(eventTarget)
            .on('mouseover', function(){ $(applyTarget).addClass('hover'); })
            .on('mouseout', function(){ $(applyTarget).removeClass('hover'); });
    },
    HydrateData: function(o)
    {
        if (!o.hasOwnProperty('hydrate')) {
            return o;
        }
        var d;
        for (var x in o.data) {
            if (!o.data.hasOwnProperty(x)) {
                continue;
            }
            d = o.data[x];
            for (var y = 0, h; h = o.hydrate[y]; y++) {
                if (!d.hasOwnProperty(h.key)) {
                    continue;
                }
                if (!h.values.hasOwnProperty(d[h.key])) {
                    continue;
                }
                $.extend(d, h.values[d[h.key]]);
            }
        }
        return o.data;
    },
    TableSort: {
        Make: function(table) {
            var $th, tr, idx, ths = table.getElementsByTagName('th');
            for (var th, x = 0; th = ths[x]; x++) {
                $th = $(th);
                if (!($th.hasClass('name') || $th.hasClass('price') || $th.hasClass('quantity') || $th.hasClass('date') || $th.hasClass('level'))) {
                    continue;
                }
                idx = 0;
                tr = th.parentNode;
                for (var y = 0; y < tr.childNodes.length; y++) {
                    if (tr.childNodes[y] == th) {
                        break;
                    }
                    if (tr.childNodes[y].colSpan && parseInt(tr.childNodes[y].colSpan, 10) > 1) {
                        idx += parseInt(tr.childNodes[y].colSpan, 10);
                    } else {
                        idx++;
                    }
                }
                if (y >= tr.childNodes.length) {
                    continue;
                }
                $th.on('click', libtuj.TableSort.Sort.bind(th, idx));
                $th.addClass('sortable');
            }
        },
        getRowSortValue: function(tr, idx, colSpan) {
            var r, eles, td, tds = tr.getElementsByTagName('td');
            for (var x = idx; x < idx + colSpan; x++) {
                td = tds[x];
                if (!td) {
                    continue;
                }
                eles = Array.from($(td).find('*'));
                for (var y = 0; y < eles.length; y++) {
                    r = $(eles[y]).data('sort');
                    if (typeof r != 'undefined') {
                        return r;
                    }
                }
            }
            return undefined;
        },
        Sort: function(idx) {
            var th = this;
            var colSpan = parseInt(th.colSpan || 1, 10);
            var t = th.parentNode.parentNode;

            var trs = Array.from(t.getElementsByTagName('tr'));
            trs = trs.filter(function(tr){
                return tr.getElementsByTagName('td').length > 0;
            });

            var curSort = $(t).data('sorted');
            var backwards = (curSort == idx + 1) ? -1 : 1;
            $(t).data('sorted', (idx + 1) * backwards);

            trs.sort(function(a,b){
                var aVal = libtuj.TableSort.getRowSortValue(a, idx, colSpan);
                var bVal = libtuj.TableSort.getRowSortValue(b, idx, colSpan);
                if (typeof aVal == 'undefined' || typeof bVal == 'undefined') {
                    return 0;
                }
                if (aVal == bVal) {
                    return 0;
                }
                if (!isNaN(aVal) && !isNaN(bVal)) {
                    return (Number(aVal) < Number(bVal) ? -1 : 1) * backwards;
                }
                return ('' + aVal).localeCompare('' + bVal) * backwards;
            });

            for (var x = 0; x < trs.length; x++) {
                trs[x].parentNode.appendChild(trs[x]);
            }
        }
    },
    Searched: {
        key: 'searched-list-',
        current: [],
        Add: function (v)
        {
            if (!v) {
                return;
            }
            var LIST_LENGTH = 15;

            var items = libtuj.Storage.Get(libtuj.Searched.key + tuj.locale);
            if (items === false) {
                items = libtuj.Searched.current;
            }
            if (items.indexOf(v) >= 0) {
                return;
            }
            if (items.unshift(v) > LIST_LENGTH) {
                items.splice(LIST_LENGTH);
            }
            libtuj.Searched.current = items;

            libtuj.Storage.Set(libtuj.Searched.key + tuj.locale, libtuj.Searched.current);

            libtuj.Searched.Update();
        },
        Update: function()
        {
            var items = libtuj.Storage.Get(libtuj.Searched.key + tuj.locale) || libtuj.Searched.current;
            var ele = document.getElementById('searched-previously');
            $(ele).empty();
            for (var x = 0; x < items.length; x++) {
                var o = libtuj.ce('option');
                o.value = items[x];
                ele.appendChild(o);
            }
        }
    },
    Ads: {
        addCount: 0,
        adsWillShow: true,
        Add: function (slot, cssClass)
        {
            var ad = libtuj.ce();
            ad.className = 'ad';
            if (cssClass) {
                ad.className += ' ' + cssClass;
            }

            var ins = libtuj.ce('ins');
            ad.appendChild(ins);
            ins.className = 'adsbygoogle';
            ins.setAttribute('data-ad-client', 'ca-pub-1018837251546750');
            ins.setAttribute('data-ad-slot', slot);

            libtuj.Ads.addCount++;

            return ad;
        },
        Show: function ()
        {
            if (!tuj.UserSeesAds()) {
                libtuj.Ads.addCount = 0;
                $('div.ad, div.adsubstitute').remove();
                return;
            }
            if (!libtuj.Ads.adsWillShow) {
                libtuj.Ads.ShowSubstitutes();
                return;
            }
            while (libtuj.Ads.addCount > 0) {
                (window.adsbygoogle = window.adsbygoogle || []).push({});
                libtuj.Ads.addCount--;
            }
        },
        ShowSubstitutes: function ()
        {
            var html = "<div>The Undermine Journal's servers cost over $100 every month.</div><div>We rely on simple Google AdSense ads and paid subscriptions to pay our bills.</div><div><br>Please whitelist ads here or <a href=\"" + tuj.BuildHash({'page':'subscription', 'id': ''}) + "\">purchase a paid subscription</a> to keep the site online. Thank you.</div>";
            $('div.ad').removeClass('ad').addClass('adsubstitute').html(html);
        },
        onWindowLoad: function () {
            libtuj.Ads.adsWillShow = window.adsbygoogle && !$.isArray(window.adsbygoogle);
            if (!libtuj.Ads.adsWillShow) {
                libtuj.Ads.ShowSubstitutes();
            }
        }
    },
    Storage: {
        Get: function (key)
        {
            try {
                if (!window.localStorage) {
                    return false;
                }
            } catch (e) {
                return false;
            }

            var v = window.localStorage.getItem(key);
            if (v != null) {
                return JSON.parse(v);
            } else {
                return false;
            }
        },
        Set: function (key, val)
        {
            try {
                if (!window.localStorage) {
                    return false;
                }
            } catch (e) {
                return false;
            }

            window.localStorage.setItem(key, JSON.stringify(val));
            return true;
        },
        Remove: function (key, val)
        {
            try {
                if (!window.localStorage) {
                    return false;
                }
            } catch (e) {
                return false;
            }

            window.localStorage.removeItem(key);
            return true;
        }
    }
};

var tujConstants = {
    itemClassOrder: [2, 9, 6, 4, 7, 3, 14, 1, 15, 8, 16, 10, 12, 13, 17, 18, 5, 11],
    locales: {
        enus: 'English',
        dede: 'Deutsch',
        eses: 'Español',
        frfr: 'Français',
        itit: 'Italiano',
        ptbr: 'Português',
        ruru: 'Русский'
    },
    siteColors: {
        light: {
            background: '#FFFFFF',
            text: '#666666',
            data: '#000000',
            bluePrice: '#0000FF',
            bluePriceFill: '#CCCCFF',
            bluePriceFillAlpha: 'rgba(153,153,255,0.66)',
            bluePriceBackground: '#6666FF',
            greenPrice: '#00FF00',
            greenPriceDim: '#009900',
            greenPriceFill: 'rgba(204,255,204,0.5)',
            greenPriceBackground: '#66CC66',
            redQuantity: '#FF3333',
            redQuantityFill: '#FF9999',
            redQuantityFillLight: '#FFCCCC',
            redQuantityBackground: '#FF6666',
        },
        dark: {
            background: '#333333',
            text: '#CCCCCC',
            data: '#FFFFFF',
            bluePrice: '#9999FF',
            bluePriceFill: '#6666CC',
            bluePriceFillAlpha: 'rgba(51,51,204,0.66)',
            bluePriceBackground: '#6666CC',
            greenPrice: '#99FF99',
            greenPriceDim: '#99CC99',
            greenPriceFill: 'rgba(102,204,102,0.5)',
            greenPriceBackground: '#66CC66',
            redQuantity: '#DD3333',
            redQuantityFill: '#996666',
            redQuantityFillLight: '#996666',
            redQuantityBackground: '#CC6666',
        }
    }
};

if (!Date.now) {
    Date.now = function now() {
        return new Date().getTime();
    };
}

var TUJ = function ()
{
    var validRegions = ['US','EU'];
    var validPages = ['', 'search', 'item', 'seller', 'battlepet', 'contact', 'category', 'transmog', 'subscription', 'policy'];
    var pagesNeedRealm = [true, true, true, true, true, false, true, true, false, false];
    var houseInfo = {};
    var drawnRegion = -1;
    var loggedInUser = false;
    var pendingCSRFProtectedRequests = [];
    this.validRegions = validRegions;
    this.realms = undefined;
    this.allRealms = undefined;
    this.apiVersion = 0;
    this.banned = {isbanned: false};
    this.params = {
        region: undefined,
        realm: undefined,
        page: undefined,
        id: undefined
    };
    this.locales = {};
    this.locale = false;
    this.lang = false;
    var hash = {
        sets: 0,
        changes: 0,
        reading: false,
        watching: false
    };
    var inMain = false;
    var self = this;
    var checkLanguageHeader = false;

    this.colorTheme = '';

    function Main()
    {
        if (inMain) {
            return;
        }
        inMain = true;

        var pageClasses = 'page-region page-realm page-front';
        for (var x = 1; x < validPages.length; x++) {
            pageClasses += ' page-' + validPages[x];
        }
        $(document.body).removeClass(pageClasses);

        if (self.colorTheme == '') {
            $('#bottom-bar .dark-only').click(SetDarkTheme.bind(self, false));
            $('#bottom-bar .light-only').click(SetDarkTheme.bind(self, true));

            SetDarkTheme(libtuj.Storage.Get('colorTheme') == 'dark');
        }

        if (self.locale == false) {
            var o, locSel = libtuj.ce('select');
            locSel.id = 'choose-locale';
            $(locSel).on('change', function() {
                LoadLocale(this.options[this.selectedIndex].value, true);
            });
            for (var loc in tujConstants.locales) {
                if (!tujConstants.locales.hasOwnProperty(loc)) {
                    continue;
                }
                o = libtuj.ce('option');
                o.value = loc;
                o.text = tujConstants.locales[loc];
                locSel.appendChild(o);
            }
            $('#super-bar').append(locSel);

            var savedLocale = libtuj.Storage.Get('locale');
            inMain = false;
            if (savedLocale && (savedLocale != 'enus')) {
                LoadLocale('enus', false);
                LoadLocale(savedLocale, true);
            } else {
                checkLanguageHeader = (savedLocale == false);
                LoadLocale('enus', true);
            }
            return;
        }

        if (typeof self.allRealms == 'undefined') {
            inMain = false;

            $('#progress-page').show();

            $.ajax({
                success: function (dta)
                {
                    self.allRealms = dta.realms;
                    if (dta.version) {
                        self.apiVersion = dta.version;
                    }
                    if (dta.hasOwnProperty('banned')) {
                        self.banned = dta.banned;
                    }
                    if (dta.hasOwnProperty('user')) {
                        if (dta.user.name) {
                            loggedInUser = dta.user;
                            FetchCSRFCookie();
                            if (loggedInUser.locale && tujConstants.locales.hasOwnProperty(loggedInUser.locale) && self.locale != loggedInUser.locale) {
                                checkLanguageHeader = false;
                                LoadLocale(loggedInUser.locale, true);
                            }
                        } else {
                            loggedInUser = false;
                        }
                    } else {
                        loggedInUser = false;
                    }
                    OnLoginStateChange();
                    if (dta.hasOwnProperty('language') && checkLanguageHeader) {
                        var l = dta.language.toLowerCase().replace(/[\s-]/, '').split(',');
                        var x, y, m, ls = [];
                        for (x = 0; x < l.length; x++) {
                            if (m = l[x].match(/^([\w]+);q=([\d\.]+)$/)) {
                                ls.push({l:m[1], q:parseFloat(m[2]), o:x});
                            } else {
                                ls.push({l:l[x], q:1, o:x});
                            }
                        }
                        ls.sort(function(a,b){
                            return (b.q - a.q) || (a.o - b.o);
                        });
                        var found = false;
                        for (x = 0; x < ls.length; x++) {
                            for (y in tujConstants.locales) {
                                if (!tujConstants.locales.hasOwnProperty(y)) {
                                    continue;
                                }
                                if (y.substr(0, ls[x].l.length) == ls[x].l) {
                                    LoadLocale(y, true);
                                    found = true;
                                    break;
                                }
                            }
                            if (found) {
                                break;
                            }
                        }
                        checkLanguageHeader = false;
                    }
                    if (typeof self.allRealms == 'undefined') {
                        alert('Error getting realms');
                        self.allRealms = [];
                    }
                    Main();
                },
                error: function (xhr, stat, er)
                {
                    if ((xhr.status == 503) && xhr.hasOwnProperty('responseJSON') && xhr.responseJSON && xhr.responseJSON.hasOwnProperty('maintenance')) {
                        self.APIMaintenance(xhr.responseJSON.maintenance);
                    } else {
                        alert('Error getting realms: ' + stat + ' ' + er);
                    }
                    self.allRealms = [];
                    loggedInUser = false;
                    OnLoginStateChange();
                },
                complete: function ()
                {
                    $('#progress-page').hide();
                },
                data: {
                    'getuser': 1
                },
                method: 'POST',
                url: 'api/realms.php'
            });
            return;
        }

        var ls, firstRun = !hash.watching;
        CheckForProxy();
        ReadParams();

        if (self.params.realm) {
            $('#topcorner').addClass('with-realm');
        } else {
            $('#topcorner').removeClass('with-realm');
        }

        if (firstRun) {
            if (!self.params.realm) {
                var searchRealm;
                if (searchRealm = /^\?realm=([AH])-([^&]+)/i.exec(decodeURIComponent(location.search))) {
                    ls = {};
                    var guessRegion = self.params.region == undefined ? 0 : self.params.region;
                    for (var x in tuj.allRealms[guessRegion]) {
                        if (tuj.allRealms[guessRegion][x].name.toLowerCase() == searchRealm[2].toLowerCase()) {
                            ls.realm = tuj.allRealms[guessRegion][x].id;
                            ls.region = guessRegion;
                        }
                    }

                    if (ls.hasOwnProperty('realm')) {
                        inMain = false;
                        tuj.SetParams(ls);
                        return;
                    }
                }

                if (self.params.region == undefined && (ls = libtuj.Storage.Get('defaultRealm')) && ls.hasOwnProperty('region')) {
                    var url = location.protocol + '//' + location.hostname + '/';
                    if (!(document.referrer && document.referrer.substr(0, url.length) == url)) {
                        inMain = false;
                        tuj.SetParams(ls);
                        return;
                    }
                }
            }

            if (location.search) {
                location.href = location.pathname + location.hash;
            }
        }

        if (loggedInUser && !loggedInUser.acceptedterms && self.params.page != 10 && self.params.id != 'accept') { // policy
            inMain = false;
            self.SetParams({'page': 'policy', 'id': 'accept'});
            return;
        }

        UpdateSidebar();
        $('#page-title').empty();
        self.SetTitle();

        window.scrollTo(0, 0);

        if (!self.params.page || pagesNeedRealm[self.params.page]) {
            if (self.params.region == undefined) {
                inMain = false;
                $('#main .page').hide();
                $('#realm-list').removeClass('show');
                $('#region-page area.region-us').attr('href', tuj.BuildHash({region:0}));
                $('#region-page area.region-eu').attr('href', tuj.BuildHash({region:1}));

                $('#region-page h2').html(libtuj.sprintf(self.lang.welcomeTo, 'The Undermine Journal') + ' <sub>' + self.lang.yourSource + '</sub>');
                var pixelmap = document.getElementById('pixelmap');
                if (!pixelmap) {
                    pixelmap = libtuj.ce('img');
                    pixelmap.id = 'pixelmap';
                    pixelmap.src = 'images/compressed/pixelmap.png';
                    pixelmap.width = '1471';
                    pixelmap.height = '908';
                    pixelmap.useMap = '#mapmap';
                    $(pixelmap).on('load', function() {
                        $('map').imageMapResize();
                    });
                    document.getElementById('region-page').appendChild(pixelmap);
                }

                $('#region-page').show();
                $(document.body).addClass('page-region');
                return;
            }

            if ($('#realm-list').length == 0) {
                inMain = false;
                DrawRealms();
                return;
            }

            $('#main .page').hide();
            $('#realm-list').removeClass('show');
            if (!self.params.realm) {
                inMain = false;
                libtuj.Storage.Remove('defaultRealm');
                if (drawnRegion != self.params.region) {
                    DrawRealms();
                }
                $('#realm-list .realms-column a').each(function () {
                    this.href = self.BuildHash({realm: this.rel});
                });
                $('#realm-list .directions').text(libtuj.sprintf(self.lang.chooseRealm, validRegions[drawnRegion]));
                $('#realm-list').addClass('show');
                $(document.body).addClass('page-realm');
                return;
            }

            if (!self.params.page || (pagesNeedRealm[self.params.page] && self.banned.isbanned)) {
                inMain = false;
                $(document.body).addClass('page-front');
                ShowRealmFrontPage();
                return;
            }
        } else {
            $('#main .page').hide();
            $('#realm-list').removeClass('show');
        }

        inMain = false;

        $(document.body).addClass('page-' + validPages[self.params.page]);

        if (typeof tuj['page_' + validPages[self.params.page]] == 'undefined') {
            var s = libtuj.ce('script');
            s.type = 'text/javascript';
            s.src = tujCDNPrefix + 'js/' + validPages[self.params.page] + '.js?' + self.apiVersion;
            document.getElementsByTagName('head')[0].appendChild(s);
        }
        else {
            tuj['page_' + validPages[self.params.page]].load(self.params);
        }

    }

    function LoadLocale(locName, activate) {
        if (activate && self.locales.hasOwnProperty(locName)) {
            self.locale = locName;
            libtuj.Storage.Set('locale', self.locale);
            var locSel = document.getElementById('choose-locale');
            for (var x = 0; x < locSel.options.length; x++) {
                if (locSel.options[x].value == locName) {
                    locSel.selectedIndex = x;
                    break;
                }
            }
            self.lang = $.extend(true, {}, self.locales.enus, self.locales[self.locale]);

            if (!!(loggedInUser) && loggedInUser.locale != locName) {
                tuj.SendCSRFProtectedRequest({
                    data: {'newlocale': locName},
                    success: function(dta) {
                        loggedInUser = $.extend(loggedInUser, dta);
                    }
                });
            }

            Main();
            return;
        }

        if (typeof window.tujLocaleVersion == 'undefined') {
            window.tujLocaleVersion = 0;
        }

        $.ajax({
            success: function(dta) {
                if (dta.hasOwnProperty('localeCode')) {
                    self.locales[dta.localeCode] = dta;
                    if (activate) {
                        LoadLocale(dta.localeCode, true);
                    }
                }
            },
            error: function (xhr, stat, er)
            {
                alert('Error getting locale ' + locName + ': ' + stat + ' ' + er);
                if (locName != 'enus') {
                    LoadLocale('enus', true);
                }
            },
            url: 'js/locale/' + locName + '.json?' + window.tujLocaleVersion
        });
    }

    function CheckForProxy() {
        if (!self.banned.isbanned) {
            if ((typeof tujCDNPrefixChecksum != 'undefined') && (Fletcher16(tujCDNPrefix) != tujCDNPrefixChecksum)) {
                self.banned = {
                    isbanned: true,
                    reason: 'proxy'
                }
            }
        }
    }

    function Fletcher16(s) {
        var s1=0, s2=0;
        for (var x = 0; x < s.length; x++) {
            s1 = (s1 + (s.charCodeAt(x) & 255)) % 255;
            s2 = (s2 + s1) % 255;
        }
        return (s2 << 8) | s1;
    }

    this.LoggedInUserName = function() {
        return !!(loggedInUser) ? loggedInUser.name : false;
    };

    this.UserSeesAds = function() {
        return !(loggedInUser && loggedInUser.hasOwnProperty('ads') && !loggedInUser.ads);
    };

    this.UserAcceptsTerms = function() {
        if (!loggedInUser) {
            return false;
        }

        tuj.SendCSRFProtectedRequest({
            data: {'acceptsterms': 1},
            success: function(dta) {
                loggedInUser = $.extend(loggedInUser, dta);
                tuj.SetParams({'page': 'subscription', 'id': undefined});
            }
        });
    };

    this.LogOut = function(callbackOrEvent) {
        var callback;
        if (typeof callbackOrEvent == 'function') {
            callback = callbackOrEvent;
        }
        
        $.ajax({
            data: {
                'logout': 1
            },
            method: 'POST',
            success: function(dta) {
                loggedInUser = false;
                OnLoginStateChange();
                Main();
                if (callback) {
                    callback();
                }
            },
            error: function() {
                alert('Error logging out. Try again?');
            },
            url: 'api/subscription.php'
        });
    };

    function FetchCSRFCookie() {
        if (!loggedInUser.hasOwnProperty('csrfCookie')) {
            return;
        }
        if (loggedInUser.hasOwnProperty('csrfToken')) {
            return;
        }
        var i = libtuj.ce('iframe');
        i.style.display = 'none';
        $(i).on('load', function() {
            var cookies = i.contentDocument.cookie.replace('; ', ';').split(';');
            for (var x = 0; x < cookies.length; x++) {
                if (cookies[x].substr(0, loggedInUser.csrfCookie.length + 1) == (loggedInUser.csrfCookie + '=')) {
                    loggedInUser.csrfToken = cookies[x].substr(loggedInUser.csrfCookie.length + 1);
                    break;
                }
            }
            i.parentNode.removeChild(i);
            if (loggedInUser.hasOwnProperty('csrfToken')) {
                while (pendingCSRFProtectedRequests.length) {
                    self.SendCSRFProtectedRequest(pendingCSRFProtectedRequests.shift());
                }
            }
            pendingCSRFProtectedRequests = [];
        });
        i.src = '/api/csrf/';
        document.body.appendChild(i);
    }

    this.SendCSRFProtectedRequest = function(ajaxParams) {
        if (!loggedInUser.hasOwnProperty('csrfToken')) {
            pendingCSRFProtectedRequests.push(ajaxParams);
            return;
        }

        ajaxParams.url = 'api/subscription.php';
        ajaxParams.type = 'POST';
        ajaxParams.crossDomain = false;
        ajaxParams.global = false;
        if (!ajaxParams.hasOwnProperty('headers')) {
            ajaxParams.headers = {};
        }
        $.extend(ajaxParams.headers, {'X-CSRF-Token': loggedInUser.csrfToken});
        delete ajaxParams.beforeSend;
        $.ajax(ajaxParams);
    };

    function OnLoginStateChange() {
        if (!loggedInUser) {
            pendingCSRFProtectedRequests = [];
            $('body').removeClass('logged-in').addClass('logged-out');
        } else {
            $('body').removeClass('logged-out').addClass('logged-in');
        }
    }

    function ReadParams()
    {
        if (!hash.watching) {
            hash.watching = $(window).on('hashchange', ReadParams);
        }

        if (hash.reading) {
            return false;
        }

        if (hash.sets > hash.changes) {
            hash.changes++;
            return false;
        }

        if (hash.sets != hash.changes) {
            return false;
        }

        hash.reading = true;

        var p = {
            region: self.params.region,
            realm: self.params.realm,
            page: undefined,
            id: undefined
        };

        var h = decodeURIComponent(location.hash.replace('+', ' ')).toLowerCase();
        if (h.charAt(0) == '#') {
            h = h.substr(1);
        }
        h = h.split('/');

        var y;
        var gotFaction = false;
        var gotRealm = false;
        var gotRegion = -2;

        for (var x = 0; x < h.length; x++) {
            for (y = 0; y < validRegions.length; y++) {
                if (h[x].toUpperCase() == validRegions[y]) {
                    p.region = y;
                    if (p.region != self.params.region) {
                        p.realm = undefined;
                    }
                    gotRegion = x;
                    break;
                }
            }
        }
        if (gotRegion < 0 && self.params.region == undefined) {
            p.region = 0;
        }
        var realms = self.allRealms[p.region];

        nextParam:
            for (var x = 0; x < h.length; x++) {
                if (x == gotRegion) {
                    continue;
                }
                if (!p.page) {
                    for (y = 0; y < validPages.length; y++) {
                        if (h[x] == validPages[y]) {
                            p.page = y;
                            continue nextParam;
                        }
                    }
                }
                if (!gotFaction) {
                    if (h[x] == 'alliance' || h[x] == 'horde') {
                        gotFaction = true;
                        continue nextParam;
                    }
                }
                if (!gotRealm) {
                    for (y in realms) {
                        if (realms.hasOwnProperty(y) && h[x] == realms[y].slug) {
                            p.realm = y;
                            gotRealm = true;
                            continue nextParam;
                        }
                    }
                }
                p.id = h[x];
            }

        if (!gotRealm && (gotRegion < 0)) {
            p.region = undefined;
        }
        if (p.region != undefined && !gotRealm) {
            p.realm = undefined;
        }

        if (!self.SetParams(p)) {
            Main();
        }

        hash.reading = false;
    }

    this.SetParams = function (p, replaceHistory)
    {
        if (p) {
            for (var x in p) {
                if (p.hasOwnProperty(x) && self.params.hasOwnProperty(x)) {
                    self.params[x] = p[x];
                }
            }
        }

        if (self.params.region != undefined) {
            self.realms = self.allRealms[self.params.region];
        }

        if (typeof self.params.page == 'string') {
            for (var x = 0; x < validPages.length; x++) {
                if (validPages[x] == self.params.page) {
                    self.params.page = x;
                }
            }

            if (typeof self.params.page == 'string') {
                self.params.page = undefined;
            }
        }

        if (self.params.realm && !self.params.page) {
            libtuj.Storage.Set('defaultRealm', {region: self.params.region, realm: self.params.realm});
        }

        var h = self.BuildHash(self.params);

        if (h != decodeURIComponent(location.hash)) {
            hash.sets++;
            if (location.search) {
                location.href = location.pathname + h;
            }
            if (replaceHistory && location.replace) {
                location.replace(h);
            } else {
                location.hash = h;
            }
            Main();
            return true;
        }

        return false;
    };

    function UpdateSidebar()
    {
        $('#region-pick-US').html('<span>' + self.lang.regionUS + '</span> <a href="#eu">' + self.lang.lookingEU + '</a>');
        $('#region-pick-EU').html('<span>' + self.lang.regionEU + '</span> <a href="#us">' + self.lang.lookingUS + '</a>');

        if (self.params.region != undefined) {
            $('#topcorner > div').show();
            $('#topcorner .region-pick').hide();
            if (!self.params.realm) {
                var $regionPick = $('#topcorner #region-pick-' + validRegions[self.params.region]);
                $regionPick.show();
                $regionPick.find('a')[0].href = self.BuildHash({region: 1-self.params.region, realm: undefined});
            }

            var regionLink = $('#topcorner a.region');
            regionLink[0].href = self.BuildHash({region: undefined, realm: undefined});
            regionLink.text(self.validRegions[self.params.region]);

            var realmLink = $('#topcorner a.realm');
            realmLink[0].href = self.BuildHash({realm: undefined});
            realmLink.text(self.params.realm ? self.realms[self.params.realm].name : '');
        } else {
            $('#topcorner > div').hide();
        }

        if (!loggedInUser) {
            var loginLink = $('<a>');
            loginLink[0].href = self.BuildHash({page: 'subscription', id: ''});
            loginLink.text(self.lang.logIn);
            $('#login-info').removeClass('logged-in-only').addClass('logged-out-only').empty().append(loginLink);
        } else {
            var logoutLink = libtuj.ce('input');
            logoutLink.type = 'button';
            logoutLink.value = self.lang.logOut;
            $(logoutLink).click(self.LogOut);

            var subLink = $('<a>');
            subLink[0].href = self.BuildHash({page: 'subscription', id: ''});
            subLink.text(loggedInUser.name);

            $('#login-info').removeClass('logged-out-only').addClass('logged-in-only').empty().append(subLink).append(logoutLink);
        }

        var bottomBarLink, bottomBarLinks = ['contact','subscription','policy','addon','localization'];
        for (var x = 0, linkName; linkName = bottomBarLinks[x]; x++) {
            bottomBarLink = $('#bottom-bar a.' + linkName);
            bottomBarLink[0].href = self.BuildHash({page: linkName, id: undefined});
            bottomBarLink.html(self.lang[linkName]);
        }

        $('#bottom-bar a.dark-only').html(self.lang.lightTheme);
        $('#bottom-bar a.light-only').html(self.lang.darkTheme);

        $('#title a')[0].href = self.BuildHash({page: undefined});

        if ($('#topcorner form').length == 0) {
            var form = libtuj.ce('form');
            var i = libtuj.ce('input');
            i.name = 'search';
            i.type = 'text';
            i.placeholder = self.lang.search;
            i.id = 'searchbox';
            i.autocomplete = 'off';
            i.setAttribute('inputmode', 'latin');
            i.setAttribute('list', 'searched-previously');

            $(form).on('submit',function () {
                location.href = self.BuildHash({page: 'search', id: this.search.value.replace('/', '')});
                return false;
            }).append(i);

            var d = libtuj.ce('div');
            d.id = 'realm-updated';

            d.className = 'no-realm-hide';
            form.className = 'no-realm-hide';

            $('#topcorner .region-realm').after(d).before(form);

            libtuj.Searched.Update();
        } else {
            $('#topcorner form input')[0].placeholder = self.lang.search;
        }

        if (!$('#realm-favstar').data('init')) {
            $('#realm-favstar').data('init', 'y');
            $('#realm-favstar').on('click', ToggleRealmFavstar);
        }
        $('#realm-favstar').removeClass('on off');

        if (self.params.realm) {
            var house = self.realms[self.params.realm].house;
            var needUpdate = false;
            if (!houseInfo.hasOwnProperty(house)) {
                houseInfo[house] = {};
                needUpdate = true;
            } else {
                if (houseInfo.hasOwnProperty('timestamps')) {
                    needUpdate = (houseInfo[house].timestamps.delayednext || houseInfo[house].timestamps.scheduled) * 1000 < Date.now();
                }
            }

            if (needUpdate) {
                $.ajax({
                    data: {'house': house},
                    success: function (d)
                    {
                        SetHouseInfo(house, d);
                    },
                    url: 'api/house.php'
                });
            } else {
                SetHouseInfo(house);
            }

            var favRealms = libtuj.Storage.Get('favorite-realms') || [];
            $('#realm-favstar').addClass('off');
            for (var x = 0; x < favRealms.length; x++) {
                if (self.realms.hasOwnProperty(favRealms[x]) && self.realms[favRealms[x]].house == house) {
                    $('#realm-favstar').removeClass('off').addClass('on');
                    break;
                }
            }

            var lost = [];
            if (favRealms.length > 20) {
                lost = favRealms.splice(20);
                libtuj.Storage.Set('favorite-realms', favRealms);
                favRealms = favRealms.concat(lost);
            }

            favRealms = favRealms.filter(function(m) { return m != self.params.realm; });
            favRealms.sort(function(a, b){
                var aRegion = -1, bRegion = -1;
                for (var x = 0; x < tuj.allRealms.length; x++) {
                    if (tuj.allRealms[x].hasOwnProperty(a)) aRegion = x;
                    if (tuj.allRealms[x].hasOwnProperty(b)) bRegion = x;
                }
                if (aRegion != bRegion) {
                    return aRegion < bRegion ? -1 : 1;
                }
                if (aRegion == -1 || bRegion == -1) {
                    return 0;
                }
                return tuj.allRealms[aRegion][a].name.localeCompare(tuj.allRealms[bRegion][b].name);
            });

            var $fr = $('#favorite-realms');
            $fr.removeClass('with-realms').empty();
            if (favRealms.length) {
                for (var rg, r, x = 0; r = favRealms[x]; x++) {
                    rg = false;
                    for (y = 0; y < tuj.allRealms.length; y++) {
                        if (tuj.allRealms[y].hasOwnProperty(r)) {
                            rg = y;
                            break;
                        }
                    }
                    if (rg === false) {
                        continue;
                    }

                    var a = libtuj.ce('a');
                    if (lost.indexOf(r) < 0) {
                        a.href = self.BuildHash({region: rg, realm: r});
                    } else {
                        a.style.textDecoration = 'line-through';
                    }
                    a.appendChild(document.createTextNode(validRegions[rg] + ' ' + tuj.allRealms[rg][r].name));
                    $fr.append(a);
                }

                $fr.addClass('with-realms');
            }
        } else {
            $('#realm-updated').empty();
        }
    }

    function ToggleRealmFavstar() {
        var house = self.realms[self.params.realm].house;

        var favRealms = libtuj.Storage.Get('favorite-realms') || [];

        favRealms = favRealms.filter(function(r){
            var region = false;
            for (var x in tuj.allRealms) {
                if (!tuj.allRealms.hasOwnProperty(x)) {
                    continue;
                }
                if (tuj.allRealms[x].hasOwnProperty(r)) {
                    region = x;
                    break;
                }
            }
            return (region !== false) && (region != self.params.region || self.realms[r].house != house);
        });

        if (!$(this).hasClass('on')) {
            favRealms.unshift(self.params.realm);
        }

        libtuj.Storage.Set('favorite-realms', favRealms);

        UpdateSidebar();
    }

    function SetHouseInfo(house, dta)
    {
        var ru = document.getElementById('realm-updated');

        if (dta) {
            houseInfo[house] = dta;
        }

        if (!self.params.realm) {
            $(ru).empty();
            return;
        }

        if (!house) {
            house = self.realms[self.params.realm].house;
        }

        if (!houseInfo.hasOwnProperty(house)) {
            $(ru).empty();
            return;
        }

        $(ru).empty();

        if (!houseInfo[house].hasOwnProperty('timestamps')) {
            return;
        }

        var showAPIDownAlert = false;

        if (houseInfo[house].timestamps.lastupdate) {
            var d = libtuj.ce();
            d.appendChild(document.createTextNode(self.lang.updated + ' '));
            d.appendChild(libtuj.FormatDate(houseInfo[house].timestamps.lastupdate, false, 'minute'));
            ru.appendChild(d);
        }
        if (houseInfo[house].timestamps.scheduled && houseInfo[house].timestamps.scheduled * 1000 > Date.now()) {
            var d = libtuj.ce();
            d.appendChild(document.createTextNode(self.lang.nextUpdate + ' '));
            d.appendChild(libtuj.FormatDate(houseInfo[house].timestamps.scheduled, false, 'minute'));
            ru.appendChild(d);
        } else if (houseInfo[house].timestamps.hasOwnProperty('lastcheck')) {
            if (houseInfo[house].timestamps.lastcheck.ts) {
                var d = libtuj.ce();
                d.appendChild(document.createTextNode(self.lang.lastChecked + ' '));
                d.appendChild(libtuj.FormatDate(houseInfo[house].timestamps.lastcheck.ts, false, 'minute'));
                ru.appendChild(d);

                showAPIDownAlert =
                    houseInfo[house].timestamps.lastupdate &&
                    (houseInfo[house].timestamps.lastupdate < (Date.now() / 1000 - 172800)) && // last update over 48 hours ago
                    (houseInfo[house].timestamps.lastcheck.ts > (Date.now() / 1000 - 2100));   // checked at least 35 mins ago
            }
            if (houseInfo[house].timestamps.lastcheck.json) {
                if (houseInfo[house].timestamps.lastcheck.json.hasOwnProperty('reason')) {
                    if (houseInfo[house].timestamps.lastcheck.json.reason.length > 50) {
                        d = libtuj.ce('abbr');
                        d.style.overflow = 'hidden';
                        d.style.textOverflow = 'ellipsis';
                        d.style.whiteSpace = 'nowrap';
                        d.style.width = '100%';
                        d.style.display = 'block';
                        d.setAttribute('title', houseInfo[house].timestamps.lastcheck.json.reason);
                    } else {
                        d = libtuj.ce();
                    }
                    d.appendChild(document.createTextNode('Blizzard API: ' + houseInfo[house].timestamps.lastcheck.json.reason));
                    ru.appendChild(d);
                }
            }
        }

        if (!self.params.page) {
            $('#front-page-sellers').empty();
            $('#front-page-most-available').empty();
            $('#front-page-deals').empty();
            $('#front-page-sellerbots').empty();

            if (houseInfo.hasOwnProperty(tuj.realms[self.params.realm].house)) {
                var info = houseInfo[tuj.realms[self.params.realm].house];
                if (info.hasOwnProperty('sellers') && info.sellers.length) {
                    var d = document.getElementById('front-page-sellers');
                    var h = libtuj.ce('h3');
                    d.appendChild(h);
                    $(h).text(self.lang.topSellers);
                    for (var x = 0; x < info.sellers.length; x++) {
                        var a = libtuj.ce('a');
                        a.href = tuj.BuildHash({page: 'seller', realm: info.sellers[x].realm, id: info.sellers[x].name});
                        a.appendChild(document.createTextNode(info.sellers[x].name + (info.sellers[x].realm == self.params.realm ? '' : (' - ' + tuj.realms[info.sellers[x].realm].name))));
                        d.appendChild(a);
                        d.appendChild(libtuj.ce('br'));
                    }
                }
                if (info.hasOwnProperty('mostAvailable') && info.mostAvailable.length) {
                    var d = document.getElementById('front-page-most-available');
                    var h = libtuj.ce('h3');
                    d.appendChild(h);
                    $(h).text(self.lang.mostAvailable);
                    for (var x = 0; x < info.mostAvailable.length; x++) {
                        var a = libtuj.ce('a');
                        a.href = tuj.BuildHash({page: 'item', id: info.mostAvailable[x].id});
                        a.rel = 'item=' + info.mostAvailable[x].id + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
                        a.appendChild(document.createTextNode('[' + info.mostAvailable[x]['name_' + tuj.locale] + ']'));
                        d.appendChild(a);
                        d.appendChild(libtuj.ce('br'));
                    }
                }
                if (info.hasOwnProperty('deals') && info.deals.length) {
                    var d = document.getElementById('front-page-deals');
                    var h = libtuj.ce('h3');
                    d.appendChild(h);
                    $(h).text(self.lang.potentialDeals);
                    for (var x = 0; x < info.deals.length; x++) {
                        var a = libtuj.ce('a');
                        a.href = tuj.BuildHash({page: 'item', id: info.deals[x].id});
                        a.rel = 'item=' + info.deals[x].id + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
                        a.appendChild(document.createTextNode('[' + info.deals[x]['name_' + tuj.locale] + ']'));
                        d.appendChild(a);
                        d.appendChild(libtuj.ce('br'));
                    }
                }
                if (info.hasOwnProperty('sellerbots') && info.sellerbots.length) {
                    var d = document.getElementById('front-page-sellerbots');
                    var h = libtuj.ce('h3');
                    d.appendChild(h);
                    var a = libtuj.ce('a');
                    h.appendChild(a);
                    a.href = '/extra/multirealm.php';
                    $(a).addClass('highlight').text('Probable Bots');
                    for (var x = 0; x < info.sellerbots.length; x++) {
                        var a = libtuj.ce('a');
                        a.href = tuj.BuildHash({page: 'seller', realm: info.sellerbots[x].realm, id: info.sellerbots[x].name});
                        a.appendChild(document.createTextNode(info.sellerbots[x].name + (info.sellerbots[x].realm == self.params.realm ? '' : (' - ' + tuj.realms[info.sellerbots[x].realm].name))));
                        d.appendChild(a);
                        d.appendChild(libtuj.ce('br'));
                    }
                }
            }

            $('#front-page-banned').hide();
            if (self.banned.isbanned) {
                var banHTML = '';
                banHTML += '<b>' + libtuj.sprintf(self.lang.ipIsBanned, self.banned.ip ? '(' + self.banned.ip + ')' : '') + '</b>';

                switch (self.banned.reason) {
                    case 'cbl':
                    case 'sorbs':
                        banHTML += '<br><br>The IP address is listed on a third-party block list. We restrict IPs on that list because they include botnets and open proxies which may be used to send repeated requests to TUJ.';
                        break;
                    case 'mask':
                        banHTML += '<br><br>The IP address is on a network that has sent repeated automated queries to TUJ. Your computer may not be affected, but many others at your network/ISP are.';
                        break;
                    case 'ip':
                        banHTML += '<br><br>The IP address was the source of many repeated automated queries to TUJ.';
                        break;
                    case 'proxy':
                        banHTML += '<br><br>The Undermine Journal does not support the use of content-altering web proxies.';
                        break;
                }

                banHTML += '<br><br>' + libtuj.sprintf(self.lang.bannedContact, tuj.BuildHash({page: 'contact', id: undefined}));

                $('#front-page-banned').html(banHTML).show();
            } else if (showAPIDownAlert) {
                var alertHTML = '';

                alertHTML += '<b>Auction House data is not being updated.</b>';
                alertHTML += '<br><br>This is probably due to an issue with the Battle.net API. Such issues are out of our control.';
                alertHTML += ' You can check <span class="nowrap"><a href="https://does.theapi.work">Does the API work?</a></span> and <a href="https://www.tradeskillmaster.com/wow-api">TradeSkillMaster</a> for independent verification of the API status.';

                $('#front-page-banned').html(alertHTML).show();
            }
        }
    }

    this.SellerIsBot = function (realm, name)
    {
        var house = tuj.realms[realm].house;
        if (!houseInfo.hasOwnProperty(house)) {
            return false;
        }
        if (!houseInfo[house].hasOwnProperty('sellerbots')) {
            return false;
        }
        for (var x = 0, bot; bot = houseInfo[house].sellerbots[x]; x++) {
            if (bot.realm == realm && bot.name == name) {
                return true;
            }
        }
        return false;
    };

    this.SetTitle = function (titlePart)
    {
        var title = '';
        var titleToReturn = '';

        if (titlePart) {
            title += titlePart + ' - '
            titleToReturn += titlePart;
        }
        else {
            if (self.params.page) {
                if (self.lang.hasOwnProperty(validPages[self.params.page])) {
                    title += self.lang[validPages[self.params.page]];
                } else {
                    title += validPages[self.params.page].substr(0, 1).toUpperCase() + validPages[self.params.page].substr(1);
                }
                if (self.params.id) {
                    title += ': ' + self.params.id;
                }
                titleToReturn += title;
                title += ' - ';
            }
        }

        if (self.params.realm) {
            title += self.validRegions[self.params.region] + ' ' + self.realms[self.params.realm].name + ' - ';
        }

        document.title = title + 'The Undermine Journal';
        return titleToReturn;
    };

    this.BuildHash = function (p)
    {
        var tParams = {};
        for (var x in self.params) {
            if (self.params.hasOwnProperty(x)) {
                tParams[x] = self.params[x];
            }
            if (p.hasOwnProperty(x)) {
                tParams[x] = p[x];
            }
        }

        if (typeof tParams.page == 'string') {
            for (var x = 0; x < validPages.length; x++) {
                if (validPages[x] == tParams.page) {
                    tParams.page = x;
                }
            }

            if (typeof tParams.page == 'string') {
                tParams.page = undefined;
            }
        }

        if (!tParams.page) {
            tParams.id = undefined;
        }

        var h = '';
        if (tParams.region != undefined) {
            h += '/' + validRegions[tParams.region].toLowerCase();
            if (tParams.realm && self.allRealms[tParams.region].hasOwnProperty(tParams.realm)) {
                h += '/' + self.allRealms[tParams.region][tParams.realm].slug;
            }
        }
        if (tParams.page) {
            h += '/' + validPages[tParams.page];
        }
        if (tParams.id) {
            h += '/' + tParams.id;
        }
        if (h != '') {
            h = '#' + h.substr(1).toLowerCase();
        }

        return h;
    }

    function DrawRealms()
    {
        if (drawnRegion != self.params.region) {
            drawnRegion = self.params.region;
            $('#realm-list').remove();
        }

        var realmList = $('#realm-list')[0];
        var realmsColumn;

        if (!realmList) {
            realmList = libtuj.ce();
            realmList.id = 'realm-list';
            $('#main').prepend(realmList);

            var directions = libtuj.ce();
            directions.className = 'directions';
            realmList.appendChild(directions);
            $(directions).text(libtuj.sprintf(self.lang.chooseRealm, validRegions[drawnRegion]));

            realmsColumn = libtuj.ce();
            realmsColumn.className = 'realms-column';
            realmList.appendChild(realmsColumn);
        } else {
            realmsColumn = realmList.getElementsByClassName('realms-column')[0];
            $(realmsColumn).empty();
        }

        var a;
        var allRealms = [];

        for (var x in self.realms) {
            if (!self.realms.hasOwnProperty(x)) {
                continue;
            }

            allRealms.push(x);
        }

        allRealms.sort(function (a, b)
        {
            return self.realms[a].name.localeCompare(self.realms[b].name);
        });

        for (x = 0; x < allRealms.length; x++) {
            a = libtuj.ce('a');
            a.rel = allRealms[x];
            $(a).text(self.realms[allRealms[x]].name);

            $(realmsColumn).append(a);
        }

        Main();
    }

    var CaptchaClick = function ()
    {
        var i = $(this);
        if (i.hasClass('selected')) {
            i.removeClass('selected');
        }
        else {
            i.addClass('selected');
        }
    };

    var CaptchaSubmit = function ()
    {
        var answer = '';
        var imgs = this.parentNode.getElementsByTagName('img');
        for (var x = 0; x < imgs.length; x++) {
            if ($(imgs[x]).hasClass('selected')) {
                answer += imgs[x].id.substr(8);
            }
        }

        if (answer == '') {
            return;
        }

        $('#progress-page').show();

        $.ajax({
            data: {answer: answer},
            success: function (d)
            {
                if (d.captcha) {
                    tuj.AskCaptcha(d.captcha);
                }
                else {
                    Main();
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
            url: 'api/captcha.php'
        });
    };

    this.AskCaptcha = function (c)
    {
        var captchaPage = $('#captcha-page')[0];
        if (!captchaPage) {
            captchaPage = libtuj.ce();
            captchaPage.id = 'captcha-page';
            captchaPage.className = 'page';
            $('#main').append(captchaPage);
        }

        $('#page-title').text(self.lang.solveCaptcha);
        tuj.SetTitle(self.lang.solveCaptcha);

        $(captchaPage).empty();

        captchaPage.appendChild(document.createTextNode(libtuj.sprintf(self.lang.captchaDirections, self.lang.races[c.lookfor])));

        var d = libtuj.ce();
        d.className = 'captcha';
        captchaPage.appendChild(d);

        for (var x = 0; x < c.ids.length; x++) {
            var img = libtuj.ce('img');
            img.className = 'captcha-button';
            img.src = 'captcha/' + c.ids[x] + '.jpg';
            img.id = 'captcha-' + (x + 1);
            $(img).click(CaptchaClick);
            d.appendChild(img);
        }

        var b = libtuj.ce('br');
        b.clear = 'all';
        d.appendChild(b);

        b = libtuj.ce('input');
        b.value = self.lang.submit;
        b.type = 'button';
        $(b).click(CaptchaSubmit);
        d.appendChild(b);

        $(captchaPage).show();
    };

    function ShowRealmFrontPage()
    {
        var frontPage = $('#front-page')[0];
        $(frontPage).show();

        $('#category-sidebar a').each(function ()
        {
            if (this.rel) {
                var parts = ['category',this.rel];
                if (this.rel.indexOf('/') > 0) {
                    parts = this.rel.split('/');
                }
                this.href = tuj.BuildHash({page: parts[0], id: parts[1]});

                var m;
                if (self.lang.hasOwnProperty('category_' + this.rel)) {
                    this.innerHTML = self.lang['category_' + this.rel];
                } else if (self.lang.hasOwnProperty(this.rel)) {
                    this.innerHTML = self.lang[this.rel];
                } else if (m = this.rel.match(/^transmog\/(\w+)$/)) {
                    this.innerHTML = self.lang.transmog + ': ' + self.lang[m[1]];
                }
            }
        });
        $('#front-welcome').html(libtuj.sprintf(self.lang.welcomeTo, 'The Undermine Journal') + ' <sub>' + self.lang.yourSource + '</sub>');

        if (!jQuery.browser.mobile) {
            $('#searchbox').focus();
        }
    }

    function SetDarkTheme(dark)
    {
        var darkSheet = document.getElementById('dark-sheet');

        var duringStartup = self.colorTheme == '';

        if (!dark) {
            self.colorTheme = 'light';
            if (darkSheet) {
                darkSheet.disabled = true;
            }
        } else {
            self.colorTheme = 'dark';
            if (darkSheet) {
                darkSheet.disabled = false;
            } else {
                darkSheet = libtuj.ce('link');
                darkSheet.rel = 'stylesheet';
                darkSheet.href = tujCDNPrefix + 'css/night.css?6';
                darkSheet.id = 'dark-sheet';
                document.getElementsByTagName('head')[0].appendChild(darkSheet);
            }
        }

        if (!duringStartup) {
            libtuj.Storage.Set('colorTheme', self.colorTheme);

            Main();
        }
    }

    this.APIMaintenance = function (maintenance)
    {
        var maintenancePage = $('#maintenance-page')[0];
        if (!maintenancePage) {
            maintenancePage = libtuj.ce();
            maintenancePage.id = 'maintenance-page';
            maintenancePage.className = 'page';
            $('#main').append(maintenancePage);
        }

        $('#page-title').text(self.lang.temporarilyOffline);
        tuj.SetTitle(self.lang.maintenance);

        $(maintenancePage).empty();

        var now = Date.now()/1000;

        maintenancePage.appendChild(document.createTextNode(self.lang.maintenanceGoblins));
        if (maintenance > now) {
            maintenancePage.appendChild(libtuj.ce('p'));
            maintenancePage.appendChild(document.createTextNode(libtuj.sprintf(self.lang.expectOnline, libtuj.FormatDate(maintenance, true))));
        }
        maintenancePage.appendChild(libtuj.ce('p'));
        maintenancePage.appendChild(document.createTextNode(self.lang.pleaseComeBack));

        $(maintenancePage).show();
    };

    function Start() {
        var GenericPage = function (me, pageName) {
            this.load = function (inParams) {
                var page = $('#' + pageName + '-page');
                page.find('.lazy-image').each(function(){
                    var $t = $(this);
                    $t.removeClass('lazy-image');
                    var i = libtuj.ce('img');
                    i.src = $t.data('src');
                    i.className = this.className;
                    $t.replaceWith(i);
                });

                $('#page-title').text(me.SetTitle(me.lang[pageName]));
                page.show();
            }
        };

        var pageName, divs = document.getElementsByClassName('page-generic');
        for (var x = 0; x < divs.length; x++) {
            pageName = divs[x].id.substr(0, divs[x].id.length - 5);
            self['page_' + pageName] = new GenericPage(self, pageName);
            validPages.push(pageName);
            pagesNeedRealm.push(false);
        }

        Main();
    }

    Start();
};

/**
 * jQuery.browser.mobile (http://detectmobilebrowser.com/)
 *
 * jQuery.browser.mobile will be true if the browser is a mobile device
 *
 **/
(function(a){(jQuery.browser=jQuery.browser||{}).mobile=/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4))})(navigator.userAgent||navigator.vendor||window.opera);

var tuj;
$(document).ready(function ()
{
    tuj = new TUJ();
});
$(window).load(libtuj.Ads.onWindowLoad);

var wowhead_tooltips = { "hide": { "extra": true, "sellprice": true } };
