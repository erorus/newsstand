
var TUJ_Category = function()
{
    var params;
    var lastResults = [];
    var resultFunctions = {};

    this.load = function(inParams)
    {
        params = {};
        for (var p in inParams)
            if (inParams.hasOwnProperty(p))
                params[p] = inParams[p];

        var qs = {
            house: tuj.realms[params.realm].house,
            id: params.id
        };
        var hash = JSON.stringify(qs);

        for (var x = 0; x < lastResults.length; x++)
            if (lastResults[x].hash == hash)
            {
                CategoryResult(false, lastResults[x].data);
                return;
            }

        var categoryPage = $('#category-page')[0];
        if (!categoryPage)
        {
            categoryPage = libtuj.ce();
            categoryPage.id = 'category-page';
            categoryPage.className = 'page';
            $('#main').append(categoryPage);
        }

        if (!params.id)
        {
            CategoryFrontPage();
            return;
        }

        $('#progress-page').show();

        $.ajax({
            data: qs,
            success: function(d) {
                if (d.captcha)
                    tuj.AskCaptcha(d.captcha);
                else
                    CategoryResult(hash, d);
            },
            complete: function() {
                $('#progress-page').hide();
            },
            url: 'api/category.php'
        });
    }

    function CategoryResult(hash, dta)
    {
        if (hash)
        {
            lastResults.push({hash: hash, data: dta});
            while (lastResults.length > 10)
                lastResults.shift();
        }

        var categoryPage = $('#category-page');
        categoryPage.empty();
        categoryPage.show();

        if (!dta.hasOwnProperty('name'))
        {
            $('#page-title').empty().append(document.createTextNode('Category: ' + params.id));
            tuj.SetTitle('Category: ' + params.id);

            var h2 = libtuj.ce('h2');
            categoryPage.append(h2);
            h2.appendChild(document.createTextNode('Category '+ params.id + ' not found.'));

            return;
        }

        $('#page-title').empty().append(document.createTextNode('Category: ' + dta.name));
        tuj.SetTitle('Category: ' + dta.name);

        if (!dta.hasOwnProperty('results'))
            return;

        categoryPage.append(libtuj.Ads.Add('8323200718'));

        var f, resultCount = 0;
        for (var x = 0; f = dta.results[x]; x++)
            if (resultFunctions.hasOwnProperty(f.name))
            {
                d = libtuj.ce();
                d.className = 'category-'+ f.name.toLowerCase();
                categoryPage.append(d);
                resultCount += resultFunctions[f.name](f.data, d) ? 1 : 0;

                if (resultCount == 5) {
                    categoryPage.append(libtuj.Ads.Add('2276667118'));
                }
            }

        libtuj.Ads.Show();
    }

    function CategoryFrontPage()
    {
        var categoryPage = $('#category-page');
        categoryPage.empty();
        categoryPage.show();

        $('#page-title').empty().append(document.createTextNode('Categories'));
        tuj.SetTitle('Categories');
    }

    resultFunctions.ItemList = function(data, dest)
    {
        var item, x, t, td, th, tr, a;

        if (!data.items.length)
            return false;

        if (!data.hiddenCols)
            data.hiddenCols = {};

        if (!data.visibleCols)
            data.visibleCols = {};

        if (!data['sort'])
            data['sort'] = '';

        var titleColSpan = 4;
        var titleTd;

        t = libtuj.ce('table');
        t.className = 'category category-items';
        dest.appendChild(t);

        if (data.hasOwnProperty('name'))
        {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('th');
            td.className = 'title';
            tr.appendChild(td);
            titleTd = td;
            $(td).text(data.name);
        }

        tr = libtuj.ce('tr');
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

        if (data.visibleCols.bid)
        {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text('Bid');
            titleColSpan++;
        }

        if (!data.hiddenCols.price)
        {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text('Current');
            titleColSpan++;
        }

        if (!data.hiddenCols.avgprice)
        {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text('Mean')
            titleColSpan++;
        }

        if (data.visibleCols.globalmedian)
        {
            td = libtuj.ce('th');
            td.className = 'price';
            tr.appendChild(td);
            $(td).text('Global Median');
            titleColSpan++;
        }

        if (!data.hiddenCols.lastseen)
        {
            td = libtuj.ce('th');
            td.className = 'date';
            tr.appendChild(td);
            $(td).text('Last Seen');
            titleColSpan++;
        }

        titleTd.colSpan = titleColSpan;

        switch (data['sort']) {
            case 'none':
                break;

            case 'lowbids' :
                data.items.sort(function(a,b){
                    return ((a.bid / libtuj.Least([a.globalmedian, a.avgprice])) - (b.bid / libtuj.Least([b.globalmedian, b.avgprice]))) ||
                        (a.bid - b.bid) ||
                        a.name.localeCompare(b.name);
                });
                break;

            case 'globalmedian diff':
                data.items.sort(function(a,b){
                    return ((b.globalmedian - b.price) - (a.globalmedian - a.price)) ||
                        ((a.price ? 0 : 1) - (b.price ? 0 : 1)) ||
                        (a.price - b.price) ||
                        a.name.localeCompare(b.name);
                });
                break;

            case 'lowprice':
                data.items.sort(function(a,b){
                    return ((a.price ? 0 : 1) - (b.price ? 0 : 1)) ||
                        (a.price - b.price) ||
                        a.name.localeCompare(b.name);
                });
                break;

            default:
                data.items.sort(function(a,b){
                    return ((a.price ? 0 : 1) - (b.price ? 0 : 1)) ||
                        (b.price - a.price) ||
                        a.name.localeCompare(b.name);
                });
        }

        for (x = 0; item = data.items[x]; x++)
        {
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

            if (data.visibleCols.bid)
            {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(item.bid));
            }

            if (!data.hiddenCols.price)
            {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(item.price));
            }

            if (!data.hiddenCols.avgprice)
            {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(item.avgprice));
            }

            if (data.visibleCols.globalmedian)
            {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatPrice(item.globalmedian));
            }

            if (!data.hiddenCols.lastseen)
            {
                td = libtuj.ce('td');
                td.className = 'date';
                tr.appendChild(td);
                td.appendChild(libtuj.FormatDate(item.lastseen));
            }
        }

        return true;
    }

    resultFunctions.BattlePetList = function(data, dest)
    {
        var t, td, tr, firstBreed, breed, species, petType, allSpecies, o, x;

        var d = libtuj.ce('div');
        dest.appendChild(d);
        d.appendChild(document.createTextNode('This page is not yet complete, and I could use your feedback!'));
        d.appendChild(libtuj.ce('br'));
        var a = libtuj.ce('a');
        a.style.textDecoration = 'underline';
        d.appendChild(a);
        a.href = 'http://stormspire.net/official-forum-undermine-journal/15269-battle-pets-category-page-feedback.html';
        $(a).text('Please check out this post and let me know what would make this page better.');
        d.style.marginBottom = '2em';
        d.style.textAlign='center';

        for (petType in tujConstants.petTypes) {
            if (!tujConstants.petTypes.hasOwnProperty(petType))
                continue;

            if (!data.hasOwnProperty(petType))
                continue;

            t = libtuj.ce('table');
            dest.appendChild(t);
            t.className = 'category category-battlepets';

            tr = libtuj.ce('tr');
            t.appendChild(tr);
            td = libtuj.ce('th');
            tr.appendChild(td);
            td.colSpan=12;
            $(td).text(tujConstants.petTypes[petType]);

            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('td');
            tr.appendChild(td);
            td.colSpan=2;
            td.className = 'name';
            $(td).text('Name');

            for (breed = 3; breed <= 12; breed++) {
                td = libtuj.ce('td');
                td.className = 'price';
                tr.appendChild(td);
                $(td).text(tujConstants.breeds[breed]);
            }

            allSpecies = [];

            for (species in data[petType]) {
                if (!data[petType].hasOwnProperty(species))
                    continue;

                o = {id: species, breeds: {}};

                firstBreed = false;
                for (breed in data[petType][species]) {
                    if (!data[petType][species].hasOwnProperty(breed))
                        continue;
                    if (!firstBreed)
                        firstBreed = breed;
                    o.breeds[breed] = data[petType][species][breed];
                }

                if (!firstBreed)
                    continue;

                o.name = data[petType][species][firstBreed].name;
                o.icon = data[petType][species][firstBreed].icon;
                o.npc = data[petType][species][firstBreed].npc;

                allSpecies.push(o);
            }

            allSpecies.sort(function(a,b) {
                var firstBreedSeen = 0;
                for (var x = 3; x <= 12; x++) {
                    if (a.breeds.hasOwnProperty(x)) {
                        if (b.breeds.hasOwnProperty(x)) {
                            if (!firstBreedSeen)
                                firstBreedSeen = x;
                            continue;
                        }
                        return -1;
                    } else if (b.breeds.hasOwnProperty(x)) {
                        return 1;
                    }
                }
                return (b.breeds[firstBreedSeen].price - a.breeds[firstBreedSeen].price) || a.name.localeCompare(b.name);
            });

            for (x = 0; x < allSpecies.length; x++) {

                tr = libtuj.ce('tr');
                t.appendChild(tr);

                td = libtuj.ce('td');
                td.className = 'icon';
                tr.appendChild(td);
                i = libtuj.ce('img');
                td.appendChild(i);
                i.className = 'icon';
                i.src = 'icon/medium/' + allSpecies[x].icon + '.jpg';

                td = libtuj.ce('td');
                td.className = 'name';
                tr.appendChild(td);
                a = libtuj.ce('a');
                td.appendChild(a);
                a.href = tuj.BuildHash({page: 'battlepet', id: allSpecies[x].id});
                a.rel = 'npc=' + allSpecies[x].npc;
                $(a).text('[' + allSpecies[x].name + ']');

                for (breed = 3; breed <= 12; breed++) {
                    td = libtuj.ce('td');
                    td.className = 'price';
                    tr.appendChild(td);
                    if (!allSpecies[x].breeds.hasOwnProperty(breed))
                        continue;

                    var a = libtuj.ce('a');
                    td.appendChild(a);
                    a.href = tuj.BuildHash({page: 'battlepet', id: '' + allSpecies[x].id + '.' + breed});
                    a.rel = 'npc=' + allSpecies[x].npc;
                    $(a).text(Math.ceil(allSpecies[x].breeds[breed].price/10000));
                }

            }

        }



    }

    this.load(tuj.params);
}

tuj.page_category = new TUJ_Category();
