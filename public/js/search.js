
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
        var t, tr, td, i, a;

        if (dta.items)
        {
            var lastClass = -1;
            var item;

            for (var x in dta.items)
            {
                if (!dta.items.hasOwnProperty(x))
                    continue;

                item = dta.items[x];
                lastResult = {page: 'item', id: item.id};
                results++;

                if (lastClass != item.classid)
                {
                    lastClass = item.classid;

                    t = libtuj.ce('table');
                    t.className = 'search-items';
                    searchPage.append(t);

                    tr = libtuj.ce('tr');
                    t.appendChild(tr);

                    td = libtuj.ce('th');
                    td.className = 'name';
                    tr.appendChild(td);
                    td.colSpan=2;
                    $(td).text('Name');

                    td = libtuj.ce('th');
                    td.className = 'price';
                    tr.appendChild(td);
                    $(td).text('Price');

                    td = libtuj.ce('th');
                    td.className = 'quantity';
                    tr.appendChild(td);
                    $(td).text('Quantity');

                    td = libtuj.ce('th');
                    td.className = 'date';
                    tr.appendChild(td);
                    $(td).text('Last Seen');
                }

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
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(item.price));

                td = libtuj.ce('td');
                td.className = 'quantity';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatQuantity(item.quantity));

                td = libtuj.ce('td');
                td.className = 'date';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatDate(item.lastseen));
            }
        }

        if (dta.sellers)
        {
            var seller;

            t = libtuj.ce('table');
            t.className = 'search-sellers';
            searchPage.append(t);

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

            for (var x in dta.sellers)
            {
                if (!dta.sellers.hasOwnProperty(x))
                    continue;

                results++;
                lastResult = {page: 'seller', id: seller.name, realm: seller.realm};
                seller = dta.sellers[x];

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

        if (results == 1)
            tuj.SetParams(lastResult);
        else
            searchPage.show();
    }

    this.load(tuj.params);
}

tuj.page_search = new TUJ_Search();
