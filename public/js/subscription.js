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

        var userName = tuj.LoggedInUserName();
        if (userName) {
            ShowLoggedInAs(userName);
        } else {
            ShowLoginForm();
        }

        ShowSubscriptionMessage(params.id);

        subscriptionPage.show();
    };

    function ShowSubscriptionMessage(id)
    {
        var msg = '';

        if (id && tuj.lang.SubscriptionErrors.hasOwnProperty(id)) {
            msg = tuj.lang.SubscriptionErrors[id];
        }

        $('#subscription-message').empty().html(msg);
    }

    function ShowLoggedInAs(userName)
    {
        var logOut = libtuj.ce('input');
        logOut.type = 'button';
        logOut.value = tuj.lang.logOut;
        $(logOut).click(tuj.LogOut);

        $('#subscription-login').empty().html(libtuj.sprintf(tuj.lang.loggedInAs, userName) + ' ').append(logOut);
    }

    function ShowLoginForm()
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
        i.type = 'button';
        i.value = tuj.lang.logInBattleNet;
        $(i).click(FetchStateAndSubmit.bind(self, formElements, region));
        $(f).append(i);
    }

    function FetchStateAndSubmit(formElements, region)
    {
        $.ajax({
            data: {
                'loginfrom': tuj.BuildHash({}),
                'region': region,
            },
            type: 'POST',
            success: function (d) {
                if (!d.state) {
                    LoginFail();
                    return;
                }
                formElements.clientId.value = d.clientId;
                formElements.state.value = d.state;
                formElements.redirectUri.value = d.redirectUri;
                formElements.submit.disabled = true;
                formElements.form.action = d.authUri.replace('%s', region.toLowerCase());
                formElements.form.submit();
            },
            error: LoginFail,
            url: 'api/subscription.php'
        });
    }

    function LoginFail()
    {
        $('#subscription-login').empty().html('Error setting up login, please try again later.');
    }

    this.load(tuj.params);
}

tuj.page_subscription = new TUJ_Subscription();
