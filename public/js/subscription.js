var TUJ_Subscription = function ()
{
    var params;
    var formElements;
    var self;

    this.load = function (inParams)
    {
        self = this;
        params = {};
        for (var p in inParams) {
            if (inParams.hasOwnProperty(p)) {
                params[p] = inParams[p];
            }
        }

        var subscriptionPage = $('#subscription-page');

        $('#page-title').text(tuj.lang.subscription);
        tuj.SetTitle(tuj.lang.subscription);

        this.ShowLoginForm();

        subscriptionPage.show();
    };

    this.ShowLoginForm = function()
    {
        formElements = {};

        var region = tuj.validRegions[0];
        if (params.region != undefined) {
            region = tuj.validRegions[params.region];
        }

        var i, f = formElements.form = libtuj.ce('form');
        f.method = 'GET';
        $('#subscription-login').empty().append(f);

        i = formElements.clientId = libtuj.ce('input');
        i.type = 'hidden';
        i.name = 'client_id';
        $(f).append(i);

        i = formElements.scope = libtuj.ce('input');
        i.type = 'hidden';
        i.name = 'scope';
        $(f).append(i);

        i = formElements.state = libtuj.ce('input');
        i.type = 'hidden';
        i.name = 'state';
        $(f).append(i);

        i = formElements.redirectUri = libtuj.ce('input');
        i.type = 'hidden';
        i.name = 'redirect_uri';
        $(f).append(i);

        i = formElements.responseType = libtuj.ce('input');
        i.type = 'hidden';
        i.name = 'response_type';
        i.value = 'code';
        $(f).append(i);

        i = formElements.submit = libtuj.ce('input');
        i.type = 'submit';
        i.value = tuj.lang.logInBattleNet;
        i.disabled = true;
        $(f).append(i);

        $.ajax({
            data: {
                'loginfrom': tuj.BuildHash({}),
                'region': region,
            },
            type: 'POST',
            success: function (d) {
                if (!d.state) {
                    self.LoginFail();
                    return;
                }
                formElements.form.action = d.authUri.replace('%s', region.toLowerCase());
                formElements.clientId.value = d.clientId;
                formElements.state.value = d.state;
                formElements.redirectUri.value = d.redirectUri;
                formElements.submit.disabled = false;
            },
            error: self.LoginFail,
            url: 'api/subscription.php'
        });
    };

    this.LoginFail = function()
    {
        $('#subscription-login').empty().html('Error setting up login, please try again later.');
    };

    this.load(tuj.params);
}

tuj.page_subscription = new TUJ_Subscription();
