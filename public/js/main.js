var libtuj = {
    ce: function(tag) { if (!tag) tag = 'div'; return document.createElement(tag); },
    AddScript: function(url) {
        var s = libtuj.ce('script');
        s.type = 'text/javascript';
        s.src = url;
        document.getElementsByTagName('head')[0].appendChild(s);
    },
    FormatPrice: function(amt,justValue)
    {
        var v = (typeof amt == 'number') ? ('' + (amt/10000).toFixed(2) + 'g') : '';
        if (justValue)
            return v;

        var s = libtuj.ce('span');
        if (v)
            s.appendChild(document.createTextNode(v));
        return s;
    },
    FormatQuantity: function(amt,justValue)
    {
        var v = Number(amt).toLocaleString();
        if (justValue)
            return v;

        var s = libtuj.ce('span');
        if (v)
            s.appendChild(document.createTextNode(v));
        return s;
    },
    FormatDate: function(unix)
    {
        var s = libtuj.ce('span');
        if (unix)
        {
            var dt;
            if (typeof unix == 'string')
                dt = new Date(unix.replace(/^(\d{4}-\d\d-\d\d) (\d\d:\d\d:\d\d)$/, '$1T$2.000Z'));
            else
                dt = new Date(unix*1000);
            s.appendChild(document.createTextNode(dt.toLocaleDateString()));
        }
        return s;
    }
};

