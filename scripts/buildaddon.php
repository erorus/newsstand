<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');

RunMeNTimes(1);
CatchKill();

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

file_put_contents('../addon/MarketData.lua', BuildAddonData('US'));

DebugMessage('Done! Started '.TimeDiff($startTime));

function BuildAddonData($region)
{
    global $db, $caughtKill;

    if ($caughtKill)
        return;

    $stmt = $db->prepare('select distinct house from tblRealm where region = ? and canonical is not null');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $houses = DBMapArray($result, null);
    $stmt->close();

    $items = [];
    $ihmCols = '';
    for ($x = 1; $x <= 31; $x++) {
        $ihmCols .= " when $x then ihm.mktslvr".($x < 10 ? '0' : '')."$x";
    }

    for ($hx = 0; $hx < count($houses); $hx++) {
        DebugMessage('Finding prices in house '.$houses[$hx].' ('.round($hx/count($houses)*100).'%)');
        $sql = <<<EOF
SELECT tis.item, round(ifnull(ihd.priceavg, ifnull(case day(hc.lastdaily) $ihmCols end, tis.price/100)))*100 prc, datediff(now(), tis.lastseen) since
FROM tblItemSummary tis
join tblHouseCheck hc on hc.house = tis.house
left join tblItemHistoryDaily ihd on ihd.house=tis.house and ihd.item=tis.item and ihd.`when` = hc.lastdaily
left join tblItemHistoryMonthly ihm on ihm.house=tis.house and ihm.item=tis.item and ihm.month=((year(hc.lastdaily) - 2014) * 12 + month(hc.lastdaily))
WHERE tis.house = ?
EOF;
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $houses[$hx]);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = DBMapArray($result);
        $stmt->close();

        foreach ($prices as $item => $priceRow) {
            $items[$item][$hx+3] = round($priceRow['prc']/100);
        }
    }

    $stmt = $db->prepare('SELECT item, median, mean, stddev FROM tblItemGlobal');
    $stmt->execute();
    $result = $stmt->get_result();
    $globalPrices = DBMapArray($result);
    $stmt->close();

    foreach ($globalPrices as $item => $priceRow) {
        $items[$item][0] = round($priceRow['median']/100);
        $items[$item][1] = round($priceRow['mean']/100);
        $items[$item][2] = round($priceRow['stddev']/100);
    }

    ksort($items);

    $priceLua = [];
    foreach ($items as $item => $prices) {
        $priceBytes = 0;
        for ($x = 0; $x < count($houses)+3; $x++) {
            if (!isset($prices[$x])) {
                $prices[$x] = 0;
                continue;
            }
            for ($y = 5; $y >= $priceBytes; $y--) {
                if ($prices[$x] >= pow(2,8*$y)) {
                    $priceBytes = $y+1;
                }
            }
        }
        if ($priceBytes == 0) {
            continue;
        }
        $priceString = chr($priceBytes);
        for ($x = 0; $x < count($prices); $x++) {
            $priceBin = '';
            for ($y = 0; $y < $priceBytes; $y++) {
                $priceBin = chr($prices[$x] % 256) . $priceBin;
                $prices[$x] = $prices[$x] >> 8;
            }
            $priceString .= $priceBin;
        }
        $priceLua[] = 'addonTable.marketData['.$item.'] = \''.base64_encode($priceString)."'\n";
    }
    unset($items);

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
            $realmLua .= 'if realmName == "' . mb_strtoupper($realmRow['name']) . '" then realmIndex = ' . $houseLookup[$realmRow['house']] . " end\n";
        }
    }

    $lua = <<<EOF
local addonName, addonTable = ...
local realmName = addonTable.realmName or string.upper(GetRealmName())

local realmIndex = nil

$realmLua

if addonTable.region ~= "US" then
    realmIndex = nil
end

if realmIndex then
    addonTable.marketData = {}
    addonTable.realmIndex = realmIndex
end

EOF;

    $priceLuas = array_chunk($priceLua, 250);
    for ($x = 0; $x < count($priceLuas); $x++) {
        $lua .= "if realmIndex then\n";
        $lua .= implode('', $priceLuas[$x]);
        $lua .= "end\n";
    }

    return $lua;

}
