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
    var ShowError = function() {
        var d = document.getElementById('lastupdate');
        d.className += ' error';
        d.innerHTML = 'Error reading building data.';
    }

    var req = new XMLHttpRequest();
    if (!req) return;
    req.open('GET',(location.hostname == 'magetower.info' ? '//data.magetower.info/' : '') + 'magetower.json',true);
    req.onreadystatechange = function () {
        if (req.readyState != 4) return;
        if (req.status != 200) {
            ShowError();
        } else {
            try {
                ReadJson(req.response);
            } catch (e) {
                ShowError();
            }
        }
    };
    if (req.readyState == 4) return;
    req.send();
}

function ReadJson(response) {
    if (typeof response != 'object') {
        response = JSON.parse(response);
    }

    document.getElementById('lastupdate').innerHTML = 'Last updated: ' + PrettySeconds(Math.floor((Date.now() - response.timestamp * 1000) / 1000)) + ' ago (' + (new Date(response.timestamp * 1000)).toLocaleString() + ')';

    var d, td, tr, tbl = document.createElement('table');

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
        1: {'shape': 'diamond', 'name': 'Under Construction'},
        2: {'shape': 'star', 'name': 'Active'},
        3: {'shape': 'skull', 'name': 'Under Attack'},
        4: {'shape': 'cross', 'name': 'Destroyed'},
    };


    var buffDescriptions = {
        237137: 'Artifact Power from<br>dungeons and raids',
        237139: 'Artifact Power from<br>world quests',
        240979: 'Reputation bonus with<br>Armies of Legionfall',
        240980: 'Waterwalk while mounted',

        239966: 'Bonus Legionfall War Supplies',
        240986: 'Legendary follower equipment',
        240989: 'Defiled Augment Rune<br>from world quests',
        240987: '10% primary stat bonus<br>in Broken Shore',

        239967: 'Daily free<br>Seal of Broken Fate',
        239968: 'Refund chance for<br>Seal of Broken Fate',
        239969: 'Bonus Nethershards',
        240985: 'Interact while mounted',
    }

    var makeDiv = function(txt, className) {
        var d = document.createElement('div');
        if (txt) {
            d.innerHTML = txt;
        }
        if (className) {
            d.className = className;
        }
        return d;
    };

    var niceHours = function(amt) {
        if (isNaN(amt) || amt === null || amt < 0) {
            return '';
        }
        if (amt > 72) {
            return '>72 hours';
        }
        return '' + amt + ' hour' + (amt == 1 ? '' : 's');
    };

    var niceBuffs = function(buffs) {
        var s = '';
        for (var x = 0; x < buffs.length; x++) {
            if (buffDescriptions.hasOwnProperty(buffs[x])) {
                s += (s ? '<br>' : '') + buffDescriptions[buffs[x]];
            }
        }
        return s;
    }

    for (var region, rx=0; region = regionOrder[rx]; rx++) {
        if (!response.update.hasOwnProperty(region)) {
            continue;
        }

        tr = document.createElement('tr');
        tr.className = 'separator';
        tbl.appendChild(tr);

        td = document.createElement('td');
        tr.appendChild(td);
        td.colSpan = 3;

        tr = document.createElement('tr');
        tr.className = 'region-name';
        tbl.appendChild(tr);

        td = document.createElement('td');
        tr.appendChild(td);
        td.colSpan = 3;

        var h = document.createElement('h2');
        h.appendChild(document.createTextNode(regions[region]));
        td.appendChild(h);

        tr = document.createElement('tr');
        tr.className = 'buildings';
        tbl.appendChild(tr);

        for (var buildingName in buildings) {
            if (!response.update[region].hasOwnProperty(buildings[buildingName])) {
                continue;
            }
            var buildingData = response.update[region][buildings[buildingName]];

            td = document.createElement('td');
            tr.appendChild(td);

            td.appendChild(makeDiv(buildingName, 'name'));

            if (status.hasOwnProperty(buildingData.state)) {
                d = makeDiv(status[buildingData.state].name, 'status');
                var s = document.createElement('span');
                s.className = 'shape ' + status[buildingData.state].shape;
                d.insertBefore(s, d.firstChild);
                d.appendChild(s.cloneNode());
            } else {
                d = makeDiv('Unknown: ' + buildingData.state, 'status');
                var s = document.createElement('span');
                s.className = 'shape moon';
                d.insertBefore(s, d.firstChild);
            }
            td.appendChild(d);

            var days = 1;

            switch (buildingData.state) {
                case 1: // under construction
                    td.appendChild(makeDiv(Math.round(buildingData.contributed * 100) + '%', 'percentage'));
                    td.appendChild(makeDiv(niceHours(buildingData.contributed_hours), 'hours'));
                    break;
                case 2: // active
                    days = 2;
                case 3: // under attack
                case 4: // destroyed
                    td.appendChild(makeDiv(Math.round((Date.now() / 1000 - buildingData.lastchange) / (864 * days)) + '%', 'percentage'));
                    td.appendChild(makeDiv(niceHours(Math.floor(((buildingData.lastchange + (86400 * days)) * 1000 - Date.now()) / 1000 / 3600)), 'hours'));
                    break;
                default:
                    td.appendChild(makeDiv(false, 'percentage'));
                    td.appendChild(makeDiv(false, 'hours'));
                    break;
            }

            td.appendChild(makeDiv(niceBuffs([buildingData.buff1, buildingData.buff2]), 'buffs'));
        }

    }

    var resultDiv = document.getElementById('result');
    resultDiv.appendChild(tbl);
}

FetchJson();