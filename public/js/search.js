var TUJ_Search = function ()
{
    var params;
    var lastResults = [];

    this.load = function (inParams)
    {
        params = {};
        for (var p in inParams) {
            if (inParams.hasOwnProperty(p)) {
                params[p] = inParams[p];
            }
        }

        var qs = {
            locale: tuj.locale,
            house: tuj.realms[params.realm].house,
            search: params.id
        };
        var hash = JSON.stringify(qs);

        for (var x = 0; x < lastResults.length; x++) {
            if (lastResults[x].hash == hash) {
                SearchResult(false, lastResults[x].data);
                return;
            }
        }

        var searchPage = $('#search-page')[0];
        if (!searchPage) {
            searchPage = libtuj.ce();
            searchPage.id = 'search-page';
            searchPage.className = 'page';
            $('#main').append(searchPage);
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
                    SearchResult(hash, d);
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
            url: 'api/search.php'
        };

        $.ajax(ajaxSettings);
    };

    function AddToSearched(a, txt)
    {
        $(a).click(libtuj.Searched.Add.bind(null, txt));
        return txt;
    }

    function SearchResult(hash, dta)
    {
        if (hash) {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10) {
                lastResults.shift();
            }
        }

        var searchPage = $('#search-page');
        searchPage.empty();

        $('#page-title').text(tuj.lang.search + ': ' + params.id);

        var results = 0;
        var lastResult;
        var lastResultName = '';
        var t, tr, td, i, a, x;

        if (dta.items) {
            dta.items.sort(function (a, b) {
                return tujConstants.itemClassOrder[a.classid] - tujConstants.itemClassOrder[b.classid] ||
                    a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]) ||
                    a.level - b.level ||
                    a.requiredside.localeCompare(b.requiredside);
            });

            var lastClass = -1;
            var item;
            var tableHeader, classResults;

            for (x = 0; item = dta.items[x]; x++) {
                lastResult = {page: 'item', id: item.id};
                results++;

                if (lastClass != item.classid) {
                    lastClass = item.classid;
                    classResults = 1;

                    t = libtuj.ce('table');
                    t.className = 'search-items';
                    searchPage.append(t);

                    tr = libtuj.ce('tr');
                    t.appendChild(tr);

                    td = libtuj.ce('th');
                    td.className = 'title';
                    tr.appendChild(td);
                    td.colSpan = 6;
                    $(td).text(tuj.lang.itemClasses.hasOwnProperty(item.classid) ? tuj.lang.itemClasses[item.classid] : (tuj.lang.class + ' ' + item.classid));

                    tr = libtuj.ce('tr');
                    tableHeader = tr;
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
                    $(td).text(tuj.lang.currentPriceAbbrev);

                    td = libtuj.ce('th');
                    td.className = 'price';
                    tr.appendChild(td);
                    $(td).text(tuj.lang.mean);

                    td = libtuj.ce('th');
                    td.className = 'date';
                    tr.appendChild(td);
                    $(td).text(tuj.lang.lastSeen);
                }
                else {
                    if (++classResults % 30 == 0) {
                        t.appendChild(tableHeader.cloneNode(true));
                    }
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
                a.rel = 'item=' + item.id + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
                a.href = tuj.BuildHash({page: 'item', id: item.id});
                $(a).text('[' + item['name_' + tuj.locale] + ']');
                lastResultName = AddToSearched(a, item['name_' + tuj.locale]);

                td = libtuj.ce('td');
                td.className = 'quantity';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatQuantity(item.quantity));

                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(item.price));

                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(item.avgprice));

                td = libtuj.ce('td');
                td.className = 'date';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatDate(item.lastseen));
            }
        }

        if (dta.battlepets) {
            dta.battlepets.sort(function (a, b)
            {
                return a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]);
            });

            var pet;
            var tableHeader, petResults = 0;

            for (x = 0; pet = dta.battlepets[x]; x++) {
                lastResult = {page: 'battlepet', id: pet.id};
                results++;

                if (petResults++ == 0) {
                    t = libtuj.ce('table');
                    t.className = 'search-pets';
                    searchPage.append(t);

                    tr = libtuj.ce('tr');
                    t.appendChild(tr);

                    td = libtuj.ce('th');
                    td.className = 'title';
                    tr.appendChild(td);
                    td.colSpan = 6;
                    $(td).text(tuj.lang.battlepets);

                    tr = libtuj.ce('tr');
                    tableHeader = tr;
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
                    $(td).text(tuj.lang.currentPriceAbbrev);

                    td = libtuj.ce('th');
                    td.className = 'price';
                    tr.appendChild(td);
                    $(td).text(tuj.lang.mean);

                    td = libtuj.ce('th');
                    td.className = 'date';
                    tr.appendChild(td);
                    $(td).text(tuj.lang.lastSeen);
                }
                else {
                    if (petResults % 30 == 0) {
                        t.appendChild(tableHeader.cloneNode(true));
                    }
                }

                tr = libtuj.ce('tr');
                t.appendChild(tr);

                td = libtuj.ce('td');
                td.className = 'icon';
                tr.appendChild(td);
                i = libtuj.ce('img');
                td.appendChild(i);
                i.className = 'icon';
                i.src = libtuj.IconURL(pet.icon, 'medium');

                td = libtuj.ce('td');
                td.className = 'name';
                tr.appendChild(td);
                a = libtuj.ce('a');
                td.appendChild(a);
                a.href = tuj.BuildHash({page: 'battlepet', id: pet.id});
                if (pet.npc) {
                    a.rel = 'npc=' + pet.npc + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
                }
                $(a).text('[' + pet['name_' + tuj.locale] + ']');
                lastResultName = AddToSearched(a, pet['name_' + tuj.locale]);

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
                td.className = 'date';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatDate(pet.lastseen));
            }
        }

        if (results == 1) {
            libtuj.Searched.Add(lastResultName);
            tuj.SetParams(lastResult, true);
        }
        else {
            if (results == 0) {
                var h2 = libtuj.ce('h2');
                h2.appendChild(document.createTextNode(tuj.lang.noResults));
                searchPage.append(h2);
            }
            searchPage.show();
        }
    }

    this.load(tuj.params);
};

tuj.page_search = new TUJ_Search();
