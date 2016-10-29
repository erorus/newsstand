<?php

$zipPath = false;
if (isset($argv[1])) {
    touch($argv[1]);
    $zipPath = realpath($argv[1]);
}

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');

RunMeNTimes(1);
CatchKill();

ini_set('memory_limit','256M');

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

$db->query('set session transaction isolation level read uncommitted');

$luaQuoteChange = [
    "\r" => '\\r',
    "\n" => '\\n',
    chr(26) => '\\026'
];

heartbeat();
file_put_contents('../addon/GetDetailedItemLevelInfo.lua', BuildGetDetailedItemLevelInfo());
file_put_contents('../addon/BonusSets.lua', BuildBonusSets());
file_put_contents('../addon/MarketData-US.lua', BuildAddonData('US'));
file_put_contents('../addon/MarketData-EU.lua', BuildAddonData('EU'));
MakeZip($zipPath);

DebugMessage('Done! Started '.TimeDiff($startTime));

function BuildGetDetailedItemLevelInfo() {
    global $db;

    $lua = <<<'EOF'
--[[

GetDetailedItemLevelInfo Polyfill, v 1.0
by Erorus for The Undermine Journal
https://theunderminejournal.com/

Based on these "specs" for a GetDetailedItemLevelInfo function coming in 7.1
https://www.reddit.com/r/woweconomy/comments/50hp5d/warning_be_careful_flipping/d74olsy

Pass in an itemstring/link to GetDetailedItemLevelInfo
Returns effectiveItemLevel, previewItemLevel, baseItemLevel

This should use the in-game function if it already exists,
otherwise it'll define a function that does what *I think* the official function would do.

]]

local addonName, addonTable = ...

EOF;

    $lua .= "local bonusLevelBoost = {";
    $stmt = $db->prepare('select id, level from tblDBCItemBonus where level is not null order by 1');
    $stmt->execute();
    $k = $v = null;
    $stmt->bind_result($k, $v);
    while ($stmt->fetch()) {
        $lua .= "[".$k.']='.$v.",";
    }
    $stmt->close();
    $lua .= "}\n";

    $lua .= "local bonusPreviewLevel = {";
    $stmt = $db->prepare('select id, previewlevel from tblDBCItemBonus where previewlevel is not null order by 1');
    $stmt->execute();
    $k = $v = null;
    $stmt->bind_result($k, $v);
    while ($stmt->fetch()) {
        $lua .= "[".$k.']='.$v.",";
    }
    $stmt->close();
    $lua .= "}\n";

    $lua .= "local bonusLevelCurve = {";
    $stmt = $db->prepare('select id, levelcurve from tblDBCItemBonus where levelcurve is not null order by 1');
    $stmt->execute();
    $k = $v = null;
    $stmt->bind_result($k, $v);
    while ($stmt->fetch()) {
        $lua .= "[".$k.']='.$v.",";
    }
    $stmt->close();
    $lua .= "}\n";

    $sql = <<<'EOF'
SELECT curve, step, `key`, value
FROM `tblDBCCurvePoint` cp
join (select distinct levelcurve from tblDBCItemBonus) c on c.levelcurve = cp.curve
order by 1, 2
EOF;
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $curveSteps = DBMapArray($result, ['curve','step']);
    $stmt->close();

    $lua .= "local curvePoints = {";
    foreach ($curveSteps as $curve => $steps) {
        $lua .= "[$curve]={";
        foreach ($steps as $row) {
            $lua .= "{" . $row['key'] . ',' . $row['value'] . "},";
        }
        $lua .= "},";
    }
    $lua .= "}\n";

    $lua .= <<<'EOF'

local function round(num)
    return floor(num + 0.5)
end

local function GetCurvePoint(curveId, point)
    local curve = curvePoints[curveId]
    if not curve then
        return nil
    end

    local lastKey, lastValue = curve[1][1], curve[1][2]
    if lastKey > point then
        return lastValue
    end

    for x = 1,#curve,1 do
        if point == curve[x][1] then
            return curve[x][2]
        end
        if point < curve[x][1] then
            return round((curve[x][2] - lastValue) / (curve[x][1] - lastKey) * (point - lastKey) + lastValue)
        end
        lastKey = curve[x][1]
        lastValue = curve[x][2]
    end

    return lastValue
end

addonTable.GetDetailedItemLevelInfo = function(item)
    local _, link, _, origLevel = GetItemInfo(item)
    if not link then
        return nil, nil, nil
    end

    local itemString = string.match(link, "item[%-?%d:]+")
    local itemStringParts = { strsplit(":", itemString) }

    local numBonuses = tonumber(itemStringParts[14],10) or 0

    if numBonuses == 0 then
        return origLevel, nil, origLevel
    end

    local effectiveLevel, previewLevel, curve
    effectiveLevel = origLevel
    previewLevel = 0

    for y = 1,numBonuses,1 do
        local bonus = tonumber(itemStringParts[14+y],10) or 0

        origLevel = origLevel - (bonusLevelBoost[bonus] or 0)
        previewLevel = bonusPreviewLevel[bonus] or previewLevel
        curve = bonusLevelCurve[bonus] or curve
    end

    if curve and itemStringParts[12] == "512" then
        effectiveLevel = GetCurvePoint(curve, tonumber(itemStringParts[15+numBonuses],10)) or effectiveLevel
    end

    return effectiveLevel, previewLevel, origLevel
end

EOF;

    return $lua;
}

