var TUJ_Subscription = function ()
{
    var params;
    var formElements;
    var self;

    var subData;

    var subPeriods = [2, 25, 55, 115, 175, 235, 355, 475, 715, 955, 1075, 1195, 1435];

    this.load = function (inParams)
    {
        self = this;
        params = {};
        for (var p in inParams) {
            if (inParams.hasOwnProperty(p)) {
                params[p] = inParams[p];
            }
        }

        $('#page-title').text(tuj.lang.subscription);
        tuj.SetTitle(tuj.lang.subscription);

        var userName = tuj.LoggedInUserName();
        if (userName) {
            ShowLoggedInAs(userName);
            $('#subscription-description').hide();
            $('#subscription-settings').empty().hide();
            FetchSubscriptionSettings();
        } else {
            ShowLoginForm();
            $('#subscription-description').show();
            $('#subscription-settings').empty().hide();
        }

        ShowSubscriptionMessage(params.id);

        $('#subscription-page').show();
    };

    function ShowSubscriptionMessage(id)
    {
        if (id && tuj.lang.SubscriptionErrors.hasOwnProperty(id)) {
            $('#subscription-message').empty().html(tuj.lang.SubscriptionErrors[id]).show();
        } else {
            $('#subscription-message').empty().hide();
        }
    }

    function ShowSubscriptionSettings(dta)
    {
        subData = dta;

        var settingsParent = $('#subscription-settings');
        settingsParent.empty().hide();

        var settingsMessages = libtuj.ce('div');
        settingsMessages.id = 'subscription-messages';
        settingsParent.append(settingsMessages);
        ShowMessages(settingsMessages);

        var settingsEmail = libtuj.ce('div');
        settingsEmail.id = 'subscription-email';
        settingsParent.append(settingsEmail);
        ShowEmail(settingsEmail);

        var settingsPeriod = libtuj.ce('div');
        settingsPeriod.id = 'subscription-period';
        settingsParent.append(settingsPeriod);
        ShowNotificationPeriod(settingsPeriod);

        var settingsPaid = libtuj.ce('div');
        settingsPaid.id = 'subscription-paidstatus';
        settingsParent.append(settingsPaid);
        ShowPaidSettings(settingsPaid);

        var settingsWatches = libtuj.ce('div');
        settingsWatches.id = 'subscription-watches';
        settingsParent.append(settingsWatches);
        ShowWatches(settingsWatches);

        settingsParent.show();
    }

    function SetEmailAddress()
    {
        var addressBox = document.getElementById('subscription-email-address');
        if (addressBox.value == addressBox.defaultValue) {
            return;
        }

        tuj.SendCSRFProtectedRequest({
            data: {'emailaddress': addressBox.value},
            success: function(dta) {
                if (dta.hasOwnProperty('address')) {
                    subData.email.address = addressBox.value = dta.address;
                }
                addressBox.defaultValue = addressBox.value;

                if (dta.hasOwnProperty('status')) {
                    if (dta.status == 'verify') {
                        $('#subscription-email').addClass('verify');
                    } else {
                        $('#subscription-email').removeClass('verify');
                    }
                    if (tuj.lang.EmailStatus.hasOwnProperty(dta.status)) {
                        alert(tuj.lang.EmailStatus[dta.status]);
                    }
                }
            },
            error: function() {
                addressBox.value = addressBox.defaultValue;
                alert(tuj.lang.EmailStatus.unknown);
            }
        });
    }

    function VerifyEmailAddress()
    {
        var addressBox = document.getElementById('subscription-email-address');
        var verifyBox = document.getElementById('subscription-email-verification-code');
        if (verifyBox.value == '') {
            return;
        }

        tuj.SendCSRFProtectedRequest({
            data: {'verifyemail': verifyBox.value},
            success: function(dta) {
                verifyBox.value = '';
                if (dta.hasOwnProperty('address')) {
                    subData.email.address = addressBox.value = dta.address;
                }
                addressBox.defaultValue = addressBox.value;

                if (dta.hasOwnProperty('status')) {
                    if (dta.status == 'verify') {
                        $('#subscription-email').addClass('verify');
                    } else {
                        $('#subscription-email').removeClass('verify');
                    }
                    if (tuj.lang.EmailStatus.hasOwnProperty(dta.status)) {
                        alert(tuj.lang.EmailStatus[dta.status]);
                    }
                }
            },
            error: function() {
                verifyBox.value = '';
                alert(tuj.lang.EmailStatus.unknown);
            }
        });
    }

    function ShowEmail(container)
    {
        var settings = subData.email;

        var h = libtuj.ce('h3');
        container.appendChild(h);
        $(h).text(tuj.lang.contactInformation);

        var d = libtuj.ce('div');
        d.className = 'instruction';
        container.appendChild(d);
        $(d).html(tuj.lang.setAddressInstruction);

        var f = libtuj.ce('form');
        container.appendChild(f);

        var s = libtuj.ce('span');
        $(s).text(tuj.lang.emailAddress + ': ');
        f.appendChild(s);

        var i = libtuj.ce('input');
        i.type = 'email';
        i.id = 'subscription-email-address';
        i.value = i.defaultValue = (settings && settings.address) ? settings.address : '';
        f.appendChild(i);

        var i = libtuj.ce('input');
        i.type = 'button';
        i.id = 'subscription-email-set-button';
        i.value = tuj.lang.setAddress;
        $(i).on('click', SetEmailAddress);
        f.appendChild(i);

        var d = libtuj.ce('div');
        d.id = 'subscription-email-verification';
        f.appendChild(d);

        var s = libtuj.ce('span');
        $(s).text(tuj.lang.emailVerification + ': ');
        d.appendChild(s);

        var i = libtuj.ce('input');
        i.type = 'number';
        i.id = 'subscription-email-verification-code';
        i.value = '';
        i.maxLength = '16';
        d.appendChild(i);

        var i = libtuj.ce('input');
        i.type = 'button';
        i.id = 'subscription-email-verify-button';
        i.value = tuj.lang.verifyAddress;
        $(i).on('click', VerifyEmailAddress);
        d.appendChild(i);

        if (settings.needVerification) {
            $('#subscription-email').addClass('verify');
        } else {
            $('#subscription-email').removeClass('verify');
        }
    }

    function ShowNotificationPeriod(dest)
    {
        var h = libtuj.ce('h3');
        dest.appendChild(h);
        $(h).text(tuj.lang.notificationPeriod);

        var d = libtuj.ce('div');
        d.className = 'instruction';
        dest.appendChild(d);
        $(d).text(tuj.lang.notificationPeriodDesc);

        var s = libtuj.ce('span');
        dest.appendChild(s);
        $(s).text(tuj.lang.notificationPeriod + ': ');

        var o, label, found, disabled, sel = libtuj.ce('select');
        for (var x = 0; x < subPeriods.length; x++) {
            disabled = false;
            o = libtuj.ce('option');
            o.value = subPeriods[x];
            label = x == 0 ? tuj.lang.asap : libtuj.FormatDate(-1 * 60 * (subPeriods[x] + 5), true, 'hour', true);
            if (subPeriods[x] < subData.reports.minperiod || subPeriods[x] > subData.reports.maxperiod) {
                o.disabled = disabled = true;
                label = tuj.lang.paidOnly + ': ' + label;
            }
            o.label = label;
            o.appendChild(document.createTextNode(label));
            sel.appendChild(o);
            if (subPeriods[x] == subData.reports.period) {
                found = true;
                if (!disabled) {
                    sel.selectedIndex = sel.options.length - 1;
                }
            }
        }
        if (!found) {
            var o = libtuj.ce('option');
            o.value = subData.reports.period;
            label = libtuj.FormatDate(-1 * 60 * (subData.reports.period + 5), true, 'hour', true);
            o.label = label;
            o.appendChild(document.createTextNode(label));
            sel.appendChild(o);
            sel.selectedIndex = sel.options.length - 1;
        }
        $(sel).on('change', SetNotificationPeriod.bind(this, sel));
        dest.appendChild(sel);
    }

    function SetNotificationPeriod(sel)
    {
        sel.disabled = true;
        tuj.SendCSRFProtectedRequest({
            data: {'setperiod': sel.options[sel.selectedIndex].value},
            success: function(dta) {
                for (var x = 0; x < sel.options.length; x++) {
                    if (sel.options[x].value == dta.period) {
                        sel.selectedIndex = x;
                        break;
                    }
                }
                sel.disabled = false;
            },
            error: function() {
                sel.disabled = false;
            }
        });
    }

    function ShowPaidSettings(dest)
    {
        var pageTitle = subData.paid.until ? tuj.lang.paidSubscription : tuj.lang.freeSubscription;
        $('#page-title').text(pageTitle);
        tuj.SetTitle(pageTitle);

        var h = libtuj.ce('h3');
        dest.appendChild(h);
        $(h).text(tuj.lang.paidSubscription);

        var d = libtuj.ce('div');
        d.className = 'instruction';
        dest.appendChild(d);
        if (subData.paid.until) {
            $(d).text(libtuj.sprintf(tuj.lang.paidExpires, libtuj.FormatDate(subData.paid.until, true)));
        } else {
            $(d).text(tuj.lang.freeSubscriptionAccount);
        }

        if (subData.paid.accept) {
            var d = libtuj.ce('div');
            d.className = 'instruction';
            dest.appendChild(d);
            $(d).text(libtuj.sprintf(tuj.lang.clickToExtendSubscription, subData.paid.accept.days, subData.paid.accept.price));

            var f = libtuj.ce('form');
            f.method = 'post';
            f.action = 'https://www.paypal.com/cgi-bin/webscr';
            f.target = '_top';

            var i = libtuj.ce('input');
            i.type = 'hidden';
            i.name = 'cmd';
            i.value = '_s-xclick';
            f.appendChild(i);

            i = libtuj.ce('input');
            i.type = 'hidden';
            i.name = 'hosted_button_id';
            i.value = subData.paid.accept.button;
            f.appendChild(i);

            i = libtuj.ce('input');
            i.type = 'hidden';
            i.name = 'custom';
            i.value = subData.paid.accept.custom;
            f.appendChild(i);

            i = libtuj.ce('input');
            i.type = 'image';
            i.src = 'images/btn_buynowCC_LG.gif';
            i.border = '0';
            i.name = 'submit';
            i.alt = 'PayPal - The safer, easier way to pay online!';
            f.appendChild(i);

            dest.appendChild(f);
        }
    }

    function ShowWatches(dest)
    {
        var w = subData.watches;

        var hasWatches;
        var byHouse = {};
        var houseKey, classKey, houseKeys = [];
        for (var k in w) {
            if (!w.hasOwnProperty(k)) {
                continue;
            }
            if (w[k].item) {
                classKey = 'i' + w[k]['class'];
            } else if (w[k].species) {
                classKey = 'p' + w[k]['type'];
            } else {
                continue;
            }

            hasWatches = true;

            houseKey = w[k].house || w[k].region;
            if (!byHouse.hasOwnProperty(houseKey)) {
                byHouse[houseKey] = {};
                houseKeys.push(houseKey);
            }

            if (!byHouse[houseKey].hasOwnProperty(classKey)) {
                byHouse[houseKey][classKey] = [];
            }
            byHouse[houseKey][classKey].push(w[k]);
        }
        if (!hasWatches) {
            return;
        }

        houseKeys.sort(function(a,b) {
            if (isNaN(a)) {
                if (isNaN(b)) {
                    return a.localeCompare(b);
                } else {
                    return 1;
                }
            } else {
                if (isNaN(b)) {
                    return -1;
                } else {
                    return parseInt(a,10) - parseInt(b,10);
                }
            }
        });

        var petOrder = [];
        for (var pt in tuj.lang.petTypes) {
            if (!tuj.lang.petTypes.hasOwnProperty(pt)) {
                continue;
            }
            petOrder.push(pt);
        }
        petOrder.sort(function(a,b) {
            return tuj.lang.petTypes[a].localeCompare(tuj.lang.petTypes[b]);
        });

        for (var hx = 0; houseKey = houseKeys[hx]; hx++) {
            var h = libtuj.ce('h3');
            dest.appendChild(h);
            $(h).text(tuj.lang.marketNotifications + ' - ' + (isNaN(houseKey) ? tuj.lang['realms' + houseKey] : libtuj.GetRealmsForHouse(houseKey, false, true)));

            for (var x = 0, classId; classId = tujConstants.itemClassOrder[x]; x++) {
                classKey = 'i' + classId;
                if (!byHouse[houseKey].hasOwnProperty(classKey)) {
                    continue;
                }

                var name = tuj.lang.itemClasses.hasOwnProperty(classId) ? tuj.lang.itemClasses[classId] : ('(Item Class ' + classId + ')');

                dest.appendChild(BuildWatchesTable(name, byHouse[houseKey][classKey]));
            }
            for (x = 0; classId = petOrder[x]; x++) {
                classKey = 'p' + classId;
                if (!byHouse[houseKey].hasOwnProperty(classKey)) {
                    continue;
                }

                var name = tuj.lang.petTypes.hasOwnProperty(classId) ? tuj.lang.petTypes[classId] : ('(Pet Type ' + classId + ')');

                dest.appendChild(BuildWatchesTable(name, byHouse[houseKey][classKey]));
            }
        }
    }

    function BuildWatchesTable(name, watches)
    {
        var item, x, regionId, realmId, t, td, th, tr, a, btn, h;

        if (watches.length == 0) {
            return null;
        }

        t = libtuj.ce('table');
        t.className = 'category category-items';

        // header
        tr = libtuj.ce('tr');
        t.appendChild(tr);

        td = libtuj.ce('th');
        td.className = 'title';
        td.colSpan = 6;
        tr.appendChild(td);

        $(td).text(name);

        watches.sort(function (a, b) {
            var aPrice = (a.price == null ? -1 : a.price);
            var bPrice = (b.price == null ? -1 : b.price);
            var aQty = (a.quantity == null ? -1 : a.quantity);
            var bQty = (b.quantity == null ? -1 : b.quantity);

            return a['name_' + tuj.locale].localeCompare(b['name_' + tuj.locale]) ||
                (aPrice - bPrice) ||
                (aQty - bQty);
        });

        var hashRegion, hashRealm;

        for (regionId in tuj.validRegions) {
            if (!tuj.validRegions.hasOwnProperty(regionId)) {
                continue;
            }
            if (watches[0].house) {
                for (realmId in tuj.allRealms[regionId]) {
                    if (!tuj.allRealms[regionId].hasOwnProperty(realmId)) {
                        continue;
                    }
                    if (tuj.allRealms[regionId][realmId].house == watches[0].house) {
                        hashRegion = regionId;
                        hashRealm = realmId;
                        break;
                    }
                }
            } else {
                if (watches[0].region == tuj.validRegions[regionId]) {
                    hashRegion = regionId;
                    break;
                }
            }
        }
        if (hashRealm && params.realm && (tuj.allRealms[hashRegion][hashRealm].house == tuj.allRealms[params.region][params.realm].house)) {
            hashRealm = params.realm;
        }

        for (x = 0; item = watches[x]; x++) {
            tr = libtuj.ce('tr');
            t.appendChild(tr);

            td = libtuj.ce('td');
            td.className = 'icon';
            tr.appendChild(td);
            i = libtuj.ce('img');
            td.appendChild(i);
            i.className = 'icon';
            i.src = libtuj.IconURL(item.icon, 'medium');

            td = libtuj.ce('td');
            td.className = 'name';
            tr.appendChild(td);
            a = libtuj.ce('a');
            td.appendChild(a);
            if (item.item) {
                a.rel = 'item=' + item.item + (item.bonusurl ? '&bonus=' + item.bonusurl : (item.basebonus ? '&bonus=' + item.basebonus : '')) + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
                h = {page: 'item', id: item.item + (item.bonusurl ? ('.'+item.bonusurl).replace(':','.') : '')};
            } else if (item.species) {
                a.rel = 'npc=' + item.npc + (tuj.locale != 'enus' ? '&domain=' + tuj.lang.wowheadDomain : '');
                h = {page: 'battlepet', id: item.species + (item.breed ? ('.'+item.breed) : '')};
            }
            if (hashRealm) {
                h.region = hashRegion;
                h.realm = hashRealm;
            } else if (hashRegion != params.region) {
                h.region = hashRegion;
                h.realm = undefined;
            }
            a.href = tuj.BuildHash(h);
            $(a).text('[' + item['name_' + tuj.locale]
                + (item['bonusname_' + tuj.locale] ? ' ' + item['bonusname_' + tuj.locale].substr(0, item['bonusname_' + tuj.locale].indexOf('|') >= 0 ? item['bonusname_' + tuj.locale].indexOf('|') : item['bonusname_' + tuj.locale].length) : '')
                + ']'
                + (item['bonustag_' + tuj.locale] ? ' ' : '')
                + (item.hasOwnProperty('breed') ? (tuj.lang.breedsLookup.hasOwnProperty(item.breed) ? ' ' + tuj.lang.breedsLookup[item.breed] : '') : '')
            );
            if (item['bonustag_' + tuj.locale]) {
                var tagspan = libtuj.ce('span');
                tagspan.className = 'nowrap';
                $(tagspan).text(item['bonustag_' + tuj.locale]);
                a.appendChild(tagspan);
            }

            td = libtuj.ce('td');
            td.className = 'quantity';
            tr.appendChild(td);
            if (item.price != null) {
                if (item.quantity != null) {
                    td.appendChild(document.createTextNode(tuj.lang.priceToBuy + ' ' + libtuj.FormatQuantity(item.quantity, true)));
                } else {
                    td.appendChild(document.createTextNode(tuj.lang.marketPrice));
                }
            } else {
                td.appendChild(document.createTextNode(tuj.lang.availableQuantity));
            }

            td = libtuj.ce('td');
            td.style.textAlign = 'right';
            tr.appendChild(td);
            td.appendChild(document.createTextNode(tuj.lang[item.direction.toLowerCase()]));

            td = libtuj.ce('td');
            td.className = 'quantity';
            tr.appendChild(td);
            if (item.price != null) {
                td.appendChild(libtuj.FormatPrice(item.price));
            } else {
                td.appendChild(libtuj.FormatQuantity(item.quantity));
            }

            td = libtuj.ce('td');
            td.className = 'quantity';
            tr.appendChild(td);
            btn = libtuj.ce('input');
            btn.type = 'button';
            btn.value = tuj.lang.delete;
            $(btn).on('click', DeleteWatch.bind(self, item, tr));
            td.appendChild(btn);
        }

        return t;
    }

    function DeleteWatch(watch, tr) {
        $(tr).find('input').prop('disabled', true);
        tuj.SendCSRFProtectedRequest({
            data: {'deletewatch': watch.seq},
            success: function() {
                tr.parentNode.removeChild(tr);
            },
            error: function() {
                $(tr).find('input').prop('disabled', false);
            }
        });
    }

    function ShowMessages(container)
    {
        var listDiv = libtuj.ce('div');
        listDiv.id = 'subscription-messages-list';
        container.appendChild(listDiv);

        var messageDiv = libtuj.ce('div');
        messageDiv.id = 'subscription-messages-window';
        container.appendChild(messageDiv);

        subData.messages.sort(function(a,b){
            return b.seq - a.seq;
        });

        var msgDiv, s;
        for (var x = 0, msg; msg = subData.messages[x]; x++) {
            msg.div = msgDiv = libtuj.ce('div');
            msgDiv.className = 'messages-list-item';
            listDiv.appendChild(msgDiv);

            s = libtuj.ce('span');
            s.className = 'message-subject';
            $(s).text(msg.subject);
            msgDiv.appendChild(s);

            s = libtuj.FormatDate(msg.created);
            s.className += ' message-date';
            msgDiv.appendChild(s);

            $(msgDiv).data('seq', msg.seq).click(ShowMessage.bind(self, x));
        }
        if (subData.messages.length) {
            ShowMessage(0);
        }
    }

    function ShowMessage(idx)
    {
        $('#subscription-messages-list .messages-list-item.selected').removeClass('selected');

        var msg = subData.messages[idx];
        $(msg.div).addClass('selected');

        var d = libtuj.ce('div');
        d.className = 'message-subject';
        $(d).text(msg.subject);

        var s = libtuj.FormatDate(msg.created);
        s.className += ' message-date';
        d.appendChild(s);

        $('#subscription-messages-window').empty().append(d).append('<div class="message-text"></div>');

        if (msg.hasOwnProperty('message')) {
            $('#subscription-messages-window .message-text').html(msg.message);
            return;
        }

        tuj.SendCSRFProtectedRequest({
            data: {'getmessage': msg.seq},
            success: function(dta) {
                msg.message = dta.message;
                ShowMessage(idx);
            },
            error: function() {
                $('#subscription-messages-window .message-text').html(tuj.lang.subMessageFetchError);
            }
        });
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
        $(i).click(FetchStateAndSubmit.bind(self, formElements, region, tuj.locale));
        $(f).append(i);
    }

    function FetchStateAndSubmit(formElements, region, locale)
    {
        $.ajax({
            data: {
                'loginfrom': tuj.BuildHash({}),
                'region': region,
                'locale': locale
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

    function FetchSubscriptionSettings()
    {
        subData = {};
        tuj.SendCSRFProtectedRequest({
            data: {'settings': 1},
            success: ShowSubscriptionSettings,
            error: SettingsFail
        });
    }

    function LoginFail()
    {
        $('#subscription-login').empty().html(tuj.lang.SubscriptionErrors.statesetup);
    }

    function SettingsFail()
    {
        $('#subscription-settings').empty().html(tuj.lang.SubscriptionErrors.nodata);
    }

    this.load(tuj.params);
}

tuj.page_subscription = new TUJ_Subscription();
