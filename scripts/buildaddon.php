<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');

RunMeNTimes(1);
CatchKill();

ini_set('memory_limit','256M');

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

heartbeat();
file_put_contents('../addon/MarketData.lua', BuildAddonData('US'));

DebugMessage('Done! Started '.TimeDiff($startTime));

function BuildAddonData($region)
{
    global $db, $caughtKill;

    heartbeat();
    if ($caughtKill)
        return;

    $globalSpots = 3; // number of global prices in front of every string

    $stmt = $db->prepare('select distinct house from tblRealm where region = ? and canonical is not null order by 1');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $houses = DBMapArray($result, null);
    $stmt->close();

    $items = [];
    $ihmColsTemplate = '';
    for ($x = 1; $x <= 31; $x++) {
        $ihmColsTemplate .= " when $x then ihm%1\$s.mktslvr".($x < 10 ? '0' : '')."$x";
    }
    $ihmCols = sprintf($ihmColsTemplate, '');
    $ihm2Cols = sprintf($ihmColsTemplate, '2');
    $ihm3Cols = sprintf($ihmColsTemplate, '3');

    for ($hx = 0; $hx < count($houses); $hx++) {
        heartbeat();
        if ($caughtKill)
            return;

        DebugMessage('Finding prices in house '.$houses[$hx].' ('.round($hx/count($houses)*100).'%)');

        $sql = <<<EOF
SELECT tis.item,
ifnull(i.stacksize,0) stacksize,
datediff(now(), tis.lastseen) since,
round(tis.price/100) lastprice,
ifnull(ihd.priceavg, case day(hc.lastdaily) $ihmCols end) priced1,
ifnull(ihd2.priceavg, case day(timestampadd(day, -1, hc.lastdaily)) $ihm2Cols end) priced2,
ifnull(ihd3.priceavg, case day(timestampadd(day, -2, hc.lastdaily)) $ihm3Cols end) priced3,
least(ihd.pricemin, ihd2.pricemin, ihd3.pricemin) pricemin,
greatest(ihd.pricemax, ihd2.pricemax, ihd3.pricemax) pricemax
FROM tblItemSummary tis
join tblHouseCheck hc on hc.house = tis.house
join tblDBCItem i on tis.item=i.id
left join tblItemHistoryDaily ihd on ihd.house=tis.house and ihd.item=tis.item and ihd.`when` = hc.lastdaily
left join tblItemHistoryDaily ihd2 on ihd2.house=tis.house and ihd2.item=tis.item and ihd2.`when` = timestampadd(day, -1, hc.lastdaily)
left join tblItemHistoryDaily ihd3 on ihd3.house=tis.house and ihd3.item=tis.item and ihd3.`when` = timestampadd(day, -2, hc.lastdaily)
left join tblItemHistoryMonthly ihm on ihm.house=tis.house and ihm.item=tis.item and ihm.month=((year(hc.lastdaily) - 2014) * 12 + month(hc.lastdaily))
left join tblItemHistoryMonthly ihm2 on ihm2.house=tis.house and ihm2.item=tis.item and ihm2.month=((year(timestampadd(day, -1, hc.lastdaily)) - 2014) * 12 + month(timestampadd(day, -1, hc.lastdaily)))
left join tblItemHistoryMonthly ihm3 on ihm3.house=tis.house and ihm3.item=tis.item and ihm3.month=((year(timestampadd(day, -2, hc.lastdaily)) - 2014) * 12 + month(timestampadd(day, -2, hc.lastdaily)))
WHERE tis.house = ?
EOF;
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $houses[$hx]);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = DBMapArray($result);
        $stmt->close();

        foreach ($prices as $item => $priceRow) {
            $items[$item][$hx+$globalSpots] = [
                'avg' => priceAvg($priceRow['lastprice'], [$priceRow['priced1'], $priceRow['priced2'], $priceRow['priced3']]),
                'days' => min(251, $priceRow['since']),
            ];
            if ($priceRow['stacksize'] > 1) {
                if ($priceRow['pricemin'] == 0) {
                    $priceRow['pricemin'] = $items[$item][$hx+$globalSpots]['avg'];
                }
                if ($priceRow['pricemax'] == 0) {
                    $priceRow['pricemax'] = $items[$item][$hx+$globalSpots]['avg'];
                }
                $items[$item][$hx+$globalSpots]['min'] = $priceRow['pricemin'];
                $items[$item][$hx+$globalSpots]['max'] = $priceRow['pricemax'];
            }
        }
    }

    heartbeat();
    if ($caughtKill)
        return;

    DebugMessage('Finding global prices');

    $stmt = $db->prepare('SELECT item, median, mean, stddev FROM tblItemGlobal');
    $stmt->execute();
    $result = $stmt->get_result();
    $globalPrices = DBMapArray($result);
    $stmt->close();

    foreach ($globalPrices as $item => $priceRow) {
        $items[$item][0] = ['avg' => round($priceRow['median']/100)];
        $items[$item][1] = ['avg' => round($priceRow['mean']/100)];
        $items[$item][2] = ['avg' => round($priceRow['stddev']/100)];
    }

    ksort($items);

    DebugMessage('Making lua strings');

    $priceLua = [];
    foreach ($items as $item => $prices) {
        heartbeat();
        if ($caughtKill)
            return;

        $priceBytes = 0;
        $hasMinMax = false;

        for ($x = 0; $x < count($houses)+$globalSpots; $x++) {
            if (!isset($prices[$x])) {
                $prices[$x] = ['avg' => 0, 'days' => 255];
                continue;
            }
            for ($y = 5; $y >= $priceBytes; $y--) {
                $hasMinMax |= isset($prices[$x]['min']);
                if ($prices[$x]['avg'] >= pow(2,8*$y)) {
                    $priceBytes = $y+1;
                }
            }
        }
        if ($priceBytes == 0) {
            continue;
        }
        $priceString = chr($priceBytes + ($hasMinMax ? 128 : 0));
        for ($x = 0; $x < $globalSpots; $x++) {
            $priceBin = '';
            $price = $prices[$x]['avg'];
            for ($y = 0; $y < $priceBytes; $y++) {
                $priceBin = chr($price % 256) . $priceBin;
                $price = $price >> 8;
            }
            $priceString .= $priceBin;
        }
        $padding = 3 * ceil(($priceBytes * $globalSpots + 1)/3) - strlen($priceString);
        if ($padding) {
            $priceString .= str_repeat(chr(0),$padding);
        }
        for ($x = $globalSpots; $x < count($prices); $x++) {
            $thisPriceString = chr($prices[$x]['days']);
            $priceBin = '';
            $price = $prices[$x]['avg'];
            for ($y = 0; $y < $priceBytes; $y++) {
                $priceBin = chr($price % 256) . $priceBin;
                $price = $price >> 8;
            }
            $thisPriceString .= $priceBin;
            if ($hasMinMax) {
                if (!isset($prices[$x]['min'])) {
                    $thisPriceString .= chr(0);
                } else {
                    $thisPriceString .= ($prices[$x]['avg'] == 0) ? chr(0) : chr(round($prices[$x]['min'] / $prices[$x]['avg'] * 255));
                }
                if (!isset($prices[$x]['max'])) {
                    $thisPriceString .= chr(0);
                } else {
                    $thisPriceString .= ($prices[$x]['max'] == 0) ? chr(0) : chr(round($prices[$x]['avg'] / $prices[$x]['max'] * 255));
                }
            }
            $priceString .= $thisPriceString;
        }
        $priceLua[] = sprintf("addonTable.marketData[%d]=crop(%d,%s,'%s')\n", $item, $priceBytes, $hasMinMax ? 'true' : 'false', base64_encode($priceString));
    }
    unset($items);

    heartbeat();
    if ($caughtKill)
        return;

    DebugMessage('Setting realm indexes');

    $houseLookup = array_flip($houses);

    $stmt = $db->prepare('select name, house from tblRealm where region = ?');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $realms = DBMapArray($result);
    $stmt->close();

    $realmLua = '';
    foreach ($realms as $realmRow) {
        if (isset($houseLookup[$realmRow['house']])) {
            $realmLua .= sprintf('if realmName == "%s" then realmIndex = %d end'."\n", mb_strtoupper($realmRow['name']), $houseLookup[$realmRow['house']]);
        }
    }

    heartbeat();
    if ($caughtKill)
        return;

    DebugMessage('Building final lua');
    $dataAge = time();

    $lua = <<<EOF