function BuildBonusSets()
{
    global $db;

    $lua = "local addonName, addonTable = ...\n";

    $lua .= "addonTable.bonusSets = {\n";
    $stmt = $db->prepare('SELECT `set`, group_concat(tagid order by 1 separator \',\') tagids FROM `tblBonusSet` group by `set`');
    $stmt->execute();
    $k = $v = null;
    $stmt->bind_result($k, $v);
    while ($stmt->fetch()) {
        $lua .= "\t[".$k.']={'.$v."},\n";
    }
    $stmt->close();
    $lua .= "}\n";

    $lua .= "addonTable.bonusTags = {\n";
    $stmt = $db->prepare('SELECT tagid, concat(\'\'\'\', group_concat(id order by 1 separator \'\'\',\'\'\'), \'\'\'\') bonuses FROM `tblDBCItemBonus` WHERE tagid is not null group by tagid');
    $stmt->execute();
    $k = $v = null;
    $stmt->bind_result($k, $v);
    while ($stmt->fetch()) {
        $lua .= "\t[".$k.']={'.$v."},\n";
    }
    $stmt->close();
    $lua .= "}\n";

    $stmt = $db->prepare('select id, concat_ws(\',\', ifnull(stamina,0), ifnull(power,0), ifnull(speed,0)) stats from tblDBCPet');
    $stmt->execute();
    $result = $stmt->get_result();
    $sets = DBMapArray($result);
    $stmt->close();

    $lua .= "addonTable.speciesStats = {\n";
    $lua .= "\t[0]={0,0,0},\n";

    foreach ($sets as $row) {
        if ($row['stats'] != '0,0,0') {
            $lua .= "\t[" . $row['id'] . ']={' . $row['stats'] . "},\n";
        }
    }

    $lua .= "}\n";
    return $lua;
}

