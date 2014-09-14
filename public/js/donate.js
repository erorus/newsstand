
var TUJ_Donate = function()
{
    var params;
    var lastResults = [];

    this.load = function(inParams)
    {
        params = {};
        for (var p in inParams)
            if (inParams.hasOwnProperty(p))
                params[p] = inParams[p];

        var donatePage = $('#donate-page');
        donatePage.show();

        $('#page-title').text('Donate to The Undermine Journal');
        tuj.SetTitle('Donate');

        if (params.id && params.id == 'thanks')
            $('#donate-thanks').show();
    }

    this.load(tuj.params);
}

tuj.page_donate = new TUJ_Donate();
