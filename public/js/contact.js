var TUJ_Contact = function () {
    var params;
    var lastResults = [];

    this.load = function (inParams) {
        params = {};
        for (var p in inParams) {
            if (inParams.hasOwnProperty(p)) {
                params[p] = inParams[p];
            }
        }

        var contactPage = $('#contact-page');
        $('#contact-page .form').show();
        $('#contact-page .done').hide();
        $('#contact-page .error').hide();

        contactPage.show();

        $('#page-title').text(tuj.lang.contactTheEditor);
        tuj.SetTitle(tuj.lang.contactTheEditor);
    };

    this.submit = function (f) {
        if (/\brevenue\b/i.test(f.message.value) ||
            /\bpublisher\b/i.test(f.message.value) ||
            /\btraffic\b/i.test(f.message.value) ||
            /\badvertising\b/i.test(f.message.value) ||
            /\bplatform\b/i.test(f.message.value)) {
            alert('We are not interested in any advertising agency offers.');

            return false;
        }

        $('#contact-page .form').hide();
        $('#contact-error-message').text(f.message.value);

        if (f.subject.value != 'Subject') {
            $('#contact-page .error').show();
            return false;
        }

        var d = {
            region: params.region ? tuj.validRegions[params.region] : undefined,
            realm: params.realm ? tuj.realms[params.realm].name : undefined,
            house: params.realm ? tuj.realms[params.realm].house : undefined,
            from: f.from.value,
            message: f.message.value,
            subject: f.subject.value
        };

        $.ajax({
            data: d,
            type: 'POST',
            success: function () {
                $('#contact-page .done').show();
            },
            error: function () {
                $('#contact-page .error').show();
            },
            url: 'api/contact.php'
        });

        return false;
    };

    this.load(tuj.params);
};

tuj.page_contact = new TUJ_Contact();