function BuildAddonData($region)
{
    global $db, $caughtKill;

    heartbeat();
    if ($caughtKill)
        return;

    DebugMessage("Starting region $region");;

    $globalSpots = 3; // number of global prices in front of every string

    $stmt = $db->prepare('select distinct house from tblRealm where region = ? and canonical is not null order by 1');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $houses = DBMapArray($result, null);
    $stmt->close();

    $item_global = [];
    $item_avg = [];
    $item_stddev = [];
    $item_recent = [];
    $item_days = [];

    DebugMessage('Finding global prices');

    $itemExcludeSql = <<<EOF
and (i.quality > 0 or i.class in (2,4))
and not (i.class = 0 and i.subclass = 5 and 0 = (select count(*) from tblDBCItemReagents ir where ir.item = i.id) and i.quality < 2)
EOF;

    $sql = <<<EOF
SELECT g.item, g.bonusset, g.median, g.mean, g.stddev
FROM tblItemGlobal g
join tblDBCItem i on g.item=i.id
where g.region = ?
$itemExcludeSql
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($priceRow = $result->fetch_assoc()) {
        $item = ''.$priceRow['item'].($priceRow['bonusset'] != '0' ? ('x'.$priceRow['bonusset']) : '');
        $item_global[$item] = pack('LLL', round($priceRow['median']/100), round($priceRow['mean']/100), round($priceRow['stddev']/100));
    }
    $result->close();
    $stmt->close();

    $stmt = $db->prepare('SELECT species, breed, avg(price) `mean`, stddev(price) `stddev` FROM tblPetSummary group by species, breed');
    $stmt->execute();
    $result = $stmt->get_result();
    while ($priceRow = $result->fetch_assoc()) {
        $item = ''.$priceRow['species'].'b'.$priceRow['breed'];
        $item_global[$item] = pack('LLL', 0, round($priceRow['mean']/100), round($priceRow['stddev']/100));
    }
    $result->close();
    $stmt->close();

    $sql = <<<EOF
SELECT tis.item, tis.bonusset,
datediff(now(), tis.lastseen) since,
round(ifnull(avg(case hours.h
    when  0 then ihh.silver00 when  1 then ihh.silver01 when  2 then ihh.silver02 when  3 then ihh.silver03
    when  4 then ihh.silver04 when  5 then ihh.silver05 when  6 then ihh.silver06 when  7 then ihh.silver07
    when  8 then ihh.silver08 when  9 then ihh.silver09 when 10 then ihh.silver10 when 11 then ihh.silver11
    when 12 then ihh.silver12 when 13 then ihh.silver13 when 14 then ihh.silver14 when 15 then ihh.silver15
    when 16 then ihh.silver16 when 17 then ihh.silver17 when 18 then ihh.silver18 when 19 then ihh.silver19
    when 20 then ihh.silver20 when 21 then ihh.silver21 when 22 then ihh.silver22 when 23 then ihh.silver23
    else null end), tis.price/100)) price,
round(ifnull(avg(if(timestampadd(hour, 72 + hours.h, ihh.`when`) > now(), case hours.h
    when  0 then ihh.silver00 when  1 then ihh.silver01 when  2 then ihh.silver02 when  3 then ihh.silver03
    when  4 then ihh.silver04 when  5 then ihh.silver05 when  6 then ihh.silver06 when  7 then ihh.silver07
    when  8 then ihh.silver08 when  9 then ihh.silver09 when 10 then ihh.silver10 when 11 then ihh.silver11
    when 12 then ihh.silver12 when 13 then ihh.silver13 when 14 then ihh.silver14 when 15 then ihh.silver15
    when 16 then ihh.silver16 when 17 then ihh.silver17 when 18 then ihh.silver18 when 19 then ihh.silver19
    when 20 then ihh.silver20 when 21 then ihh.silver21 when 22 then ihh.silver22 when 23 then ihh.silver23
    else null end, null)), tis.price/100)) pricerecent,
round(stddev(case hours.h
    when  0 then ihh.silver00 when  1 then ihh.silver01 when  2 then ihh.silver02 when  3 then ihh.silver03
    when  4 then ihh.silver04 when  5 then ihh.silver05 when  6 then ihh.silver06 when  7 then ihh.silver07
    when  8 then ihh.silver08 when  9 then ihh.silver09 when 10 then ihh.silver10 when 11 then ihh.silver11
    when 12 then ihh.silver12 when 13 then ihh.silver13 when 14 then ihh.silver14 when 15 then ihh.silver15
    when 16 then ihh.silver16 when 17 then ihh.silver17 when 18 then ihh.silver18 when 19 then ihh.silver19
    when 20 then ihh.silver20 when 21 then ihh.silver21 when 22 then ihh.silver22 when 23 then ihh.silver23
    else null end)) pricestddev,
ceil(ivc.copper/100) vendorprice
FROM tblItemSummary tis
join tblDBCItem i on i.id = tis.item
join (select 0 h union select  1 h union select  2 h union select  3 h union
     select  4 h union select  5 h union select  6 h union select  7 h union
     select  8 h union select  9 h union select 10 h union select 11 h union
     select 12 h union select 13 h union select 14 h union select 15 h union
     select 16 h union select 17 h union select 18 h union select 19 h union
     select 20 h union select 21 h union select 22 h union select 23 h) hours
left join tblItemHistoryHourly ihh on ihh.item = tis.item and ihh.house = tis.house and ihh.bonusset = tis.bonusset
left join tblDBCItemVendorCost ivc on ivc.item = i.id
WHERE tis.house = ?
$itemExcludeSql
group by tis.item, tis.bonusset
EOF;

    for ($hx = 0; $hx < count($houses); $hx++) {
        heartbeat();
        if ($caughtKill)
            return;

        DebugMessage('Finding item prices in house '.$houses[$hx].' ('.round($hx/count($houses)*100).'%) '.round(memory_get_usage()/1048576));

        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $houses[$hx]);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($priceRow = $result->fetch_assoc()) {
            $item = ''.$priceRow['item'].($priceRow['bonusset'] != '0' ? ('x'.$priceRow['bonusset']) : '');

            if (!isset($item_avg[$item])) {
                $item_avg[$item] = '';
            }
            if (!isset($item_stddev[$item])) {
                $item_stddev[$item] = '';
            }
            if (!isset($item_recent[$item])) {
                $item_recent[$item] = '';
            }
            if (!isset($item_days[$item])) {
                $item_days[$item] = '';
            }

            $prc = intval($priceRow['price'], 10);
            $usingVendor = $priceRow['vendorprice'] && (intval($priceRow['vendorprice'],10) < $prc) && ($priceRow['bonusset'] == '0');
            if ($usingVendor) {
                $prc = intval($priceRow['vendorprice'],10);
            }

            $item_avg[$item] .= str_repeat(chr(0), 4 * $hx  - strlen($item_avg[$item])) . pack('L', $prc);
            $item_stddev[$item] .= str_repeat(chr(0), 4 * $hx - strlen($item_stddev[$item])) . pack('L', (!$usingVendor && $priceRow['pricestddev']) ? intval($priceRow['pricestddev'],10) : 0);
            $item_recent[$item] .= str_repeat(chr(0), 4 * $hx - strlen($item_recent[$item])) . pack('L', (!$usingVendor && $priceRow['pricerecent']) ? intval($priceRow['pricerecent'],10) : $prc);
            $item_days[$item] .= str_repeat(chr(255), $hx - strlen($item_days[$item])) . chr($priceRow['vendorprice'] ? 252 : min(251, intval($priceRow['since'],10)));
        }
        $result->close();
        $stmt->close();
    }

    $sql = <<<EOF
