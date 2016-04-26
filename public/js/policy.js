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
        policyPage.children('#policy-accept').hide();

        switch (params.id) {
            case 'terms':
            case 'privacy':
                policyPage.children('.policy-section').hide();
                policyPage.children('#policy-blizzard.policy-section').show();
                policyPage.children('#policy-' + params.id + '.policy-section').show();
                break;
            case 'accept':
                SetupAcceptSection(policyPage);
                // fall through
            default:
                policyPage.children('.policy-section').show();
        }

        policyPage.show();
    };

    function SetupAcceptSection(policyPage) {
        if (!tuj.LoggedInUserName()) {
            return;
        }

        var policyAccept = policyPage.children('#policy-accept');
        policyAccept.empty();

        var s = libtuj.ce('span');
        s.appendChild(document.createTextNode(tuj.lang.confirmPolicyAcceptance))
        policyAccept[0].appendChild(s);

        var f = libtuj.ce('form');
        policyAccept[0].appendChild(f);

        var l = libtuj.ce('label');
        f.appendChild(l);
        var i = libtuj.ce('input');
        l.appendChild(i);
        i.type = 'checkbox';
        i.name = 'accept-terms';
        l.appendChild(document.createTextNode(tuj.lang.acceptPolicyCheckbox))

        var l = libtuj.ce('label');
        f.appendChild(l);
        var i = libtuj.ce('input');
        l.appendChild(i);
        i.type = 'checkbox';
        i.name = 'accept-english';
        l.appendChild(document.createTextNode(tuj.lang.acceptSupportEnglish))

        var i = libtuj.ce('input');
        f.appendChild(i);
        i.type = 'submit';
        i.className = 'button';
        i.value = tuj.lang.submit;

        var i = libtuj.ce('input');
        f.appendChild(i);
        i.type = 'button';
        i.className = 'button';
        i.value = tuj.lang.logOut;
        $(i).on('click', tuj.LogOut.bind(tuj, tuj.SetParams.bind(tuj, {'page': 'subscription', 'id': undefined})));

        $(f).on('submit', OnSubmitPolicyAcceptance);

        policyAccept.show();
    }

    function OnSubmitPolicyAcceptance() {
        var valid = true;

        var required = ['accept-terms','accept-english'];

        for (var x = 0; x < required.length; x++) {
            if (!this[required[x]].checked) {
                valid = false;
                $(this[required[x]].parentNode).addClass('highlight');
            } else {
                $(this[required[x]].parentNode).removeClass('highlight');
            }
        }

        if (valid) {
            tuj.UserAcceptsTerms();
        }
        return false;
    }

    this.load(tuj.params);
}

tuj.page_policy = new TUJ_Policy();