var TUJ = function()
{
    var validPages = ['','search','item','seller','category','battlepet'];
    this.validFactions = {'alliance': 1, 'horde': -1};
    this.realms = undefined;
    this.params = {
        realm: undefined,
        faction: undefined,
        page: undefined,
        id: undefined
    }
    var hash = {
        sets: 0,
        changes: 0,
        watching: false
    }
    var inMain = false;
    var self = this;

    function Main()
    {
        if (inMain)
            return;
        inMain = true;

        document.body.className = '';

        if (typeof self.realms == 'undefined')
        {
            inMain = false;
            $.ajax({
                data: {
                    region: 'US'
                },
                success: function(dta)
                {
                    self.realms = dta;
                    Main();
                },
                url: 'api/realms.php'
            });
            return;
        }

        ReadParams();

        UpdateSidebar();

        if ($('#realm-list').length == 0)
        {
            inMain = false;
            DrawRealms();
            return;
        }

        $('#main .page').hide();
        $('#realm-list').removeClass('show');
        if (!self.params.realm)
        {
            if (self.params.faction)
                ChooseFaction(self.params.faction);
            inMain = false;
            $('#faction-pick a').each(function() { this.href = self.BuildHash({realm: undefined, faction: this.rel});});
            $('#realm-list .realms-column a').each(function() { this.href = self.BuildHash({realm: this.rel}); });
            $('#realm-list').addClass('show');
            document.body.className = 'realm';
            return;
        }

        if (!self.params.page)
        {
            inMain = false;
            document.body.className = 'front';
            ShowRealmFrontPage();
            return;
        }

        document.body.className = validPages[self.params.page];

        if (typeof tuj['page_'+validPages[self.params.page]] == 'undefined')
            libtuj.AddScript('js/'+validPages[self.params.page]+'.js');
        else
            tuj['page_'+validPages[self.params.page]].load(self.params);

        inMain = false;
    }

    function ReadParams()
    {
        if (!hash.watching)
            hash.watching = $(window).on('hashchange', ReadParams);

        if (hash.sets > hash.changes)
        {
            hash.changes++;
            return false;
        }

        if (hash.sets != hash.changes)
            return false;

        var p = {
            realm: undefined,
            faction: undefined,
            page: undefined,
            id: undefined
        }

        var h = location.hash.toLowerCase();
        if (h.charAt(0) == '#')
            h = h.substr(1);
        h = h.split('/');

        var y;

        nextParam:
        for (var x = 0; x < h.length; x++)
        {
            if (!p.page)
                for (y = 0; y < validPages.length; y++)
                    if (h[x] == validPages[y])
                    {
                        p.page = y;
                        continue nextParam;
                    }
            if (!p.faction)
                for (y in self.validFactions)
                    if (self.validFactions.hasOwnProperty(y) && h[x] == y)
                    {
                        p.faction = y;
                        continue nextParam;
                    }
            if (!p.realm)
                for (y in self.realms)
                    if (self.realms.hasOwnProperty(y) && h[x] == self.realms[y].slug)
                    {
                        p.realm = y;
                        continue nextParam;
                    }
            p.id = h[x];
        }

        if (!self.SetParams(p))
            Main();
    }

    this.SetParams = function(p)
    {
        if (p)
            for (var x in p)
                if (p.hasOwnProperty(x) && self.params.hasOwnProperty(x))
                    self.params[x] = p[x];

        var h = self.BuildHash(self.params);

        if (h != location.hash)
        {
            hash.sets++;
            location.hash = h;
            Main();
            return true;
        }

        return false;
    }

    function UpdateSidebar()
    {
        var factionLink = $('#topcorner a.faction')[0];
        factionLink.className = 'faction '+(self.params.faction ? self.params.faction : 'none');
        var otherFaction = self.params.faction ? (self.params.faction == 'alliance' ? 'horde' : 'alliance') : undefined;
        factionLink.href = self.BuildHash({faction: otherFaction});

        var realmLink = $('#topcorner a.realm');
        realmLink[0].href = self.BuildHash({realm: undefined});
        realmLink.text(self.params.realm ? self.realms[self.params.realm].name : '');

        $('#title a')[0].href = self.BuildHash({page: undefined});
        $('#page-title').empty();

        if ($('#topcorner form').length == 0)
        {
            var form = libtuj.ce('form');
            var i = libtuj.ce('input');
            i.name = 'search';
            i.type = 'text';
            i.placeholder = 'Search';

            $(form).on('submit', function() {
                location.href = self.BuildHash({page: 'search', id: this.search.value.replace('/','')});
                return false;
            }).append(i);

            $('#topcorner .realm-faction').after(form);
        }
    }

    this.BuildHash = function(p)
    {
        var tParams = {};
        for (var x in self.params)
        {
            if (self.params.hasOwnProperty(x))
                tParams[x] = self.params[x];
            if (p.hasOwnProperty(x))
                tParams[x] = p[x];
        }

        if (typeof tParams.page == 'string')
        {
            for (var x = 0; x < validPages.length; x++)
                if (validPages[x] == tParams.page)
                    tParams.page = x;

            if (typeof tParams.page == 'string')
                tParams.page = undefined;
        }

        if (!tParams.page)
            tParams.id = undefined;
        if (!tParams.faction)
            tParams.realm = undefined;

        var h = '';
        if (tParams.realm)
            h += '/' + self.realms[tParams.realm].slug;
        if (tParams.faction)
            h += '/' + tParams.faction;
        if (tParams.page)
            h += '/' + validPages[tParams.page];
        if (tParams.id)
            h += '/' + tParams.id;
        if (h != '')
            h = '#' + h.substr(1).toLowerCase();

        return h;
    }

    function DrawRealms()
    {
        var addResize = false;
        var realmList = $('#realm-list')[0];

        if (!realmList)
        {
            realmList = libtuj.ce();
            realmList.id = 'realm-list';
            $('#main').prepend(realmList);

            var factionPick = libtuj.ce();
            factionPick.id = 'faction-pick';
            realmList.appendChild(factionPick);

            var directions = libtuj.ce();
            directions.className = 'directions';
            factionPick.appendChild(directions);
            $(directions).text('Choose your faction, then realm.');

            var factionAlliance = libtuj.ce('a');
            factionAlliance.rel = 'alliance';
            $(factionAlliance).addClass('alliance').text('Alliance');
            factionPick.appendChild(factionAlliance);
            var factionHorde = libtuj.ce('a');
            $(factionHorde).addClass('horde').text('Horde');
            factionHorde.rel = 'horde';
            factionPick.appendChild(factionHorde);

            addResize = true;
        }

        $(realmList).addClass('width-test');
        var maxWidth = realmList.clientWidth;
        var oldColCount = realmList.getElementsByClassName('realms-column').length;

        var cols = [];
        var colWidth = 0;
        if (oldColCount == 0)
        {
            cols.push(libtuj.ce());
            cols[0].className = 'realms-column';
            $(realmList).append(cols[0]);

            colWidth = cols[0].offsetWidth;
        }
        else
        {
            colWidth = realmList.getElementsByClassName('realms-column')[0].offsetWidth;
        }
        $(realmList).removeClass('width-test');

        var numCols = Math.floor(maxWidth / colWidth);
        if (numCols == 0)
            numCols = 1;

        if (numCols == oldColCount)
            return;

        if (oldColCount > 0)
            $(realmList).children('.realms-column').remove();

        for (var x = cols.length; x < numCols; x++)
        {
            cols[x] = libtuj.ce();
            cols[x].className = 'realms-column';
            $(realmList).append(cols[x]);
        }

        var cnt = 0;

        for (var x in self.realms)
        {
            if (!self.realms.hasOwnProperty(x))
                continue;

            cnt++;
        }

        var a;
        var c = 0;
        for (var x in self.realms)
        {
            if (!self.realms.hasOwnProperty(x))
                continue;

            a = libtuj.ce('a');
            a.rel = x;
            $(a).text(self.realms[x].name);

            $(cols[Math.min(cols.length-1, Math.floor(c++ / cnt * numCols))]).append(a);
        }

        if (addResize)
            optimizedResize.add(DrawRealms);

        Main();
    }

    function ChooseFaction(dta)
    {
        var toRemove = '';
        var toAdd = (dta.hasOwnProperty('data') ? dta.data.addClass : dta);

        for (var f in self.validFactions)
            if (self.validFactions.hasOwnProperty(f) && toAdd.indexOf(f) < 0)
                toRemove += (toRemove == '' ? '' : ' ') + f;
        $('#realm-list').addClass(toAdd).removeClass(toRemove);
        self.SetParams({faction: toAdd});
        Main();
    }

    function ShowRealmFrontPage()
    {
        var frontPage = $('#front-page')[0];
        $(frontPage).show();
    }
    Main();
};

var tuj;
$(document).ready(function() {
    tuj = new TUJ();
});

var optimizedResize = (function() {

    var callbacks = [],
        running = false;

    // fired on resize event
    function resize() {

        if (!running) {
            running = true;

            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(runCallbacks);
            } else {
                setTimeout(runCallbacks, 66);
            }
        }

    }

    // run the actual callbacks
    function runCallbacks() {

        for (var x = 0; x < callbacks.length; x++)
            callbacks[x]();

        running = false;
    }

    // adds callback to loop
    function addCallback(callback) {

        if (callback) {
            callbacks.push(callback);
        }

    }

    return {
        add: function(callback) {
            if (callbacks.length == 0)
                window.addEventListener('resize', resize);
            addCallback(callback);
        }
    }
}());

var wowhead_tooltips = { "hide": { "extra": true, "sellprice": true } };