SELECT tps.species, tps.breed,
datediff(now(), tps.lastseen) since,
round(ifnull(avg(case hours.h
    when  0 then phh.silver00 when  1 then phh.silver01 when  2 then phh.silver02 when  3 then phh.silver03
    when  4 then phh.silver04 when  5 then phh.silver05 when  6 then phh.silver06 when  7 then phh.silver07
    when  8 then phh.silver08 when  9 then phh.silver09 when 10 then phh.silver10 when 11 then phh.silver11
    when 12 then phh.silver12 when 13 then phh.silver13 when 14 then phh.silver14 when 15 then phh.silver15
    when 16 then phh.silver16 when 17 then phh.silver17 when 18 then phh.silver18 when 19 then phh.silver19
    when 20 then phh.silver20 when 21 then phh.silver21 when 22 then phh.silver22 when 23 then phh.silver23
    else null end), tps.price/100)) price,
round(ifnull(avg(if(timestampadd(hour, 72 + hours.h, phh.`when`) > now(), case hours.h
    when  0 then phh.silver00 when  1 then phh.silver01 when  2 then phh.silver02 when  3 then phh.silver03
    when  4 then phh.silver04 when  5 then phh.silver05 when  6 then phh.silver06 when  7 then phh.silver07
    when  8 then phh.silver08 when  9 then phh.silver09 when 10 then phh.silver10 when 11 then phh.silver11
    when 12 then phh.silver12 when 13 then phh.silver13 when 14 then phh.silver14 when 15 then phh.silver15
    when 16 then phh.silver16 when 17 then phh.silver17 when 18 then phh.silver18 when 19 then phh.silver19
    when 20 then phh.silver20 when 21 then phh.silver21 when 22 then phh.silver22 when 23 then phh.silver23
    else null end, null)), tps.price/100)) pricerecent,