local addonName, addonTable = ...
local realmName = addonTable.realmName or string.upper(GetRealmName())

addonTable.dataAge = $dataAge

local realmIndex = nil
$realmLua

if addonTable.region ~= "$region" then
    realmIndex = nil
end

if realmIndex then
    addonTable.marketData = {}
    addonTable.realmIndex = realmIndex
end

local function crop(priceSize8, hasMinMax, b)
    local headerSize6 = math.ceil((priceSize8 * 3 + 1) / 3) * 4

    local recordSize8 = priceSize8 + 1
    if hasMinMax then
        recordSize8 = recordSize8 + 2
    end
    local recordSize6 = math.ceil(recordSize8 / 3) * 4

    local offset6 = 1 + headerSize6 + math.floor(recordSize8 * realmIndex / 3) * 4
    local length6 = recordSize6 + 4

    return string.sub(b, 1, headerSize6)..string.sub(b, offset6, offset6 + length6 - 1)
end


EOF;

    $priceLuas = array_chunk($priceLua, 250);
    unset($priceLua);

    for ($x = 0; $x < count($priceLuas); $x++) {
        $lua .= "if realmIndex then\n";
        $lua .= implode('', $priceLuas[$x]);
        $lua .= "end\n";
    }
    unset($priceLuas);

    return $lua;
}


function priceAvg($lastPrice, $dailyPrices) {
    for ($x = 0; $x < count($dailyPrices); $x++) {
        if (is_null($dailyPrices[$x])) {
            array_splice($dailyPrices, $x--, 1);
        }
    }
    if (count($dailyPrices) == 0) {
        return $lastPrice;
    }
    return round(array_sum($dailyPrices)/count($dailyPrices));
}