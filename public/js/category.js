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

        var categoryPage = $('#category-page')[0];
        if (!categoryPage) {
            categoryPage = libtuj.ce();
            categoryPage.id = 'category-page';
            categoryPage.className = 'page';
            $('#main').append(categoryPage);
        }

        if (params.id == 'custom') {
            ShowCustomPage();
            return;
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

        if (!params.id) {
            tuj.SetParams({id: 'deals'}, true);
            return;
        }

        $('#progress-page').show();

        var ajaxTries = 0;
        var ajaxSettings = {
            data: qs,
            success: function (d)
            {
                if (d.captcha) {
                    tuj.AskCaptcha(d.captcha);
                }
                else {
                    CategoryResult(hash, d);
                }

                $('#progress-page').hide();
            },
            error: function (xhr, stat, er)
            {
                if (xhr.status == 429 && xhr.responseJSON && xhr.responseJSON.concurrent_retry) {
                    if (++ajaxTries >= 10) {
                        $('#progress-page').hide();

                        alert('Too many concurrent requests, please reload to try again.');
                    } else {
                        var delay = 2500 + Math.round(Math.random() * 1000) + 1000 * ajaxTries;
                        console.log('Other concurrent requests currently being processed, will retry after ' + delay + 'ms');

                        window.setTimeout(
                            function () {
                                $.ajax(ajaxSettings);
                            }, delay);
                    }

                    return;
                }

                $('#progress-page').hide();

                if ((xhr.status == 503) && xhr.responseJSON && xhr.responseJSON.maintenance) {
                    tuj.APIMaintenance(xhr.responseJSON.maintenance);
                } else {
                    alert('Error fetching page data: ' + xhr.status + ' ' + stat + ' ' + er);
                }
            },
            url: 'api/category.php'
        };

        $.ajax(ajaxSettings);
    };

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
        if (isNaN(days) || days < 14) {
            days = 14;
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
        daysBox.min = 14;
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

    function MakeRealmCompareSelBox(selectedRealmId) {
        var realms = [];
        for (var id in tuj.realms) {
            if (!tuj.realms.hasOwnProperty(id)) {
                continue;
            }
            realms.push(id);
        }
        realms.sort(function(a,b){
            return tuj.realms[a].name.localeCompare(tuj.realms[b].name);
        });

        var sel = libtuj.ce('select');
        var opt = libtuj.ce('option');
        opt.value = '';
        opt.label = '';
        opt.appendChild(document.createTextNode(''));
        sel.appendChild(opt);

        for (var x = 0, realm; realm = realms[x]; x++) {
            realm = tuj.realms[realm];

            opt = libtuj.ce('option');
            opt.value = realm.id;
            opt.label = realm.name;
            opt.appendChild(document.createTextNode(realm.name));
            sel.appendChild(opt);
            if (realm.id == selectedRealmId) {
                opt.selected = true;
            }
        }

        return sel;
    }

    function ShowCustomPage()
    {
        var categoryPage = $('#category-page');
        categoryPage.empty();
        categoryPage.show();

        var titleName = tuj.lang.custom;

        $('#page-title').empty().append(document.createTextNode(tuj.lang.category + ': ' + titleName));
        tuj.SetTitle(tuj.lang.category + ': ' + titleName);

        var d = libtuj.ce('div');
        d.className = 'custom-textarea';
        categoryPage.append(d);

        var resultsDiv = libtuj.ce('div');
        categoryPage.append(resultsDiv);

        d.appendChild(document.createTextNode(tuj.lang.pasteInItems));

        d.appendChild(libtuj.ce('br'));

        var ta = libtuj.ce('textarea');
        d.appendChild(ta);

        d.appendChild(libtuj.ce('br'));

        d.appendChild(document.createTextNode(tuj.lang.compareWith));
        var sel = MakeRealmCompareSelBox();
        d.appendChild(sel);

        var btn = libtuj.ce('input');
        btn.type = 'button';
        btn.value = tuj.lang.submit;
        d.appendChild(btn);
        $(btn).on('click', LoadCustomItems.bind(btn, ta, sel, resultsDiv));
    }

    function ParseCustomItemListMatch(dumpInto, match, p1)
    {
        dumpInto[p1] = true;
        return '';
    }

    function CustomItemSectionsComparitor(a,b) {
        var aClass = a.hasOwnProperty('data') && a.data.hasOwnProperty('name') && (a.data.name.indexOf('itemClasses.') == 0) ? parseInt(a.data.name.substr(12)) : 0;
        var bClass = b.hasOwnProperty('data') && b.data.hasOwnProperty('name') && (b.data.name.indexOf('itemClasses.') == 0) ? parseInt(b.data.name.substr(12)) : 0;
        if (tujConstants.itemClassOrder.hasOwnProperty(aClass) && tujConstants.itemClassOrder.hasOwnProperty(bClass)) {
            return tujConstants.itemClassOrder[aClass] - tujConstants.itemClassOrder[bClass];
        }
        return 0;
    }

    function LoadCustomItems(ta, realmSel, resultsDiv) {
        var rawItemList = ta.value;
        var itemList = {};
        var items = [];

        var matcher = ParseCustomItemListMatch.bind(null, itemList);

        rawItemList = rawItemList.replace(/\bp(:\d+)+/g, ''); // remove pets from tsm shopping list
        rawItemList = rawItemList.replace(/\bi(?:tem)?:(\d+)(?::\d+)*/g, matcher); // use items without bonuses from tsm shopping list
        rawItemList = rawItemList.replace(/(\d+)/g, matcher); // any other numbers assumed to be items

        for (var i in itemList) {
            if (itemList.hasOwnProperty(i)) {
                items.push(i);
                if (items.length >= 250) {
                    break;
                }
            }
        }

        if (!items.length) {
            return;
        }

        var compareRealm = realmSel.selectedIndex == 0 ? false : realmSel.options[realmSel.selectedIndex].value;
        var compareHouse = compareRealm ? tuj.realms[compareRealm].house : false;

        $('#category-page').hide();
        $('#progress-page').show();
        $(resultsDiv).empty();

        $.ajax({
            data: {'items': items.join(',')},
            method: 'POST',
            success: function (d)
            {
                if (d.captcha) {
                    tuj.AskCaptcha(d.captcha);
                } else {
                    if (d.hasOwnProperty('results')) {
                        d.results.sort(CustomItemSectionsComparitor);
                    }
                    if (compareHouse) {
                        $.ajax({
                            data: {'items': items.join(',')},
                            method: 'POST',
                            success: function (d2)
                            {
                                if (d2.captcha) {
                                    tuj.AskCaptcha(d2.captcha);
                                } else {
                                    CategoryResult(false, MergeComparedData(d, d2, compareRealm), resultsDiv);
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
                            url: 'api/category.php?house=' + compareHouse + '&id=custom'
                        })
                    } else {
                        CategoryResult(false, d, resultsDiv);
                        $('#progress-page').hide();
                    }
                }
            },
            error: function (xhr, stat, er)
            {
                if ((xhr.status == 503) && xhr.hasOwnProperty('responseJSON') && xhr.responseJSON && xhr.responseJSON.hasOwnProperty('maintenance')) {
                    tuj.APIMaintenance(xhr.responseJSON.maintenance);
                } else {
                    alert('Error fetching page data: ' + stat + ' ' + er);
                }
                $('#progress-page').hide();
            },
            url: 'api/category.php?house=' + tuj.realms[params.realm].house + '&id=custom'
        });
    }

    function LoadComparedRealm(dta, realmSel, resultsDiv) {
        var compareRealm = realmSel.selectedIndex == 0 ? false : realmSel.options[realmSel.selectedIndex].value;
        var compareHouse = compareRealm ? tuj.realms[compareRealm].house : false;

        $('#category-page').hide();
        $('#progress-page').show();
        $(resultsDiv).empty();

        if (!compareHouse) {
            CategoryResult(false, MergeComparedData(dta), resultsDiv);
            $('#progress-page').hide();
            return;
        }

        $.ajax({
            data: $.extend({}, params, {'house': compareHouse}),
            success: function (d)
            {
                if (d.captcha) {
                    tuj.AskCaptcha(d.captcha);
                } else {
                    CategoryResult(false, MergeComparedData(dta, d, compareRealm), resultsDiv);
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

    function MergeComparedData(fromData, theirData, theirRealmId) {
        var x, y, z, ourSection, theirSection;

        var ourData = $.extend(true, {}, fromData);

        if (theirRealmId) {
            ourData.compareTo = theirRealmId;
        } else {
            delete ourData.compareTo;
        }
        for (x = 0; ourSection = ourData.results[x]; x++) {
            if (ourSection.name != 'ItemList') {
                continue;
            }
            delete ourSection.data.compareTo;
            if (ourSection.data.hasOwnProperty('hiddenCols')) {
                ourSection.data.hiddenCols.lastseen = false;
            }
            for (y = 0; y < ourSection.data.items.length; y++) {
                delete ourSection.data.items[y].compareTo;
            }
        }

        if (!theirData) {
            return ourData;
        }

        while (theirSection = theirData.results.shift()) {
            if (theirSection.name != 'ItemList') {
                continue;
            }
            for (x = 0; ourSection = ourData.results[x]; x++) {
                if (ourSection.name != 'ItemList') {
                    continue;
                }
                if (ourSection.data.name != theirSection.data.name) {
                    continue;
                }
                ourSection.data.compareTo = theirRealmId;
                if (!ourSection.data.hasOwnProperty('hiddenCols')) {
                    ourSection.data.hiddenCols = {};
                }
                ourSection.data.hiddenCols.lastseen = true;

                for (y = 0; y < ourSection.data.items.length; y++) {
                    for (z in theirSection.data.items) {
                        if (!theirSection.data.items.hasOwnProperty(z)) {
                            continue;
                        }
                        if (ourSection.data.items[y].id == theirSection.data.items[z].id &&
                            ourSection.data.items[y].level == theirSection.data.items[z].level) {
                            ourSection.data.items[y].compareTo = theirSection.data.items[z];
                            delete theirSection.data.items[z];
                            break;
                        }
                    }
                }
            }
        }

        return ourData;
    }

    function MakeImportStringDisplayFunction(tr, inpt, btn) {
        return function() {
            tr.style.display = '';
            btn.style.display = 'none';
            inpt.select();
        };
    }

    function LoadCharacterPets(dta, realmSel, charInput, resultsDiv) {
        var loadRealm = realmSel.selectedIndex == 0 ? false : realmSel.options[realmSel.selectedIndex].value;
        var loadChar = charInput.value.replace(/\s+/g, '');

        $(resultsDiv).empty();

        if (!loadRealm || !loadChar) {
            return;
        }

        var post = {
            'char': loadChar,
            'realm': loadRealm,
        };

        $('#progress-page').show();

        $.ajax({
            data: post,
            success: function (d)
            {
                if (d.captcha) {
                    tuj.AskCaptcha(d.captcha);
                } else {
                    if (d.pets) {
                        ShowCharacterPets(dta, d.pets, loadChar, resultsDiv);
                    }
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
            url: 'api/characterpets.php'
        });
    }

    function ShowCharacterPets(priceLookup, pets, charName, dest) {
        var speciesList = [];
        for (var s in pets) {
            if (!pets.hasOwnProperty(s)) {
                continue;
            }
            if (!priceLookup.hasOwnProperty(s)) {
                continue;
            }
            speciesList.push(s);
        }

        speciesList.sort(function(a, b) {
            return (priceLookup[b].price - priceLookup[a].price) ||
                priceLookup[a]['name_' + tuj.locale].localeCompare(priceLookup[b]['name_' + tuj.locale]);
        });

        var tr, td, t = libtuj.ce('table');
        dest.appendChild(t);
        t.className = 'category category-battlepets';

        tr = libtuj.ce('tr');
        t.appendChild(tr);
        td = libtuj.ce('th');
        tr.appendChild(td);
        td.colSpan = 12;
        $(td).text(charName.substr(0,1).toLocaleUpperCase() + charName.substr(1).toLocaleLowerCase());

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        td = libtuj.ce('th');
        tr.appendChild(td);
        td.colSpan = 2;
        td.className = 'name';
        $(td).text(tuj.lang.name);

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

        var curIconTd, curNameTd, pet;
        for (var x = 0; x < speciesList.length; x++) {
            pet = priceLookup[speciesList[x]];

            tr = libtuj.ce('tr');
            t.appendChild(tr);

            curIconTd = td = libtuj.ce('td');
            td.rowSpan = 1;
            td.className = 'icon';
            tr.appendChild(td);
            var i = libtuj.ce('img');
            td.appendChild(i);
            i.className = 'icon';
            i.src = libtuj.IconURL(pet.icon, 'medium');

            curNameTd = td = libtuj.ce('td');
            td.rowSpan = 1;
            td.className = 'name';
            tr.appendChild(td);
            var a = libtuj.ce('a');
            td.appendChild(a);
            a.href = tuj.BuildHash({page: 'battlepet', id: pet.species});
            a.rel = 'npc=' + pet.npc + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
            $(a).text('[' + pet['name_' + tuj.locale] + ']');

            td = libtuj.ce('td');
            td.className = 'quantity';
            tr.appendChild(td);
            td.appendChild(libtuj.FormatQuantity(pet.quantity));

            td = libtuj.ce('td');
            td.className = 'price';
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(pet.price));

            td = libtuj.ce('td');
            td.className = 'price';
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(pet.avgprice));

            td = libtuj.ce('td');
            td.className = 'price';
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(pet.regionavgprice));

            td = libtuj.ce('td');
            td.className = 'date';
            tr.appendChild(td);
            td.appendChild(libtuj.FormatDate(pet.lastseen));

            for (var y = pets[speciesList[x]]; y > 1; y--) {
                t.appendChild(tr.cloneNode(true));
            }
        }
    }

    function CategoryResult(hash, dta, resultsContainer)
    {
        if (hash) {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10) {
                lastResults.shift();
            }
        }

        var resultsDiv = resultsContainer ? $(resultsContainer) : $('#category-page');
        resultsDiv.empty();
        $('#category-page').show();

        if (!dta.hasOwnProperty('name')) {
            $('#page-title').empty().append(document.createTextNode(tuj.lang.category + ': ' + params.id));
            tuj.SetTitle(tuj.lang.category + ': ' + params.id);

            var h2 = libtuj.ce('h2');
            resultsDiv.append(h2);
            h2.appendChild(document.createTextNode(libtuj.sprintf(tuj.lang.notFound, tuj.lang.category + ' ' + params.id)));

            return;
        }

        var titleName = dta.name;
        if (tuj.lang.hasOwnProperty(titleName)) {
            titleName = tuj.lang[titleName];
        }

        $('#page-title').empty().append(document.createTextNode(tuj.lang.category + ': ' + titleName));
        tuj.SetTitle(tuj.lang.category + ': ' + titleName);

        var everySpecies = {};

        if (dta.hasOwnProperty('results')) {
            if (['custom', 'battlepets', 'deals', 'unusualItems', 'potentialLowBids'].indexOf(dta.name) < 0) {
                var compareDiv = libtuj.ce('div');
                compareDiv.className = 'custom-textarea';
                resultsDiv.append(compareDiv);

                compareDiv.appendChild(document.createTextNode(tuj.lang.compareWith));
                var sel = MakeRealmCompareSelBox(dta.compareTo);
                compareDiv.appendChild(sel);

                var btn = libtuj.ce('input');
                btn.type = 'button';
                btn.value = tuj.lang.submit;
                compareDiv.appendChild(btn);
                $(btn).on('click', LoadComparedRealm.bind(btn, dta, sel, resultsDiv));
            } else if (dta.name == 'battlepets') {
                var loadPetsDiv = libtuj.ce('div');
                loadPetsDiv.className = 'custom-textarea center';
                resultsDiv.append(loadPetsDiv);
                loadPetsDiv.appendChild(document.createTextNode("Load pets from:"));

                var sel = MakeRealmCompareSelBox(params.realm);
                loadPetsDiv.appendChild(sel);

                var inbox = libtuj.ce('input');
                inbox.style.width = '15em';
                inbox.style.margin = '0 1em';
                inbox.maxLength = 12;
                inbox.placeholder = tuj.lang.name;
                loadPetsDiv.appendChild(inbox);

                var btn = libtuj.ce('input');
                btn.type = 'button';
                btn.value = tuj.lang.submit;
                loadPetsDiv.appendChild(btn);

                var charPetsResults = libtuj.ce('div');
                resultsDiv.append(charPetsResults);
            }

            resultsDiv.append(libtuj.Ads.Add('8323200718'));

            var r, f, resultCount = 0;
            for (var x = 0; f = dta.results[x]; x++) {
                if (resultFunctions.hasOwnProperty(f.name)) {
                    var d = libtuj.ce();
                    d.className = 'category category-' + f.name.toLowerCase();
                    resultsDiv.append(d);
                    if ((r = resultFunctions[f.name](f.data, d)) && (++resultCount == 5)) {
                        //resultsDiv.append(libtuj.Ads.Add('2276667118'));
                    }
                    if (f.name == 'BattlePetList') {
                        $.extend(everySpecies, r);
                    }
                }
            }

            if (dta.name == 'battlepets' && everySpecies) {
                $(btn).on('click', LoadCharacterPets.bind(btn, everySpecies, sel, inbox, charPetsResults));
            }
        }

        if (dta.name == 'unusualItems') {
            resultsDiv.append(MakeNotificationsSection(tuj.realms[params.realm].house));
        }

        libtuj.Ads.Show();
    }

    resultFunctions.ItemList = function (data, dest)
    {
        var item, x, t, td, th, tr, a, i, comparePrice, pct;

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

        var getAmountByItem = function(amounts, id) {
            return amounts ? (amounts[id] || amounts[0] || 1) : 1;
        };

        var abbrPriceAmount = function(price, amount) {
            if (amount == 1) {
                return libtuj.FormatPrice(price);
            }

            var abbr = libtuj.ce('abbr');
            abbr.title = '' + amount + ' x ' + libtuj.FormatPrice(price, true);
            abbr.appendChild(libtuj.FormatPrice(price * amount));
            return abbr;
        };

        var titleColSpan = 2;
        var titleTd;

        var hasCraftingPrice = false;
        var nonUniques = {};
        for (x = 0; item = data.items[x]; x++) {
            if (nonUniques.hasOwnProperty(item.id)) {
                nonUniques[item.id] = true;
            } else {
                nonUniques[item.id] = false;
            }
            hasCraftingPrice |= !!item.craftingprice;
        }
        hasCraftingPrice &= !data.hiddenCols.craftingprice;

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

            var sluggedName = data.name.replace(/[^ a-zA-Z0-9\.\-_]/, '');
            for (x = 0; x < sluggedName.length; x++) {
                if (sluggedName.substr(x, 1) == ' ') {
                    sluggedName = sluggedName.substr(0, x) + sluggedName.substr(x+1, 1).toUpperCase() + sluggedName.substr(x+2);
                }
            }

            var sluggedParts = sluggedName.split('.');
            var langObj = tuj.lang;
            var title = data.name;
            while (sluggedParts.length) {
                var slugPart = sluggedParts.shift();
                if (!langObj.hasOwnProperty(slugPart)) {
                    break;
                }
                if (!sluggedParts.length) {
                    title = langObj[slugPart];
                } else {
                    langObj = langObj[slugPart];
                }
            }
            $(td).text(title);
        }

        tr = libtuj.ce('tr');
        t.appendChild(tr);

        if (data.amounts) {
            td = libtuj.ce('th');
            td.className = 'quantity';
            tr.appendChild(td);
            titleColSpan++;
        }

        td = libtuj.ce('th');
        td.className = 'name';
        tr.appendChild(td);
        td.colSpan = 2;
        $(td).text(tuj.lang.name);

        if (!data.hiddenCols.quantity) {
            td = libtuj.ce('th');
            td.className = 'quantity';
            tr.appendChild(td);
            $(td).text(tuj.lang.availableAbbrev);
            titleColSpan++;
        }

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

        if (hasCraftingPrice) {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text(tuj.lang.materials);
            titleColSpan++;

            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text(tuj.lang.profit);
            titleColSpan++;
        }

        if (!data.hiddenCols.avgprice) {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text(tuj.lang.mean);
            titleColSpan++;
        }

        if (data.visibleCols.globalmedian) {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text(tuj.lang.regional);
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

        if (data.compareTo) {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text(tuj.realms[data.compareTo].name);
            titleColSpan++;

            td = libtuj.ce('th');
            td.className = 'quantity';
            tr.appendChild(td);
            titleColSpan++;
        }

        if (titleTd) {
            titleTd.colSpan = titleColSpan;
        }

        libtuj.TableSort.Make(t);

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
                        a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]) ||
                        a.requiredside.localeCompare(b.requiredside);
                });
                break;

            case 'globalmedian diff':
                data.items.sort(function (a, b)
                {
                    return ((b.globalmedian - b.price) - (a.globalmedian - a.price)) ||
                        ((a.price ? 0 : 1) - (b.price ? 0 : 1)) ||
                        (a.price - b.price) ||
                        a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]) ||
                        a.requiredside.localeCompare(b.requiredside);
                });
                break;

            case 'globalmedian':
                data.items.sort(function (a, b)
                {
                    return ((a.globalmedian ? 0 : 1) - (b.globalmedian ? 0 : 1)) ||
                        (b.globalmedian - a.globalmedian) ||
                        a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]) ||
                        a.requiredside.localeCompare(b.requiredside);
                });
                break;

            case 'lowprice':
                data.items.sort(function (a, b)
                {
                    return ((a.price ? 0 : 1) - (b.price ? 0 : 1)) ||
                        (a.price * getAmountByItem(data.amounts, a.id) - b.price * getAmountByItem(data.amounts, b.id)) ||
                        a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]) ||
                        a.requiredside.localeCompare(b.requiredside);
                });
                break;

            default:
                data.items.sort(function (a, b)
                {
                    return ((a.price ? 0 : 1) - (b.price ? 0 : 1)) ||
                        (b.price * getAmountByItem(data.amounts, b.id) - a.price * getAmountByItem(data.amounts, a.id)) ||
                        a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]) ||
                        a.requiredside.localeCompare(b.requiredside);
                });
        }


        if (titleTd) {
            tr = libtuj.ce('tr');
            tr.style.display = 'none';
            t.insertBefore(tr, titleTd.parentNode.nextSibling);

            td = libtuj.ce('th');
            td.className = 'import-string';
            td.colSpan = titleColSpan;
            tr.appendChild(td);

            var i = libtuj.ce('input');
            i.type = 'text';
            i.readOnly = true;
            $(i).on('click', function(){ this.select(); });
            td.appendChild(i);

            var tsmSep = data.dynamicItems ? ';' : ',';
            var distinctItems = {};
            for (x = 0; item = data.items[x]; x++) {
                if (!distinctItems.hasOwnProperty(item.id)) {
                    distinctItems[item.id] = true;
                    i.value = (i.value ? i.value + tsmSep : '') + 'i:' + item.id;
                }
            }

            x = libtuj.ce('span');
            x.className = 'import-toggle';
            titleTd.appendChild(x);
            x.appendChild(document.createTextNode('TSM'));
            $(x).on('click', MakeImportStringDisplayFunction(tr, i, x));
        }

        for (x = 0; item = data.items[x]; x++) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            var amount = 1;
            if (data.amounts) {
                td = libtuj.ce('td');
                td.className = 'quantity';
                tr.appendChild(td);
                amount = getAmountByItem(data.amounts, item.id);
                td.appendChild(libtuj.FormatQuantity(amount));
            }

            td = libtuj.ce('td');
            td.className = 'icon';
            tr.appendChild(td);
            if (item.requiredside) {
                td.className += ' double';
                i = libtuj.ce('img');
                td.appendChild(i);
                i.className = 'icon';
                i.src = libtuj.IconURL('ui_' + item.requiredside.toLowerCase() + 'icon', 'medium');
            }
            i = libtuj.ce('img');
            td.appendChild(i);
            i.className = 'icon';
            i.src = libtuj.IconURL(item.icon, 'medium');

            td = libtuj.ce('td');
            td.className = 'name';
            tr.appendChild(td);
            a = libtuj.ce('a');
            td.appendChild(a);
            a.rel = 'item=' + item.id + (item.rand ? '&rand=' + item.rand : '') + (item.bonusurl ? '&bonus=' + item.bonusurl : '') + (item.lootedlevel ? '&lvl=' + item.lootedlevel : '') + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
            a.href = tuj.BuildHash({page: 'item', id: item.id + ((item.level && item.level != item.baselevel) ? '.' + item.level : '')});
            $(a).text('[' + item['name_' + tuj.locale] + (item['bonusname_' + tuj.locale] ? ' ' + item['bonusname_' + tuj.locale].substr(0, item['bonusname_' + tuj.locale].indexOf('|') >= 0 ? item['bonusname_' + tuj.locale].indexOf('|') : item['bonusname_' + tuj.locale].length) : '') + ']');
            if (item.level && item.baselevel) {
                if (nonUniques[item.id] || item.level != item.baselevel) {
                    var s = libtuj.ce('span');
                    s.className = 'level';
                    s.appendChild(document.createTextNode(item.level));
                    a.appendChild(s);
                }
                if (!item.bonusurl && item.level != item.baselevel) {
                    a.rel += '&bonus=' + libtuj.LevelOffsetBonus(item.level - item.baselevel);
                }
            }
            $(a).data('sort', a.textContent);

            if (!data.hiddenCols.quantity) {
                td = libtuj.ce('td');
                td.className = 'quantity';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatQuantity(item.quantity));
            }

            if (data.visibleCols.bid) {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(abbrPriceAmount(item.bid, amount));
            }

            if (!data.hiddenCols.price) {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(abbrPriceAmount(item.price, amount));
            }

            if (hasCraftingPrice) {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(abbrPriceAmount(item.craftingprice, amount));

                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                if (item.price && item.craftingprice) {
                    td.appendChild(abbrPriceAmount(item.price - item.craftingprice, amount));
                    pct = item.craftingprice / item.price * 100;
                    if (pct < 66) {
                        td.className += ' pct-mid';
                    } else if (pct < 110) {
                        td.className += ' pct-normal';
                    } else if (pct < 135) {
                        td.className += ' pct-high';
                    } else {
                        td.className += ' pct-veryhigh';
                    }
                }
            }

            if (!data.hiddenCols.avgprice) {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(abbrPriceAmount(item.avgprice, amount));
            }

            if (data.visibleCols.globalmedian) {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(abbrPriceAmount(item.globalmedian, amount));
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

            if (data.compareTo && item.hasOwnProperty('compareTo')) {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                a = libtuj.ce('a');
                td.appendChild(a);
                a.href = tuj.BuildHash({realm: data.compareTo, page: 'item', id: item.id});
                comparePrice = item.compareTo.avgprice || item.compareTo.price;
                a.appendChild(abbrPriceAmount(comparePrice, amount));

                pct = (item.quantity ? item.price : (item.avgprice || item.price)) / comparePrice * 100;
                if (!isNaN(pct)) {
                    pct = Math.min(pct, 999);

                    td = libtuj.ce('td');
                    td.className = 'quantity ';
                    if (pct < 50) {
                        td.className += 'pct-low';
                    } else if (pct < 80) {
                        td.className += 'pct-mid';
                    } else if (pct < 110) {
                        td.className += 'pct-normal';
                    } else if (pct < 135) {
                        td.className += 'pct-high';
                    } else {
                        td.className += 'pct-veryhigh';
                    }
                    tr.appendChild(td);
                    td.appendChild(libtuj.FormatQuantity(pct));
                }
            }
        }

        return true;
    };

    resultFunctions.BattlePetList = function (data, dest)
    {
        var t, td, tr, species, petType, allSpecies, o, x, b, i, a, loc;

        var dateRegEx = /^(\d{4}-\d\d-\d\d) (\d\d:\d\d:\d\d)$/;
        var dateRegExFmt = '$1T$2.000Z';

        var speciesSort = function (a, b) {
            return (b.price - a.price) ||
                a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]);
        };

        var everySpecies = {};

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

            libtuj.TableSort.Make(t);

            allSpecies = [];

            for (species in data[petType]) {
                if (!data[petType].hasOwnProperty(species)) {
                    continue;
                }

                allSpecies.push(data[petType][species]);
                everySpecies[species] = data[petType][species];
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
                a.href = tuj.BuildHash({page: 'battlepet', id: allSpecies[x].species});
                a.rel = 'npc=' + allSpecies[x].npc + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
                $(a).text('[' + allSpecies[x]['name_' + tuj.locale] + ']');
                $(a).data('sort', a.textContent);

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
            }

        }

        return everySpecies;
    };

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
    };

    resultFunctions.RecipeList = function (data, dest)
    {
        var item, crafted, map, x, t, td, th, tr, a, i;

        if (!data.map.length) {
            return false;
        }

        t = libtuj.ce('table');
        t.className = 'category category-recipes';
        dest.appendChild(t);

        if (data.hasOwnProperty('name')) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('th');
            td.className = 'title';
            tr.appendChild(td);
            td.colSpan = 6;

            var sluggedName = data.name.toLowerCase().replace(/[^ a-z0-9_]/, '');
            for (x = 0; x < sluggedName.length; x++) {
                if (sluggedName.substr(x, 1) == ' ') {
                    sluggedName = sluggedName.substr(0, x) + sluggedName.substr(x+1, 1).toUpperCase() + sluggedName.substr(x+2);
                }
            }

            if (tuj.lang.hasOwnProperty(sluggedName)) {
                $(td).text(tuj.lang[sluggedName]);
            } else if (sluggedName == 'recipes') {
                $(td).text(tuj.lang.itemClasses[9]);
            } else {
                $(td).text(data.name);
            }
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

        td = libtuj.ce('th');
        td.className = 'price';
        tr.appendChild(td);
        $(td).text(tuj.lang.price);

        td = libtuj.ce('th');
        td.className = 'quantity';
        tr.appendChild(td);
        $(td).text(tuj.lang.availableAbbrev);

        td = libtuj.ce('th');
        td.className = 'price';
        tr.appendChild(td);
        $(td).text(tuj.lang.price);

        libtuj.TableSort.Make(t);

        data.map.sort(function(a, b) {
            var recipeA = data.recipes[a.recipe];
            var craftedA = data.crafted[a.crafted];
            var recipeB = data.recipes[b.recipe];
            var craftedB = data.crafted[b.crafted];

            if (!recipeA || !craftedA) {
                return 1;
            }
            if (!recipeB || !craftedB) {
                return -1;
            }

            var recipePriceA = recipeA.price || 500000;
            var recipePriceB = recipeB.price || 500000;
            var craftedPriceA = craftedA.price || 0;
            var craftedPriceB = craftedB.price || 0;

            var ratioA = recipePriceA * Math.log(recipePriceA / 10000) - craftedPriceA;
            var ratioB = recipePriceB * Math.log(recipePriceB / 10000) - craftedPriceB;

            return ((recipePriceA - craftedPriceA > 0 ? 1 : 0) - (recipePriceB - craftedPriceB > 0 ? 1 : 0)) || // recipe cheaper than crafted at top
                (((recipePriceA && !craftedPriceA) ? 0 : 1) - ((recipePriceB && !craftedPriceB) ? 0 : 1)) || // has recipe but not crafted at bottom
                (ratioA - ratioB) || // degree of profitability
                (recipePriceA - recipePriceB) || // cheaper recipes at top
                (craftedPriceA - craftedPriceB) || // cheaper items at top
                recipeA['name_' + tuj.locale].localeCompare(recipeB['name_' + tuj.locale]);
        });

        for (x = 0; map = data.map[x]; x++) {
            item = data.recipes[map.recipe];
            crafted = data.crafted[map.crafted];

            if (!item) {
                continue;
            }

            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('td');
            td.className = 'icon';
            tr.appendChild(td);
            if (item.requiredside) {
                td.className += ' double';
                i = libtuj.ce('img');
                td.appendChild(i);
                i.className = 'icon';
                i.src = libtuj.IconURL('ui_' + item.requiredside.toLowerCase() + 'icon', 'medium');
            }
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
            a.href = tuj.BuildHash({page: 'item', id: item.id});
            $(a).text('[' + item['name_' + tuj.locale] + (item['bonusname_' + tuj.locale] ? ' ' + item['bonusname_' + tuj.locale].substr(0, item['bonusname_' + tuj.locale].indexOf('|') >= 0 ? item['bonusname_' + tuj.locale].indexOf('|') : item['bonusname_' + tuj.locale].length) : '') + ']');
            $(a).data('sort', a.textContent);

            td = libtuj.ce('td');
            td.className = 'quantity';
            tr.appendChild(td);
            if (item.quantity > 0 || !item.lastseen) {
                td.appendChild(libtuj.FormatQuantity(item.quantity));
            } else {
                a = libtuj.ce('abbr');
                a.className = 'full-date';
                a.title = tuj.lang.lastSeen + ' ' + libtuj.FormatDate(item.lastseen, true);
                a.appendChild(libtuj.FormatQuantity(item.quantity));
                td.appendChild(a);
            }

            td = libtuj.ce('td');
            td.className = 'price';
            tr.appendChild(td);
            td.appendChild(libtuj.FormatPrice(item.price));

            td = libtuj.ce('td');
            td.className = 'quantity';
            tr.appendChild(td);
            if (crafted) {
                if (crafted.quantity > 0 || !crafted.lastseen) {
                    td.appendChild(libtuj.FormatQuantity(crafted.quantity));
                } else {
                    a = libtuj.ce('abbr');
                    a.className = 'full-date';
                    a.title = tuj.lang.lastSeen + ' ' + libtuj.FormatDate(crafted.lastseen, true);
                    a.appendChild(libtuj.FormatQuantity(crafted.quantity));
                    td.appendChild(a);
                }
            }

            td = libtuj.ce('td');
            td.className = 'price';
            tr.appendChild(td);
            if (crafted) {
                a = libtuj.ce('a');
                td.appendChild(a);
                a.rel = 'item=' + crafted.id + (crafted.bonusurl ? '&bonus=' + crafted.bonusurl : (crafted.basebonus ? '&bonus=' + crafted.basebonus : '')) + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
                a.href = tuj.BuildHash({page: 'item', id: crafted.id});
                a.appendChild(libtuj.FormatPrice(crafted.price));
            }
        }

        return true;
    };


    this.load(tuj.params);
};

tuj.page_category = new TUJ_Category();
