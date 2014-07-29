var libtuj = {
    ce: function(tag) { if (!tag) tag = 'div'; return document.createElement(tag); },
};

var TUJ = function()
{
    var realms;

    function Main()
    {
        $.ajax({
            data: {
                region: 'US'
            },
            success: ReadRealms,
            url: 'api/realms.php'
        });
    }

    function ReadRealms(dta)
    {
        realms = dta;

        var realmList = libtuj.ce();
        realmList.className = 'realm-list';
        $('#main').append(realmList);

        DrawRealms();
        optimizedResize.add(DrawRealms);
    }

    function DrawRealms()
    {
        realmList = $('#main div.realm-list')[0];

        var maxWidth = realmList.offsetWidth;
        var oldColCount = realmList.childNodes.length;

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
            colWidth = realmList.childNodes[0].offsetWidth;
        }

        var numCols = Math.floor(maxWidth / colWidth);
        if (numCols == 0)
            numCols = 1;

        if (numCols == oldColCount)
            return;

        if (oldColCount > 0)
            $(realmList).empty();

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
            $(a).text(realms[x].name);

            $(cols[Math.min(cols.length-1, Math.floor(c++ / cnt * numCols))]).append(a);
        }
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

        callbacks.forEach(function(callback) {
            callback();
        });

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
