
var TUJ_Contact = function()
{
    var params;
    var lastResults = [];

    this.load = function(inParams)
    {
        params = {};
        for (var p in inParams)
            if (inParams.hasOwnProperty(p))
                params[p] = inParams[p];

        var contactPage = $('#contact-page');
        contactPage.show();

        $('#page-title').text('Contact The Editor');
        tuj.SetTitle('Contact The Editor');
    }

    this.load(tuj.params);
}

tuj.page_contact = new TUJ_Contact();
