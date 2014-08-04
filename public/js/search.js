
var TUJ_Search = function()
{
    var params;

    this.load = function(inParams)
    {
        params = inParams;

        var searchPage = $('#search-page')[0];
        if (!searchPage)
        {
            searchPage = libtuj.ce();
            searchPage.id = 'search-page';
            searchPage.className = 'page';
            $('#realm-header').after(searchPage);
        }
        $.ajax({
            data: {
                house: tuj.realms[params.realm].house,
                search: params.id
            },
            success: function(dta)
            {
                SearchResult(dta);
            },
            url: 'api/search.php'
        });
    }

    function SearchResult(dta)
    {
        var searchPage = $('#search-page');
        searchPage.empty();

        var h = libtuj.ce();
        h.className = 'header';
        searchPage.append(h);
        $(h).text('Search: '+params.id);

        var gotResult = false;
        var t, tr, td, i, a;

        if (dta.items)
        {
            gotResult = true;

            var lastClass = -1;
            var item;

            for (var x in dta.items)
            {
                if (!dta.items.hasOwnProperty(x))
                    continue;

                item = dta.items[x];

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
                tr.appendChild(td);
                i = libtuj.ce('img');
                td.appendChild(i);
                i.className = 'icon';

                td = libtuj.ce('td');
                tr.appendChild(td);
                a = libtuj.ce('a');
                td.appendChild(a);
                a.href = tuj.BuildHash({page: 'item', id: item.id});
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

        searchPage.show();
    }

    this.load(tuj.params);
}

tuj.page_search = new TUJ_Search();
