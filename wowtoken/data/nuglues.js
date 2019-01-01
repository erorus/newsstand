/* Requesting this file every 500ms spams my server for no reason. Luckily, browser cache works for most users, but not
    all. Since you didn't accept https://github.com/nuglues/nuglues.github.io/pull/2 I'm forced to change the code
    on your site myself.
 */

function getTokenPrice() {
    if (arguments.callee.nextCheck > (new Date()).valueOf()) {
        return;
    }
    arguments.callee.nextCheck = (new Date()).valueOf() + 30000;

    var tokenXmlhttp = new XMLHttpRequest();
    tokenXmlhttp.open("get", "https://data.wowtoken.info/snapshot.json");
    tokenXmlhttp.send();
    tokenXmlhttp.onreadystatechange = function () {
        if (tokenXmlhttp.readyState === 4 && tokenXmlhttp.status === 200) {
            var data = JSON.parse(tokenXmlhttp.responseText);
            innerHtml("tokenPrice-1", dateObjToStr(new Date(data.CN.timestamp*1000), 1,1,1,1,1,1,"-",":"));
            innerHtml("tokenPrice-2", data.CN.formatted.buy2);
        }
    };
}
