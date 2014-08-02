var libtuj = {
    ce: function(tag) { if (!tag) tag = 'div'; return document.createElement(tag); },
};

var TUJ = function()
{
    var realms;
    var realm;
    var faction = 1;

    function Main()
    {
        if (typeof realms == 'undefined')
        {
            $.ajax({
                data: {
                    region: 'US'
                },
                success: function(dta)
                {
                    realms = dta;
                    Main();
                },
                url: 'api/realms.php'
            });
            return;
        }

        if ($('#realm-list').length == 0)
        {
            DrawRealms();
            return;
        }

        if (typeof realm == 'undefined')
        {
            $('#realm-list').addClass('show');
            return;
        }
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
            $(factionAlliance).addClass('alliance').text('Alliance').click({addClass: 'alliance', removeClass: 'horde'}, ChooseFaction);
            factionPick.appendChild(factionAlliance);
            var factionHorde = libtuj.ce('a');
            $(factionHorde).addClass('horde').text('Horde').click({addClass: 'horde', removeClass: 'alliance'}, ChooseFaction);
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

        for (var x in realms)
        {
            if (!realms.hasOwnProperty(x))
                continue;

            cnt++;
        }

        var a;
        var c = 0;
        for (var x in realms)
        {
            if (!realms.hasOwnProperty(x))
                continue;

            a = libtuj.ce('a');
            $(a).text(realms[x].name).click({id: x}, ChooseRealm);

            $(cols[Math.min(cols.length-1, Math.floor(c++ / cnt * numCols))]).append(a);
        }

        if (addResize)
            optimizedResize.add(DrawRealms);

        Main();
    }

    function ChooseFaction(dta)
    {
        $('#realm-list').addClass(dta.data.addClass).removeClass(dta.data.removeClass);
        faction = (dta.data.addClass == 'horde' ? -1 : 1);
    }

    function ChooseRealm(dta)
    {
        realm = realms[dta.data.id];
        console.log(realm, faction);
        $('#realm-list').removeClass('show');

        var realmHeader = $('#realm-header')[0];
        if (!realmHeader)
        {
            realmHeader = libtuj.ce();
            realmHeader.id = 'realm-header';
            $('#realm-list').after(realmHeader);

        }

        Main();
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
