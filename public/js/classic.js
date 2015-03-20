var TUJClassic = {
    UpdateSidebar: function() {
        if ($('#title-date').length == 0) {
            var d = libtuj.ce();
            d.id = 'title-date';
            $('#topcorner .region-realm').after(d);
            var dt = new Date();
            var dts = dt.toLocaleString('en-US',{
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
            $(d).html(" | <b>" + dts + "</b> | ");
        }

        $('#classic-menus table a').each(function () {
            if (this.rel) {
                var parts = ['category',this.rel];
                if (this.rel.indexOf('/') > 0) {
                    parts = this.rel.split('/');
                }
                this.href = tuj.BuildHash({page: parts[0], id: parts[1]});
            }
        });

        $('#top-bar-contact')[0].href = $('#bottom-bar a.contact')[0].href;
        $('#top-bar-donate')[0].href = $('#bottom-bar a.donate')[0].href;
    },

    SetHouseInfo: function() {
        var map = {
            'sectionitems_hotitems': 'front-page-most-available',
            'sectionitems_busysellers': 'front-page-sellers',
            'sectionitems_greatdeals': 'front-page-deals'
        };

        for (var o in map) {
            var n = map[o];
            if ($('#'+n+' a').length == 0) {
                continue;
            }
            $('#'+o).empty();
            $('#'+n+' a').each(function(){
                var d = libtuj.ce();
                d.className = 'sectionheader';
                d.appendChild(this);
                document.getElementById(o).appendChild(d);
            });
        }
    },

    newSectionNode: undefined,
    newSectionTimer: undefined,
    newSection: undefined,
    prevSection: undefined,
    setSectionTimer: function (n)
    {
        if (typeof TUJClassic.newSectionTimer != 'undefined') {
            if (n == TUJClassic.newSectionNode) {
                return;
            }
            window.clearTimeout(TUJClassic.newSectionTimer);
        }
        TUJClassic.newSectionNode = n;
        TUJClassic.newSectionTimer = window.setTimeout(TUJClassic.setSection, 250);
    },
    clearSectionTimer: function ()
    {
        if (typeof TUJClassic.newSectionTimer != 'undefined') {
            window.clearTimeout(TUJClassic.newSectionTimer);
            TUJClassic.newSectionNode = undefined;
            TUJClassic.newSectionTimer = undefined;
        }
    },
    setSection: function ()
    {
        var n = TUJClassic.newSectionNode;
        if (typeof TUJClassic.newSectionTimer != 'undefined') {
            window.clearTimeout(TUJClassic.newSectionTimer);
            TUJClassic.newSectionTimer = undefined;
        }
        TUJClassic.newSectionNode = undefined;
        if (typeof TUJClassic.prevSection == 'undefined') {
            TUJClassic.prevSection = 'Items';
        }
        TUJClassic.newSection = n.innerHTML;
        if (TUJClassic.newSection == TUJClassic.prevSection) {
            return;
        }
        TUJClassic.prevSection = TUJClassic.newSection;

        /*var c = document.getElementById('sectionitemscontainer').getElementsByTagName('div');
        for (var x = 0; x < c.length; x++) {
            if (c[x].className == '') {
                c[x].style.display = 'none';
            }
        }*/
        $('#sectionitemscontainer > div').hide();
        document.getElementById('sectionitems_' + TUJClassic.newSection.replace(' ', '').toLowerCase()).style.display = 'block';

        var tds = n.parentNode.getElementsByTagName('td');
        for (x = 0; x < tds.length; x++) {
            tds[x].className = 'sectionheader' + ((tds[x].innerHTML == TUJClassic.newSection) ? ' sectionheaderselected' : '');
        }
    }
};