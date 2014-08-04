
var TUJ_Item = function()
{
    var params;

    this.load = function(inParams)
    {
        params = inParams;

        var itemPage = $('#item-page')[0];
        if (!itemPage)
        {
            itemPage = libtuj.ce();
            itemPage.id = 'item-page';
            itemPage.className = 'page';
            $('#realm-header').after(itemPage);
        }
        $.ajax({
            data: {
                house: tuj.realms[params.realm].house * tuj.validFactions[params.faction],
                item: params.id
            },
            success: ItemResult,
            url: 'api/item.php'
        });
    }

    function ItemResult(dta)
    {
        var itemPage = $('#item-page');
        itemPage.empty();

        var h = libtuj.ce();
        h.className = 'header';
        itemPage.append(h);
        $(h).text('Item: '+dta.stats.name);

        itemPage.show();
    }

    this.load(tuj.params);
}

tuj.page_item = new TUJ_Item();
