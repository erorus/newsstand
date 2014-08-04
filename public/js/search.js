
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
        $(searchPage).show();
        $(searchPage).text('hi search page: '+ params.id);
    }

    this.load(tuj.GetParams());
}

tuj.page_search = new TUJ_Search();
