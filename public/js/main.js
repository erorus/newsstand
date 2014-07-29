var libtuj = {
    ce: function(tag) { return document.createElement(tag); },
};

var TUJ = function()
{
    function main()
    {
        $('#main').append(libtuj.ce('div')).text('hello world');

    }

    main();
};

var tuj;
$(document).ready(function() {
    tuj = new TUJ();
});