var TUJ_Subscription = function ()
{
    var params;

    this.load = function (inParams)
    {
        params = {};
        for (var p in inParams) {
            if (inParams.hasOwnProperty(p)) {
                params[p] = inParams[p];
            }
        }

        var subscriptionPage = $('#subscription-page');

        subscriptionPage.show();

        $('#page-title').text(tuj.lang.subscription);
        tuj.SetTitle(tuj.lang.subscription);
    }

    this.load(tuj.params);
}

tuj.page_subscription = new TUJ_Subscription();
