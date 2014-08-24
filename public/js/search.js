
var TUJ_Search = function()
{
    var params;
    var lastResults = [];

    this.load = function(inParams)
    {
        params = {};
        for (var p in inParams)
            if (inParams.hasOwnProperty(p))
                params[p] = inParams[p];

        var qs = {
            house: tuj.realms[params.realm].house,
            search: params.id
        };
        var hash = JSON.stringify(qs);

        for (var x = 0; x < lastResults.length; x++)
            if (lastResults[x].hash == hash)
            {
                SearchResult(false, lastResults[x].data);
                return;
            }

        var searchPage = $('#search-page')[0];
        if (!searchPage)
        {
            searchPage = libtuj.ce();
            searchPage.id = 'search-page';
            searchPage.className = 'page';
            $('#main').append(searchPage);
        }
        $.ajax({
            data: qs,
            success: function(d) { SearchResult(hash, d); },
            url: 'api/search.php'
        });
    }

    function SearchResult(hash, dta)
    {
        if (hash)
        {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10)
                lastResults.shift();
        }

        var searchPage = $('#search-page');
        searchPage.empty();

        $('#page-title').text('Search: '+params.id);

        var results = 0;
        var lastResult;
        var t, tr, td, i, a, x;

        if (dta.items)
        {
            dta.items.sort(function(a,b){
                return tujConstants.itemClassOrder[a.classid] - tujConstants.itemClassOrder[b.classid] ||
                    a.name.localeCompare(b.name);
            });

            var lastClass = -1;
            var item;
            var tableHeader, classResults;

            for (x = 0; item = dta.items[x]; x++)
            {
                lastResult = {page: 'item', id: item.id};
                results++;

                if (lastClass != item.classid)
                {
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
                    td.colSpan=6;
                    $(td).text(tujConstants.itemClasses.hasOwnProperty(item.classid) ? tujConstants.itemClasses[item.classid] : ('Class ' + item.classid));

                    tr = libtuj.ce('tr');
                    tableHeader = tr;
                    t.appendChild(tr);

                    td = libtuj.ce('th');
                    td.className = 'name';
                    tr.appendChild(td);
                    td.colSpan=2;
                    $(td).text('Name');

                    td = libtuj.ce('th');
                    td.className = 'quantity';
                    tr.appendChild(td);
                    $(td).text('Avail');

                    td = libtuj.ce('th');
                    td.className = 'price';
                    tr.appendChild(td);
                    $(td).text('Current');

                    td = libtuj.ce('th');
                    td.className = 'price';
                    tr.appendChild(td);
                    $(td).text('Mean');

                    td = libtuj.ce('th');
                    td.className = 'date';
                    tr.appendChild(td);
                    $(td).text('Last Seen');
                }
                else if (++classResults % 30 == 0)
                    t.appendChild(tableHeader.cloneNode(true));

                tr = libtuj.ce('tr');
                t.appendChild(tr);

                td = libtuj.ce('td');
                td.className = 'icon';
                tr.appendChild(td);
                i = libtuj.ce('img');
                td.appendChild(i);
                i.className = 'icon';
                i.src = 'icon/medium/' + item.icon + '.jpg';

                td = libtuj.ce('td');
                td.className = 'name';
                tr.appendChild(td);
                a = libtuj.ce('a');
                td.appendChild(a);
                a.href = tuj.BuildHash({page: 'item', id: item.id});
                a.rel = 'item=' + item.id;
                $(a).text('[' + item.name + ']');

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

        if (dta.sellers)
        {
            dta.sellers.sort(function(a,b){
                return a.name.localeCompare(b.name) || tuj.realms[a.realm].name.localeCompare(tuj.realms[b.realm].name);
            });

            var seller;

            t = libtuj.ce('table');
            t.className = 'search-sellers';
            searchPage.append(t);

            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('th');
            td.className = 'title';
            tr.appendChild(td);
            td.colSpan=5;
            $(td).text('Sellers');

            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('th');
            td.className = 'name';
            tr.appendChild(td);
            $(td).text('Name');

            td = libtuj.ce('th');
            td.className = 'date';
            tr.appendChild(td);
            $(td).text('Last Seen');

            for (x = 0; seller = dta.sellers[x]; x++)
            {
                results++;
                lastResult = {page: 'seller', id: seller.name, realm: seller.realm};

                tr = libtuj.ce('tr');
                t.appendChild(tr);

                td = libtuj.ce('td');
                td.className = 'name';
                tr.appendChild(td);
                a = libtuj.ce('a');
                td.appendChild(a);
                a.href = tuj.BuildHash({page: 'seller', id: seller.name, realm: seller.realm});
                $(a).text(seller.name + ' - ' + tuj.realms[seller.realm].name);

                td = libtuj.ce('td');
                td.className = 'date';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatDate(seller.lastseen));
            }
        }

        if (dta.battlepets)
        {
            dta.battlepets.sort(function(a,b){
                return a.name.localeCompare(b.name);
            });

            var pet;
            var tableHeader, petResults = 0;

            for (x = 0; pet = dta.battlepets[x]; x++)
            {
                lastResult = {page: 'battlepet', id: pet.id};
                results++;

                if (petResults++ == 0)
                {
                    t = libtuj.ce('table');
                    t.className = 'search-pets';
                    searchPage.append(t);

                    tr = libtuj.ce('tr');
                    t.appendChild(tr);

                    td = libtuj.ce('th');
                    td.className = 'title';
                    tr.appendChild(td);
                    td.colSpan=6;
                    $(td).text('Battle Pets');

                    tr = libtuj.ce('tr');
                    tableHeader = tr;
                    t.appendChild(tr);

                    td = libtuj.ce('th');
                    td.className = 'name';
                    tr.appendChild(td);
                    td.colSpan=2;
                    $(td).text('Name');

                    td = libtuj.ce('th');
                    td.className = 'quantity';
                    tr.appendChild(td);
                    $(td).text('Avail');

                    td = libtuj.ce('th');
                    td.className = 'price';
                    tr.appendChild(td);
                    $(td).text('Current');

                    td = libtuj.ce('th');
                    td.className = 'price';
                    tr.appendChild(td);
                    $(td).text('Mean');

                    td = libtuj.ce('th');
                    td.className = 'date';
                    tr.appendChild(td);
                    $(td).text('Last Seen');
                }
                else if (petResults % 30 == 0)
                    t.appendChild(tableHeader.cloneNode(true));

                tr = libtuj.ce('tr');
                t.appendChild(tr);

                td = libtuj.ce('td');
                td.className = 'icon';
                tr.appendChild(td);
                i = libtuj.ce('img');
                td.appendChild(i);
                i.className = 'icon';
                i.src = 'icon/medium/' + pet.icon + '.jpg';

                td = libtuj.ce('td');
                td.className = 'name';
                tr.appendChild(td);
                a = libtuj.ce('a');
                td.appendChild(a);
                a.href = tuj.BuildHash({page: 'battlepet', id: pet.id});
                if (pet.npc)
                    a.rel = 'npc=' + pet.npc;
                $(a).text('[' + pet.name + ']');

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

        if (results == 1)
            tuj.SetParams(lastResult);
        else
            searchPage.show();
    }

    this.load(tuj.params);
}

tuj.page_search = new TUJ_Search();