round(stddev(case hours.h
    when  0 then phh.silver00 when  1 then phh.silver01 when  2 then phh.silver02 when  3 then phh.silver03
    when  4 then phh.silver04 when  5 then phh.silver05 when  6 then phh.silver06 when  7 then phh.silver07
    when  8 then phh.silver08 when  9 then phh.silver09 when 10 then phh.silver10 when 11 then phh.silver11
    when 12 then phh.silver12 when 13 then phh.silver13 when 14 then phh.silver14 when 15 then phh.silver15
    when 16 then phh.silver16 when 17 then phh.silver17 when 18 then phh.silver18 when 19 then phh.silver19
    when 20 then phh.silver20 when 21 then phh.silver21 when 22 then phh.silver22 when 23 then phh.silver23
    else null end)) pricestddev
FROM tblPetSummary tps
join (select 0 h union select  1 h union select  2 h union select  3 h union
     select  4 h union select  5 h union select  6 h union select  7 h union
     select  8 h union select  9 h union select 10 h union select 11 h union
     select 12 h union select 13 h union select 14 h union select 15 h union
     select 16 h union select 17 h union select 18 h union select 19 h union
     select 20 h union select 21 h union select 22 h union select 23 h) hours
left join tblPetHistoryHourly phh on phh.species=tps.species and phh.house = tps.house and phh.breed=tps.breed
WHERE tps.house = ?
group by tps.species, tps.breed
EOF;

    for ($hx = 0; $hx < count($houses); $hx++) {
        heartbeat();
        if ($caughtKill)
            return;

        DebugMessage('Finding pet prices in house '.$houses[$hx].' ('.round($hx/count($houses)*100).'%) '.round(memory_get_usage()/1048576));

        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $houses[$hx]);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($priceRow = $result->fetch_assoc()) {
            $item = ''.$priceRow['species'].'b'.$priceRow['breed'];

            if (!isset($item_avg[$item])) {
                $item_avg[$item] = '';
            }
            if (!isset($item_stddev[$item])) {
                $item_stddev[$item] = '';
            }
            if (!isset($item_recent[$item])) {
                $item_recent[$item] = '';
            }
            if (!isset($item_days[$item])) {
                $item_days[$item] = '';
            }

            $prc = intval($priceRow['price'], 10);

            $item_avg[$item] .= str_repeat(chr(0), 4 * $hx  - strlen($item_avg[$item])) . pack('L', $prc);
            $item_stddev[$item] .= str_repeat(chr(0), 4 * $hx - strlen($item_stddev[$item])) . pack('L', $priceRow['pricestddev'] ? intval($priceRow['pricestddev'],10) : 0);
            $item_recent[$item] .= str_repeat(chr(0), 4 * $hx - strlen($item_recent[$item])) . pack('L', $priceRow['pricerecent'] ? intval($priceRow['pricerecent'],10) : $prc);
            $item_days[$item] .= str_repeat(chr(255), $hx - strlen($item_days[$item])) . chr(min(251, intval($priceRow['since'],10)));
        }
        $result->close();
        $stmt->close();
    }

    heartbeat();
    if ($caughtKill)
        return;

    DebugMessage('Making lua strings');

    $priceLua = '';
    $luaLines = 0;
    $dataFuncIndex = 0;
    foreach ($item_global as $item => $globalPriceList) {
        heartbeat();
        if ($caughtKill)
            return;

        $globalPrices = array_values(unpack('L*',$globalPriceList));
        $prices = isset($item_avg[$item]) ? array_values(unpack('L*',$item_avg[$item])) : [];
        $recents = isset($item_recent[$item]) ? array_values(unpack('L*',$item_recent[$item])) : [];
        $stddevs = isset($item_stddev[$item]) ? array_values(unpack('L*',$item_stddev[$item])) : [];

        $priceBytes = 0;
        for ($x = 0; $x < $globalSpots; $x++) {
            if (!isset($globalPrices[$x])) {
                $globalPrices[$x] = 0;
                continue;
            }
            for ($y = 4; $y >= $priceBytes; $y--) {
                if ($globalPrices[$x] >= pow(2,8*$y)) {
                    $priceBytes = $y+1;
                }
            }
        }
        for ($x = 0; $x < count($houses); $x++) {
            if (!isset($stddevs[$x])) {
                $stddevs[$x] = 0;
            }
            if (!isset($recents[$x])) {
                $recents[$x] = 0;
            }
            if (!isset($prices[$x])) {
                $prices[$x] = 0;
                continue;
            }
            for ($y = 4; $y >= $priceBytes; $y--) {
                if ($prices[$x] >= pow(2,8*$y)) {
                    $priceBytes = $y+1;
                } elseif ($stddevs[$x] >= pow(2,8*$y)) {
                    $priceBytes = $y+1;
                } elseif ($recents[$x] >= pow(2,8*$y)) {
                    $priceBytes = $y+1;
                }
            }
        }
        if ($priceBytes == 0) {
            continue;
        }

        $priceString = chr($priceBytes);

        for ($x = 0; $x < $globalSpots; $x++) {
            $priceBin = '';
            $price = $globalPrices[$x];
            for ($y = 0; $y < $priceBytes; $y++) {
                $priceBin = chr($price % 256) . $priceBin;
                $price = $price >> 8;
            }
            $priceString .= $priceBin;
        }

        for ($x = 0; $x < count($prices); $x++) {
            if ((!isset($item_days[$item])) || (($thisPriceString = substr($item_days[$item], $x, 1)) === false)) {
                $thisPriceString = chr(255);
            }
            $priceBin = '';
            $price = $prices[$x];
            for ($y = 0; $y < $priceBytes; $y++) {
                $priceBin = chr($price % 256) . $priceBin;
                $price = $price >> 8;
            }
            $thisPriceString .= $priceBin;

            $priceBin = '';
            $price = $stddevs[$x];
            for ($y = 0; $y < $priceBytes; $y++) {
                $priceBin = chr($price % 256) . $priceBin;
                $price = $price >> 8;
            }
            $thisPriceString .= $priceBin;

            $priceBin = '';
            $price = $recents[$x];
            for ($y = 0; $y < $priceBytes; $y++) {
                $priceBin = chr($price % 256) . $priceBin;
                $price = $price >> 8;
            }
            $thisPriceString .= $priceBin;

            $priceString .= $thisPriceString;
        }
        if ($luaLines == 0) {
            $dataFuncIndex++;
            $priceLua .= "dataFuncs[$dataFuncIndex] = function()\n";
        }
        $priceLua .= sprintf("addonTable.marketData['%s']=crop(%d,%s)\n", $item, $priceBytes, luaQuote($priceString));
        if (++$luaLines >= 2000) {
            $priceLua .= "end\n";
            $luaLines = 0;
        }
    }
    unset($items);
    if ($luaLines > 0) {
        $priceLua .= "end\n";
    }

    heartbeat();
    if ($caughtKill)
        return;

    DebugMessage('Setting realm indexes');

    $houseLookup = array_flip($houses);

    $stmt = $db->prepare('select rgh.realmguid, rgh.house from tblRealmGuidHouse rgh join tblRealm r on rgh.house = r.house and r.canonical is not null where r.region = ?');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $guids = DBMapArray($result);
    $stmt->close();

    $guidLua = '';
    foreach ($guids as $guidRow) {
        if (isset($houseLookup[$guidRow['house']])) {
            $guidLua .= ($guidLua == '' ? '' : ',') . '[' . $guidRow['realmguid'] . ']=' . $houseLookup[$guidRow['house']];
        }
    }

    heartbeat();
    if ($caughtKill)
        return;

    DebugMessage('Building final lua');
    $dataAge = time();

    $lua = <<<EOF
