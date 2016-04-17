var TUJ_Policy = function ()
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

        $('#page-title').text(tuj.lang.termsAndPolicies);
        tuj.SetTitle(tuj.lang.termsAndPolicies);

        var policyPage = $('#policy-page');
        switch (params.id) {
            case 'terms':
            case 'privacy':
                policyPage.children('.policy-section').hide();
                policyPage.children('hr').hide();
                policyPage.children('#policy-' + params.id + '.policy-section').show();
                break;
            default:
                policyPage.children('.policy-section').show();
                policyPage.children('hr').show();
        }

        policyPage.show();
    }

    this.load(tuj.params);
}

tuj.page_policy = new TUJ_Policy();
