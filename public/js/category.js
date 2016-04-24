var TUJ_Category = function ()
{
    var params;
    var lastResults = [];
    var resultFunctions = {};

    this.load = function (inParams)
    {
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
                CategoryResult(false, lastResults[x].data);
                return;
            }
        }

        var categoryPage = $('#category-page')[0];
        if (!categoryPage) {
            categoryPage = libtuj.ce();
            categoryPage.id = 'category-page';
            categoryPage.className = 'page';
            $('#main').append(categoryPage);
        }

        if (!params.id) {
            tuj.SetParams({id: 'deals'}, true);
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
                    CategoryResult(hash, d);
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
            url: 'api/category.php'
        });
    }

    function MakeNotificationsSection(house)
    {
        var t = libtuj.ce('table');
        t.className = 'category';

        var tr = libtuj.ce('tr');
        t.appendChild(tr);
        var td = libtuj.ce('th');
        tr.appendChild(td);
        td.className = 'title';
        $(td).text(tuj.lang.marketNotifications);

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        td = libtuj.ce('td');
        tr.appendChild(td);
        var d = libtuj.ce();
        d.style.textAlign = 'center';
        td.appendChild(d);

        if (tuj.LoggedInUserName()) {
            t.className += ' logged-in-only';

            d.style.display = 'none';
            d.appendChild(document.createTextNode(tuj.lang.marketNotificationsDesc));
            var cht = libtuj.ce();
            cht.className = 'notifications-insert';
            cht.style.textAlign = 'left';
            d.appendChild(cht);
            GetRareNotificationsList(house, d);
        } else {
            t.className += ' logged-out-only';

            var a = libtuj.ce('a');
            a.href = tuj.BuildHash({'page': 'subscription', 'id': undefined});
            a.className = 'highlight';
            a.appendChild(document.createTextNode(tuj.lang.logInToFreeSub));
            d.appendChild(a);
        }

        return t;
    }

    function RareNotificationsAdd(house, mainDiv, qualityBox, classBox, minLevelBox, maxLevelBox, craftedCheck, vendorCheck, daysBox)
    {
        var self = this;
        var quality = qualityBox.options[qualityBox.selectedIndex].value;
        var itemClass = classBox.options[classBox.selectedIndex].value;

        var minLevel = parseInt(minLevelBox.value,10);
        if (isNaN(minLevel)) {
            minLevel = '';
        }
        minLevelBox.value = minLevel;

        var maxLevel = parseInt(maxLevelBox.value,10);
        if (isNaN(maxLevel)) {
            maxLevel = '';
        }
        maxLevelBox.value = maxLevel;

        var crafted = craftedCheck.checked ? 1 : 0;
        var vendor = vendorCheck.checked ? 1 : 0;

        var days = parseInt(daysBox.value,10);
        if (isNaN(days)) {
            days = '14';
        }
        daysBox.value = days;

        tuj.SendCSRFProtectedRequest({
            data: {
                'setrare': house,
                'quality': quality,
                'itemclass': itemClass,
                'minlevel': minLevel,
                'maxlevel': maxLevel,
                'crafted': crafted,
                'vendor': vendor,
                'days': days,
            },
            success: RareNotificationsList.bind(self, house, mainDiv),
        });
    }

    function RareNotificationsDel(house, mainDiv, id)
    {
        var self = this;
        tuj.SendCSRFProtectedRequest({
            data: {'deleterare': id, 'house': house},
            success: RareNotificationsList.bind(self, house, mainDiv),
        });
    }

    function GetRareNotificationsList(house, mainDiv)
    {
        var self = this;
        tuj.SendCSRFProtectedRequest({
            data: {'getrare': house},
            success: RareNotificationsList.bind(self, house, mainDiv),
        });
    }

    function RareNotificationsList(house, mainDiv, dta)
    {
        var dest = $(mainDiv).find('.notifications-insert');
        dest.empty();
        dest = dest[0];

        var ids = [];
        for (var k in dta.watches) {
            if (dta.watches.hasOwnProperty(k)) {
                ids.push(k);
            }
        }
        if (ids.length) {
            // show current notifications
            ids.sort(function(ax,bx){
                var a = dta.watches[ax];
                var b = dta.watches[bx];
                return tujConstants.itemClassOrder[a.itemclass] - tujConstants.itemClassOrder[b.itemclass] ||
                    a.minquality - b.minquality ||
                    a.days - b.days;
            });

            var ul = libtuj.ce('ul');
            dest.appendChild(ul);

            for (var kx = 0, k; k = ids[kx]; kx++) {
                var li = libtuj.ce('li');
                ul.appendChild(li);

                var n = dta.watches[k];

                var btn = libtuj.ce('input');
                btn.type = 'button';
                btn.value = tuj.lang.delete;
                $(btn).on('click', RareNotificationsDel.bind(btn, house, mainDiv, n.seq));
                li.appendChild(btn);

                li.appendChild(document.createTextNode(tuj.lang.itemClasses[n.itemclass]));
                if (n.minquality) {
                    li.appendChild(document.createTextNode(', ' + tuj.lang.qualities[n.minquality] + '+'));
                }
                if (n.minlevel) {
                    li.appendChild(document.createTextNode(', ' + tuj.lang.level + ' >= ' + n.minlevel))
                }
                if (n.maxlevel) {
                    li.appendChild(document.createTextNode(', ' + tuj.lang.level + ' <= ' + n.maxlevel))
                }
                if (n.includecrafted) {
                    li.appendChild(document.createTextNode(', ' + tuj.lang.includingCrafted));
                }
                if (n.includevendor) {
                    li.appendChild(document.createTextNode(', ' + tuj.lang.includingVendor));
                }
                li.appendChild(document.createTextNode(' ' + tuj.lang.notSeenForXDays + ' ' + n.days + ' ' + tuj.lang.timeDays));
            }
        }

        if (ids.length >= dta.maximum) {
            $(mainDiv).show();
            return;
        }

        // add new notifications
        var newNotif = libtuj.ce('div');
        newNotif.className = 'notifications-add';
        dest.appendChild(newNotif);

        newNotif.appendChild(document.createTextNode(tuj.lang.tellMeAboutNewAuctions));

        var t = libtuj.ce('table');
        newNotif.appendChild(t);

        var tr = libtuj.ce('tr');
        t.appendChild(tr);
        var td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(tuj.lang.quality + ': '));
        td = libtuj.ce('td');
        tr.appendChild(td);
        var qualityBox = libtuj.ce('select');
        for (var x = 0; x <= 5; x++) {
            var opt = libtuj.ce('option');
            opt.value = x;
            opt.label = tuj.lang.qualities[x] + ' ' + tuj.lang.orBetter;
            opt.appendChild(document.createTextNode(tuj.lang.qualities[x] + ' ' + tuj.lang.orBetter));
            qualityBox.appendChild(opt);
        }
        td.appendChild(qualityBox);

        var tr = libtuj.ce('tr');
        t.appendChild(tr);
        var td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(tuj.lang['class'] + ': '));
        td = libtuj.ce('td');
        tr.appendChild(td);
        var classBox = libtuj.ce('select');
        td.appendChild(classBox);
        var watchClasses = [2, 4, 9, 7, 0, 5, 3, 16, 1, 17, 12, 13]; // see tujConstants.itemClassOrder
        for (var x = 0; x < watchClasses.length; x++) {
            if (!tuj.lang.itemClasses.hasOwnProperty(watchClasses[x])) {
                continue;
            }
            var opt = libtuj.ce('option');
            opt.value = watchClasses[x];
            opt.label = tuj.lang.itemClasses[watchClasses[x]];
            opt.appendChild(document.createTextNode(tuj.lang.itemClasses[watchClasses[x]]));
            classBox.appendChild(opt);
        }

        var tr = libtuj.ce('tr');
        t.appendChild(tr);
        var td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(tuj.lang.minimumLevel + ': '));
        td = libtuj.ce('td');
        tr.appendChild(td);
        var minLevelBox = libtuj.ce('input');
        minLevelBox.className = 'input-quantity';
        minLevelBox.type = 'number';
        minLevelBox.min = 0;
        minLevelBox.max = 999;
        minLevelBox.size = 4;
        minLevelBox.maxLength = 3;
        minLevelBox.autocomplete = 'off';
        td.appendChild(minLevelBox);

        var tr = libtuj.ce('tr');
        t.appendChild(tr);
        var td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(tuj.lang.maximumLevel + ': '));
        td = libtuj.ce('td');
        tr.appendChild(td);
        var maxLevelBox = libtuj.ce('input');
        maxLevelBox.className = 'input-quantity';
        maxLevelBox.type = 'number';
        maxLevelBox.min = 0;
        maxLevelBox.max = 999;
        maxLevelBox.size = 4;
        maxLevelBox.maxLength = 3;
        maxLevelBox.autocomplete = 'off';
        td.appendChild(maxLevelBox);

        var tr = libtuj.ce('tr');
        t.appendChild(tr);
        var td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(tuj.lang.includingCrafted + ': '));
        td = libtuj.ce('td');
        tr.appendChild(td);
        var craftedCheck = libtuj.ce('input');
        craftedCheck.type = 'checkbox';
        craftedCheck.value = '1';
        td.appendChild(craftedCheck);

        var tr = libtuj.ce('tr');
        t.appendChild(tr);
        var td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(tuj.lang.includingVendor + ': '));
        td = libtuj.ce('td');
        tr.appendChild(td);
        var vendorCheck = libtuj.ce('input');
        vendorCheck.type = 'checkbox';
        vendorCheck.value = '1';
        td.appendChild(vendorCheck);

        var tr = libtuj.ce('tr');
        t.appendChild(tr);
        var td = libtuj.ce('td');
        tr.appendChild(td);
        td.appendChild(document.createTextNode(tuj.lang.notSeenForXDays + ': '));
        td = libtuj.ce('td');
        tr.appendChild(td);
        var daysBox = libtuj.ce('input');
        daysBox.className = 'input-quantity';
        daysBox.type = 'number';
        daysBox.min = 0;
        daysBox.max = 730;
        daysBox.size = 4;
        daysBox.maxLength = 3;
        daysBox.autocomplete = 'off';
        daysBox.value = '14';
        td.appendChild(daysBox)
        td.appendChild(document.createTextNode(' ' + tuj.lang.timeDays));

        var btn = libtuj.ce('input');
        btn.type = 'button';
        btn.value = tuj.lang.add;
        $(btn).on('click', RareNotificationsAdd.bind(btn, house, mainDiv, qualityBox, classBox, minLevelBox, maxLevelBox, craftedCheck, vendorCheck, daysBox));
        td.appendChild(btn);

        $(mainDiv).show();
    }

    function CategoryResult(hash, dta)
    {
        if (hash) {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10) {
                lastResults.shift();
            }
        }

        var categoryPage = $('#category-page');
        categoryPage.empty();
        categoryPage.show();

        if (!dta.hasOwnProperty('name')) {
            $('#page-title').empty().append(document.createTextNode(tuj.lang.category + ': ' + params.id));
            tuj.SetTitle(tuj.lang.category + ': ' + params.id);

            var h2 = libtuj.ce('h2');
            categoryPage.append(h2);
            h2.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.notFound, tuj.lang.category + ' ' + params.id)));

            return;
        }

        var titleName = dta.name;
        if (tuj.lang.hasOwnProperty(titleName)) {
            titleName = tuj.lang[titleName];
        }

        $('#page-title').empty().append(document.createTextNode(tuj.lang.category + ': ' + titleName));
        tuj.SetTitle(tuj.lang.category + ': ' + titleName);

        if (dta.hasOwnProperty('results')) {
            categoryPage.append(libtuj.Ads.Add('8323200718'));

            var f, resultCount = 0;
            for (var x = 0; f = dta.results[x]; x++) {
                if (resultFunctions.hasOwnProperty(f.name)) {
                    d = libtuj.ce();
                    d.className = 'category-' + f.name.toLowerCase();
                    categoryPage.append(d);
                    if (resultFunctions[f.name](f.data, d) && (++resultCount == 5)) {
                        categoryPage.append(libtuj.Ads.Add('2276667118'));
                    }
                }
            }
        }

        if (dta.name == 'unusualItems') {
            categoryPage.append(MakeNotificationsSection(tuj.realms[params.realm].house));
        }

        libtuj.Ads.Show();
    }

    resultFunctions.ItemList = function (data, dest)
    {
        var item, x, t, td, th, tr, a;

        if (!data.items.length) {
            return false;
        }

        if (!data.hiddenCols) {
            data.hiddenCols = {};
        }

        if (!data.visibleCols) {
            data.visibleCols = {};
        }

        if (!data['sort']) {
            data['sort'] = '';
        }

        var titleColSpan = 4;
        var titleTd;

        t = libtuj.ce('table');
        t.className = 'category category-items';
        dest.appendChild(t);

        if (data.hasOwnProperty('name')) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('th');
            td.className = 'title';
            tr.appendChild(td);
            titleTd = td;

            var sluggedName = data.name.toLowerCase().replace(/[^ a-z0-9]/, '');
            for (x = 0; x < sluggedName.length; x++) {
                if (sluggedName.substr(x, 1) == ' ') {
                    sluggedName = sluggedName.substr(0, x) + sluggedName.substr(x+1, 1).toUpperCase() + sluggedName.substr(x+2);
                }
            }

            $(td).text(tuj.lang.hasOwnProperty(sluggedName) ? tuj.lang[sluggedName] : data.name);
        }

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        td = libtuj.ce('th');
        td.className = 'name';
        tr.appendChild(td);
        td.colSpan = 2;
        $(td).text(tuj.lang.name);

        td = libtuj.ce('th');
        td.className = 'quantity';
        tr.appendChild(td);
        $(td).text(tuj.lang.availableAbbrev);

        if (data.visibleCols.bid) {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text(tuj.lang.bid);
            titleColSpan++;
        }

        if (!data.hiddenCols.price) {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text(tuj.lang.currentPriceAbbrev);
            titleColSpan++;
        }

        if (!data.hiddenCols.avgprice) {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text(tuj.lang.mean)
            titleColSpan++;
        }

        if (data.visibleCols.globalmedian) {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text(tuj.lang.globalMedian);
            titleColSpan++;
        }

        if (!data.hiddenCols.lastseen) {
            td = libtuj.ce('th');
            td.className = 'date';
            tr.appendChild(td);
            $(td).text(tuj.lang.lastSeen);
            titleColSpan++;
        }

        if (data.visibleCols.posted) {
            td = libtuj.ce('th');
            td.className = 'date';
            tr.appendChild(td);
            $(td).text(tuj.lang.age);
            titleColSpan++;
        }

        titleTd.colSpan = titleColSpan;

        switch (data['sort']) {
            case 'none':
                break;

            case 'lowbids' :
                data.items.sort(function (a, b)
                {
                    return ((a.bid / libtuj.Least([a.globalmedian, a.avgprice])) - (b.bid / libtuj.Least([
                        b.globalmedian, b.avgprice
                    ]))) ||
                        (a.bid - b.bid) ||
                        a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]);
                });
                break;

            case 'globalmedian diff':
                data.items.sort(function (a, b)
                {
                    return ((b.globalmedian - b.price) - (a.globalmedian - a.price)) ||
                        ((a.price ? 0 : 1) - (b.price ? 0 : 1)) ||
                        (a.price - b.price) ||
                        a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]);
                });
                break;

            case 'lowprice':
                data.items.sort(function (a, b)
                {
                    return ((a.price ? 0 : 1) - (b.price ? 0 : 1)) ||
                        (a.price - b.price) ||
                        a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]);
                });
                break;

            default:
                data.items.sort(function (a, b)
                {
                    return ((a.price ? 0 : 1) - (b.price ? 0 : 1)) ||
                        (b.price - a.price) ||
                        a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]);
                });
        }

        for (x = 0; item = data.items[x]; x++) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('td');
            td.className = 'icon';
            tr.appendChild(td);
            i = libtuj.ce('img');
            td.appendChild(i);
            i.className = 'icon';
            i.src = libtuj.IconURL(item.icon, 'medium');

            td = libtuj.ce('td');
            td.className = 'name';
            tr.appendChild(td);
            a = libtuj.ce('a');
            td.appendChild(a);
            a.rel = 'item=' + item.id + (item.bonusurl ? '&bonus=' + item.bonusurl : (item.basebonus ? '&bonus=' + item.basebonus : '')) + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
            a.href = tuj.BuildHash({page: 'item', id: item.id + (item.bonusurl ? ('.'+item.bonusurl).replace(':','.') : '')});
            $(a).text('[' + item['name_' + tuj.locale] + (item['bonusname_' + tuj.locale] ? ' ' + item['bonusname_' + tuj.locale].substr(0, item['bonusname_' + tuj.locale].indexOf('|') >= 0 ? item['bonusname_' + tuj.locale].indexOf('|') : item['bonusname_' + tuj.locale].length) : '') + ']' + (item['bonustag_' + tuj.locale] ? ' ' : ''));
            if (item['bonustag_' + tuj.locale]) {
                var tagspan = libtuj.ce('span');
                tagspan.className = 'nowrap';
                $(tagspan).text(item['bonustag_' + tuj.locale]);
                a.appendChild(tagspan);
            }

            td = libtuj.ce('td');
            td.className = 'quantity';
            tr.appendChild(td);
            td.appendChild(libtuj.FormatQuantity(item.quantity));

            if (data.visibleCols.bid) {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(item.bid));
            }

            if (!data.hiddenCols.price) {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(item.price));
            }

            if (!data.hiddenCols.avgprice) {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(item.avgprice));
            }

            if (data.visibleCols.globalmedian) {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(item.globalmedian));
            }

            if (!data.hiddenCols.lastseen) {
                td = libtuj.ce('td');
                td.className = 'date';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatDate(item.lastseen));
            }

            if (data.visibleCols.posted) {
                td = libtuj.ce('td');
                td.className = 'date';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatDate(item.posted, false, 'hour', true));
            }
        }

        return true;
    }

    function ShowBreedRows(species, tds)
    {
        var rows = $('.category-battlepets .breed.species' + species);
        rows.show();
        for (var x = 0; x < tds.length; x++) {
            tds[x].rowSpan = (rows.length + 1);
        }
        this.style.cursor = 'default';
    }

    resultFunctions.BattlePetList = function (data, dest)
    {
        var t, td, tr, firstBreed, breed, species, petType, allSpecies, o, x, b, i, a, loc;

        var dateRegEx = /^(\d{4}-\d\d-\d\d) (\d\d:\d\d:\d\d)$/;
        var dateRegExFmt = '$1T$2.000Z';

        var speciesSort = function (a, b) {
            return (b.price - a.price) ||
                a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]);
        };

        var breedSort = function (a, b) {
            var ab = allSpecies[x].breeds[a];
            var bb = allSpecies[x].breeds[b];
            return ((bb.quantity > 0 ? 1 : 0) - (ab.quantity > 0 ? 1 : 0)) ||
                (bb.price - ab.price) ||
                tuj.lang.breedsLookup[a].localeCompare(tuj.lang.breedsLookup[b]);
        };

        var d = libtuj.ce('div');
        dest.appendChild(d);
        d.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.expandBreeds, tuj.lang.all)));
        d.appendChild(libtuj.ce('br'));
        d.style.marginBottom = '2em';
        d.style.textAlign = 'center';

        for (petType in tuj.lang.petTypes) {
            if (!tuj.lang.petTypes.hasOwnProperty(petType)) {
                continue;
            }

            if (!data.hasOwnProperty(petType)) {
                continue;
            }

            t = libtuj.ce('table');
            dest.appendChild(t);
            t.className = 'category category-battlepets';

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.colSpan = 12;
            $(td).text(tuj.lang.petTypes[petType]);

            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('th');
            tr.appendChild(td);
            td.colSpan = 2;
            td.className = 'name';
            $(td).text(tuj.lang.name);

            td = libtuj.ce('th');
            tr.appendChild(td);
            td.className = 'breeds';
            $(td).text(tuj.lang.breeds);

            td = libtuj.ce('th');
            tr.appendChild(td);
            td.className = 'quantity';
            $(td).text(tuj.lang.availableAbbrev);

            td = libtuj.ce('th');
            tr.appendChild(td);
            td.className = 'price';
            $(td).text(tuj.lang.currentPriceAbbrev);

            td = libtuj.ce('th');
            tr.appendChild(td);
            td.className = 'price';
            $(td).text(tuj.lang.mean);

            td = libtuj.ce('th');
            tr.appendChild(td);
            td.className = 'price';
            $(td).text(tuj.lang.regional);

            td = libtuj.ce('th');
            tr.appendChild(td);
            td.className = 'date';
            $(td).text(tuj.lang.lastSeen);

            allSpecies = [];

            for (species in data[petType]) {
                if (!data[petType].hasOwnProperty(species)) {
                    continue;
                }

                o = {id: species, quantity: 0, breedCount: 0, breeds: {}};

                firstBreed = false;
                for (breed in data[petType][species]) {
                    if (!data[petType][species].hasOwnProperty(breed)) {
                        continue;
                    }
                    b = data[petType][species][breed];

                    if (!firstBreed) {
                        firstBreed = breed;
                        o.price = b.price;
                        o.avgprice = b.avgprice;
                        o.regionavgprice = b.regionavgprice;
                        o.lastseen = Date.parse(b.lastseen.replace(dateRegEx, dateRegExFmt)) / 1000;
                    }
                    o.breedCount++;
                    if (b.quantity && ((o.price > b.price) || (!o.quantity))) {
                        o.price = b.price;
                    }
                    o.quantity += b.quantity;
                    if ((o.avgprice > b.avgprice && b.avgprice) || (!o.avgprice)) {
                        o.avgprice = b.avgprice;
                    }
                    if ((o.regionavgprice > b.regionavgprice && b.regionavgprice) || (!o.regionavgprice)) {
                        o.regionavgprice = b.regionavgprice;
                    }
                    x = Date.parse(b.lastseen.replace(dateRegEx, dateRegExFmt)) / 1000;
                    if (o.lastseen < x) {
                        o.lastseen = x;
                    }
                    o.breeds[breed] = data[petType][species][breed];
                }

                if (!firstBreed) {
                    continue;
                }

                for (loc in tujConstants.locales) {
                    if (tujConstants.locales.hasOwnProperty(loc) && data[petType][species][firstBreed].hasOwnProperty('name_' + loc)) {
                        o['name_' + loc] = data[petType][species][firstBreed]['name_' + loc];
                    }
                }
                o.icon = data[petType][species][firstBreed].icon;
                o.npc = data[petType][species][firstBreed].npc;
                o.firstBreed = firstBreed;

                allSpecies.push(o);
            }

            allSpecies.sort(speciesSort);

            var curIconTd, curNameTd;
            for (x = 0; x < allSpecies.length; x++) {
                tr = libtuj.ce('tr');
                t.appendChild(tr);

                curIconTd = td = libtuj.ce('td');
                td.rowSpan = 1;
                td.className = 'icon';
                tr.appendChild(td);
                i = libtuj.ce('img');
                td.appendChild(i);
                i.className = 'icon';
                i.src = libtuj.IconURL(allSpecies[x].icon, 'medium');

                curNameTd = td = libtuj.ce('td');
                td.rowSpan = 1;
                td.className = 'name';
                tr.appendChild(td);
                a = libtuj.ce('a');
                td.appendChild(a);
                a.href = tuj.BuildHash({page: 'battlepet', id: allSpecies[x].id});
                a.rel = 'npc=' + allSpecies[x].npc + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
                $(a).text('[' + allSpecies[x]['name_' + tuj.locale] + ']');

                td = libtuj.ce('td');
                td.className = 'breeds';
                tr.appendChild(td);

                if (allSpecies[x].breedCount > 1) {
                    a = libtuj.ce('span');
                    a.style.cursor = 'pointer';
                    td.appendChild(a);
                    $(a).text('(' + tuj.lang.all + ')');
                    $(a).click(ShowBreedRows.bind(a, allSpecies[x].id, [curIconTd, curNameTd]));
                } else {
                    td.appendChild(document.createTextNode(tuj.lang.breedsLookup[allSpecies[x].firstBreed]));
                }

                td = libtuj.ce('td');
                td.className = 'quantity';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatQuantity(allSpecies[x].quantity));

                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(allSpecies[x].price));

                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(allSpecies[x].avgprice));

                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(allSpecies[x].regionavgprice));

                td = libtuj.ce('td');
                td.className = 'date';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatDate(allSpecies[x].lastseen));

                if (allSpecies[x].breedCount > 1) {
                    var workingBreeds = [];
                    for (breed in tuj.lang.breedsLookup) {
                        if (!tuj.lang.breedsLookup.hasOwnProperty(breed) || !allSpecies[x].breeds.hasOwnProperty(breed)) {
                            continue;
                        }
                        workingBreeds.push(breed);
                    }
                    workingBreeds.sort(breedSort);

                    for (i = 0; breed = workingBreeds[i]; i++) {
                        if (!tuj.lang.breedsLookup.hasOwnProperty(breed) || !allSpecies[x].breeds.hasOwnProperty(breed)) {
                            continue;
                        }
                        var b = allSpecies[x].breeds[breed];

                        tr = libtuj.ce('tr');
                        tr.className = 'breed species' + allSpecies[x].id;
                        t.appendChild(tr);

                        libtuj.AlsoHover(tr, curIconTd);
                        libtuj.AlsoHover(tr, curNameTd);

                        td = libtuj.ce('td');
                        td.className = 'breeds';
                        tr.appendChild(td);
                        a = libtuj.ce('a');
                        td.appendChild(a);
                        a.href = tuj.BuildHash({page: 'battlepet', id: '' + allSpecies[x].id + '.' + breed});
                        a.rel = 'npc=' + allSpecies[x].npc + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
                        $(a).text(tuj.lang.breedsLookup[breed]);

                        td = libtuj.ce('td');
                        td.className = 'quantity';
                        tr.appendChild(td);
                        td.appendChild(libtuj.FormatQuantity(b.quantity));

                        td = libtuj.ce('td');
                        td.className = 'price';
                        tr.appendChild(td);
                        td.appendChild(libtuj.FormatPrice(b.price));

                        td = libtuj.ce('td');
                        td.className = 'price';
                        tr.appendChild(td);
                        td.appendChild(libtuj.FormatPrice(b.avgprice));

                        td = libtuj.ce('td');
                        td.className = 'price';
                        tr.appendChild(td);
                        td.appendChild(libtuj.FormatPrice(b.regionavgprice));

                        td = libtuj.ce('td');
                        td.className = 'date';
                        tr.appendChild(td);
                        td.appendChild(libtuj.FormatDate(b.lastseen));

                    }
                }
            }

        }


    }

    resultFunctions.FishTable = function (data, dest)
    {
        var f, item, x, y, t, td, th, tr, a;

        var fishTypeCount = 4;

        if (!data.fish.length) {
            return false;
        }

        t = libtuj.ce('table');
        t.className = 'category category-fish';
        dest.appendChild(t);

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        td = libtuj.ce('th');
        td.className = 'title';
        td.colSpan = 2 + (fishTypeCount * 2);
        tr.appendChild(td);
        $(td).text(data.name);

        tr = libtuj.ce('tr');
        tr.className = 'subheading';
        t.appendChild(tr);

        td = libtuj.ce('th');
        td.className = 'name';
        td.colSpan = 2;
        tr.appendChild(td);
        $(td).text(tuj.lang.name);

        td = libtuj.ce('th');
        td.colSpan = 2;
        td.className = 'price';
        tr.appendChild(td);
        $(td).text(tuj.lang.flesh);

        td = libtuj.ce('th');
        td.colSpan = 2;
        td.className = 'price';
        tr.appendChild(td);
        $(td).text(tuj.lang.small);

        td = libtuj.ce('th');
        td.colSpan = 2;
        td.className = 'price';
        tr.appendChild(td);
        $(td).text(tuj.lang.regular);

        td = libtuj.ce('th');
        td.colSpan = 2;
        td.className = 'price';
        tr.appendChild(td);
        $(td).text(tuj.lang.enormous);

        tr = libtuj.ce('tr');
        tr.className = 'subheading';
        t.appendChild(tr);

        td = libtuj.ce('th');
        td.className = 'name';
        tr.appendChild(td);
        td.colSpan = 2;

        for (x = 1; x <= fishTypeCount; x++) {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text(tuj.lang.price);

            td = libtuj.ce('th');
            td.className = 'quantity';
            tr.appendChild(td);
            $(td).text(tuj.lang.availableAbbrev);
        }

        data.fish.sort(function (a, b)
        {
            return data.prices[b[0]].price - data.prices[a[0]].price;
        });

        for (x = 0; f = data.fish[x]; x++) {
            item = data.prices[f[2]];

            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('td');
            td.className = 'icon';
            tr.appendChild(td);
            i = libtuj.ce('img');
            td.appendChild(i);
            i.className = 'icon';
            i.src = libtuj.IconURL(item.icon, 'medium');

            td = libtuj.ce('td');
            td.className = 'name';
            tr.appendChild(td);
            a = libtuj.ce('a');
            $(td).text(item['name_' + tuj.locale]);

            for (y = 0; y < fishTypeCount && y < f.length; y++) {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                a = libtuj.ce('a');
                td.appendChild(a);
                a.href = tuj.BuildHash({page: 'item', id: f[y]});
                a.rel = 'item=' + f[y] + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
                a.appendChild(libtuj.FormatPrice(data.prices[f[y]].price));

                td = libtuj.ce('td');
                td.className = 'quantity';
                tr.appendChild(td);
                a = libtuj.ce('a');
                td.appendChild(a);
                a.href = tuj.BuildHash({page: 'item', id: f[y]});
                a.rel = 'item=' + f[y] + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
                a.appendChild(libtuj.FormatQuantity(data.prices[f[y]].quantity));
            }

        }

        return true;
    }


    this.load(tuj.params);
}

tuj.page_category = new TUJ_Category();