local addonName, addonTable = ...
addonTable.dataLoads = addonTable.dataLoads or {}

local realmIndex
local dataFuncs = {}

local tuj_substr = string.sub
local tuj_concat = table.concat

local function crop(priceSize, b)
    local headerSize = 1 + priceSize * 3
    local recordSize = 1 + priceSize * 3

    local offset = 1 + headerSize + recordSize * realmIndex

    return tuj_substr(b, 1, headerSize)..tuj_substr(b, offset, offset + recordSize - 1)
end

EOF;

    $luaEnd = <<<EOF

local dataLoad = function(realmId)
    local realmGuids = {{$guidLua}}
    realmIndex = realmGuids[realmId]

    if not realmIndex then
        wipe(dataFuncs)
        return false
    end

    addonTable.marketData = {}
    addonTable.realmIndex = realmIndex
    addonTable.dataAge = $dataAge
    addonTable.region = "$region"

    for i=1,#dataFuncs,1 do
        dataFuncs[i]()
        dataFuncs[i]=nil
    end

    wipe(dataFuncs)
    return true
end

table.insert(addonTable.dataLoads, dataLoad)

EOF;


    return pack('CCC', 239, 187, 191).$lua.$priceLua.$luaEnd;
}

function MakeZip($zipPath = false)
{
    DebugMessage('Making zip file..');

    $zipFilename = tempnam('/tmp','addonzip');
    $zip = new ZipArchive;
    if (!$zip->open($zipFilename, ZipArchive::CREATE)) {
        @unlink($zipFilename);
        DebugMessage('Could not create zip file', E_USER_ERROR);;
    }

    $tocFile = file_get_contents('../addon/TheUndermineJournal.toc');
    $tocFile = sprintf($tocFile, date('D, F j'), date('Ymd'));

    $zip->addFromString("TheUndermineJournal/TheUndermineJournal.toc",$tocFile);
    RecursiveAddToZip($zip, '../addon/libs/', 'TheUndermineJournal/libs/');
    $zip->addFile('../addon/GetDetailedItemLevelInfo.lua',"TheUndermineJournal/GetDetailedItemLevelInfo.lua");
    $zip->addFile('../addon/BonusSets.lua',"TheUndermineJournal/BonusSets.lua");
    $zip->addFile('../addon/TheUndermineJournal.lua',"TheUndermineJournal/TheUndermineJournal.lua");
    $zip->addFile('../addon/MarketData-US.lua',"TheUndermineJournal/MarketData-US.lua");
    $zip->addFile('../addon/MarketData-EU.lua',"TheUndermineJournal/MarketData-EU.lua");
    //$zip->addFromString("TheUndermineJournal/SpellToItem.lua",getspelltoitemlua());
    $zip->close();

    if (!$zipPath) {
        $zipPath = '../addon/TheUndermineJournal.zip';
    }
    rename($zipFilename, $zipPath);
}

