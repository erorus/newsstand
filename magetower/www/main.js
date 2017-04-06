function PrettySeconds(s) {
    s = parseInt(s, 10);

    if (s <= 1) {
        return 'Immediately';
    }
    if (s <= 90) {
        return '' + s + " seconds";
    }
    var m = Math.round(s/60);
    if (m <= 90) {
        return '' + m + ' minute' + (m == 1 ? '' : 's');
    }
    var h = Math.floor(m/60);
    m = m % 60;
    if (h <= 36) {
        return '' + h + ' hour' + (h == 1 ? '' : 's') + ', ' + m + ' minute' + (m == 1 ? '' : 's');
    }
    var d = Math.floor(h/24);
    h = h % 24;
    return '' + d + ' day' + (d == 1 ? '' : 's') + ', ' + h + ' hour' + (h == 1 ? '' : 's');
}

function FetchJson() {
    var req = new XMLHttpRequest();
    if (!req) return;
    req.open('GET','snapshot.json',true);
    req.onreadystatechange = function () {
        if (req.readyState != 4) return;
        ReadJson(req.response);
    };
    if (req.readyState == 4) return;
    req.send();
}

function ReadJson(response) {
    if (typeof response != 'object') {
        response = JSON.parse(response);
    }

    document.getElementById('lastupdate').innerHTML = 'Last updated: ' + PrettySeconds(Math.floor((Date.now() - response.timestamp * 1000) / 1000)) + ' ago (' + (new Date(response.timestamp * 1000)).toLocaleString() + ')';

    var td, tr, tbl = document.createElement('table');

    var regions = {
        'US': 'North America',
        'EU': 'Europe',
        'CN': 'China',
        'TW': 'Taiwan',
        'KR': 'Korea',
    }

    var regionOrder = ['US', 'EU', 'CN', 'TW', 'KR'];

    var offset = (new Date()).getTimezoneOffset();
    if ((offset < 120) && (offset > -360)) {
        regionOrder = ['EU', 'US', 'CN', 'TW', 'KR'];
    }

    var buildings = {
        'Mage Tower': 1,
        'Command Center': 3,
        'Nether Disruptor': 4,
    };

    var status = {
        1: 'Under Construction',
        2: 'Active',
        3: 'Under Attack',
        4: 'Destroyed',
    };

    var buffDescriptions = {
        237137: 'Artifact Power from dungeons and raids',
        237139: 'Artifact Power from world quests',
        240979: 'Reputation bonus with Armies of Legionfall',
        240980: 'Waterwalk while mounted',

        239966: 'Bonus Legionfall War Supplies',
        240986: 'Legendary follower equipment',
        240989: 'Defiled Augment Rune from world quests',
        240987: '10% primary stat bonus in Broken Shore',

        239967: 'Daily free Seal of Broken Fate',
        239968: 'Refund chance for Seal of Broken Fate',
        239969: 'Bonus Nethershards',
        240985: 'Interact while mounted',
    }

    var makeTd = function(txt, className) {
        var td = document.createElement('td');
        if (txt) {
            var t = document.createTextNode(txt);
            td.appendChild(t);
        }
        if (className) {
            td.className = className;
        }
        return td;
    };

    var niceHours = function(amt) {
        if (isNaN(amt) || amt === null || amt < 0) {
            return '';
        }
        if (amt > 72) {
            return '>72 hours';
        }
        return '' + amt + ' hours';
    };

    var niceBuffs = function(buffs) {
        var s = '';
        for (var x = 0; x < buffs.length; x++) {
            if (buffDescriptions.hasOwnProperty(buffs[x])) {
                s += (s ? ', ' : '') + buffDescriptions[buffs[x]];
            }
        }
        return s;
    }

    for (var region, rx=0; region = regionOrder[rx]; rx++) {
        if (!response.update.hasOwnProperty(region)) {
            continue;
        }

        tr = document.createElement('tr');
        tbl.appendChild(tr);

        td = document.createElement('td');
        tr.appendChild(td);
        td.colSpan = 5;

        var h = document.createElement('h2');
        h.appendChild(document.createTextNode(regions[region]));
        td.appendChild(h);

        for (var buildingName in buildings) {
            if (!response.update[region].hasOwnProperty(buildings[buildingName])) {
                continue;
            }
            var buildingData = response.update[region][buildings[buildingName]];

            tr = document.createElement('tr');
            tbl.appendChild(tr);

            tr.appendChild(makeTd(buildingName, 'name'));
            tr.appendChild(makeTd(status[buildingData.state] || ('Unknown: ' + buildingData.state), 'status'));

            switch (buildingData.state) {
                case 1: // under construction
                    tr.appendChild(makeTd(Math.round(buildingData.contributed * 100) + '%', 'percentage'));
                    tr.appendChild(makeTd(niceHours(buildingData.contributed_hours), 'hours'));
                    break;
                case 2: // active
                    tr.appendChild(makeTd(Math.round((Date.now() / 1000 - buildingData.lastchange) / 1728) + '%', 'percentage'));
                    tr.appendChild(makeTd(niceHours(Math.floor(((buildingData.lastchange + 172800) * 1000 - Date.now()) / 1000 / 3600)), 'hours'));
                    break;
                case 3: // under attack
                case 4: // destroyed
                    tr.appendChild(makeTd(Math.round((Date.now() / 1000 - buildingData.lastchange) / 864) + '%', 'percentage'));
                    tr.appendChild(makeTd(niceHours(Math.floor(((buildingData.lastchange + 86400) * 1000 - Date.now()) / 1000 / 3600)), 'hours'));
                    break;
                default:
                    tr.appendChild(makeTd(false, 'percentage'));
                    tr.appendChild(makeTd(false, 'hours'));
                    break;
            }

            tr.appendChild(makeTd(niceBuffs([buildingData.buff1, buildingData.buff2]), 'buffs'));
        }
    }

    var resultDiv = document.getElementById('result');
    resultDiv.appendChild(tbl);
}

FetchJson();