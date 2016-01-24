var TUJ_Transmog = function ()
{
    var params;
    var lastResults = [];
    var resultFunctions = {};
    var self = this;
    var typeNames = [];
    var lastTabs = {};

    var validPages;

    this.load = function (inParams)
    {
        validPages = {
            'cloth': tuj.lang.clothArmor,
            'leather': tuj.lang.leatherArmor,
            'mail': tuj.lang.mailArmor,
            'plate': tuj.lang.plateArmor,
            'main': tuj.lang.mainWeapons,
            'off': tuj.lang.offWeapons,
        };

        params = {};
        for (var p in inParams) {
            if (inParams.hasOwnProperty(p)) {
                params[p] = inParams[p];
            }
        }

        var qs = {
            house: tuj.realms[params.realm].house,
            id: params.id
        };
        var hash = JSON.stringify(qs);

        for (var x = 0; x < lastResults.length; x++) {
            if (lastResults[x].hash == hash) {
                TransmogResult(false, lastResults[x].data, hash);
                return;
            }
        }

        var transmogPage = $('#transmog-page')[0];
        if (!transmogPage) {
            transmogPage = libtuj.ce();
            transmogPage.id = 'transmog-page';
            transmogPage.className = 'page';
            $('#main').append(transmogPage);
        }

        if ((!params.id) || (!validPages.hasOwnProperty(params.id))) {
            tuj.SetParams({id: 'main'}, true);
            return;
        }

        $('#progress-page').show();

        $.ajax({
            data: qs,
            success: function (d)
            {
                if (d.captcha) {
                    tuj.AskCaptcha(d.captcha);
                }
                else {
                    TransmogResult(hash, d, hash);
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
            url: 'api/transmog.php'
        });
    }

    function TransmogResult(hash, dta, tabKey)
    {
        if (hash) {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10) {
                lastResults.shift();
            }
        }

        var transmogPage = $('#transmog-page');
        transmogPage.empty();
        transmogPage.show();

        $('#page-title').empty().append(document.createTextNode(tuj.lang.transmog + ': ' + validPages[params.id]));
        tuj.SetTitle(tuj.lang.transmog + ': ' + validPages[params.id]);

        //transmogPage.append(libtuj.Ads.Add('8323200718'));

        typeNames = [];
        var tn;
        for (var k in dta) {
            if (dta.hasOwnProperty(k)) {
                tn = k;
                if (tuj.lang.itemTypes.hasOwnProperty(k)) {
                    tn = tuj.lang.itemTypes[k];
                } else if (tuj.lang.itemSubClasses.hasOwnProperty(k)) {
                    tn = tuj.lang.itemSubClasses[k];
                }
                typeNames.push({'id': k, 'nm': tn});
            }
        }
        typeNames.sort(function(a,b) { return a.nm.localeCompare(b.nm); });

        if (typeNames.length > 1) {
            d = libtuj.ce();
            d.className = 'transmog-slots';
            transmogPage.append(d);

            for (var x = 0; x < typeNames.length; x++) {
                a = libtuj.ce('a');
                d.appendChild(a);
                a.id = 'transmog-slot-choice-' + x;
                $(a).click(self.showType.bind(self, x, dta, tabKey));
                a.appendChild(document.createTextNode(typeNames[x].nm));
                if (x == 0) {
                    a.className = 'selected';
                }
            }
        }

        d = libtuj.ce();
        d.id = 'transmog-results';
        transmogPage.append(d);

        if (!lastTabs.hasOwnProperty(tabKey)) {
            lastTabs[tabKey] = 0;
        }
        self.showType(lastTabs[tabKey], dta, tabKey);

        var s = libtuj.ce();
        s.style.textAlign = 'center';
        var a = libtuj.ce('a');
        a.href = 'http://' + tuj.lang.wowheadDomain + '.wowhead.com/';
        $(a).text('Wowhead');
        $(s).append(libtuj.sprintf(tuj.lang.imagesBy, a.outerHTML));
        transmogPage.append(s);

        //libtuj.Ads.Show();
    }

    this.showType = function(idx, dta, tabKey) {
        $('.transmog-slots a').removeClass('selected');
        $('#transmog-slot-choice-'+idx).addClass('selected');

        $('#transmog-results').empty();

        lastTabs[tabKey] = idx;

        var items = dta[typeNames[idx].id];
        items.sort(self.itemSort);

        for (var y = 0; y < items.length; y++) {
            var box = libtuj.ce();
            box.className = 'transmog-box';
            d.appendChild(box);

            var img = libtuj.ce('a');
            img.className = 'transmog-img';
            box.appendChild(img);
            img.href = tuj.BuildHash({page: 'item', id: items[y].id});
            img.style.backgroundImage = 'url(' + tujCDNPrefix + 'models/' + items[y].display + '.png)';

            var prc = libtuj.ce('a');
            box.appendChild(prc);
            prc.href = img.href;
            prc.rel = 'item=' + items[y].id + (tuj.locale != 'enus' ? '&locale=' + tuj.locale : '');
            prc.appendChild(libtuj.FormatPrice(items[y].buy));
        }
    }

    this.itemSort = function(a,b) {
        return (a.buy - b.buy) || (a.id - b.id);
    }

    this.load(tuj.params);
}

tuj.page_transmog = new TUJ_Transmog();