function RecursiveAddToZip(ZipArchive &$zip, $dirPath, $zipRoot) {
    $paths = glob($dirPath.'*', GLOB_MARK | GLOB_NOESCAPE);
    foreach ($paths as $path) {
        if (substr($path, -1) == '/') {
            // dir
            RecursiveAddToZip($zip, $path, $zipRoot.basename(substr($path, 0, -1)).'/');
        } else {
            // file
            $zip->addFile($path, $zipRoot.basename($path));
        }
    }
}

function luaQuote($s) {
    global $luaQuoteChange;

    if ($s == '') {
        return "''";
    }

    static $regex = '';

    if ($regex == '') {
        $regex = '/([';
        foreach (array_keys($luaQuoteChange) as $c) {
            $regex .= '\\x'.str_pad(dechex(ord($c)), 2, '0', STR_PAD_LEFT);
        }
        $regex .= ']+)/';
    }

    $parts = preg_split($regex, $s, -1, PREG_SPLIT_DELIM_CAPTURE);
    $result = [];
    for ($x = 0; $x < count($parts); $x++) {
        $c = 0;
        $p = preg_replace_callback($regex, 'luaPreg', $parts[$x], -1, $c);
        if ($c == 0) {
            $p = luaBracket($p);
        }
        $result[] = $p;
    }

    switch(count($result)) {
        case 0:
            return "''";
        case 1:
            return $result[0];
        case 2:
            return $result[0].'..'.$result[1];
    }
    return 'tuj_concat({'.implode(',',$result).'})';
}

function luaPreg($m) {
    global $luaQuoteChange;
    $s = $m[0];
    foreach ($luaQuoteChange as $from => $to) {
        $s = str_replace($from, $to, $s);
    }
    return "'$s'";
}

function luaBracket($s) {
    $e = -1;
    while (++$e < 10) {
        $eq = str_repeat('=', $e);
        $pre = '['.$eq.'[';
        $suf = "]$eq]";
        if (strpos($s, $pre) !== false) {
            continue;
        }
        if (strpos($s, $suf) !== false) {
            continue;
        }
        if (substr($s, -1*($e+1)) == substr($suf, 0, -1)) {
            continue;
        }
        return $pre.$s.$suf;
    }
    return "''";
}
