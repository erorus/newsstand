<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');

define('PRICE_ENCODING', 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_');
define('MOST_COPPER', 200000000); // 20k gold
define('LOG_BASE', 10);

$priceLogFactor = log(MOST_COPPER/10000, LOG_BASE) / (pow(strlen(PRICE_ENCODING),2) - 1);

RunMeNTimes(1);
CatchKill();

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

BuildAddonData('US');
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

    for ($hx = 0; $hx < count($houses); $hx++) {
        $sql = <<<EOF
SELECT tis.item, ifnull(ihd.priceavg, tis.price) prc, datediff(now(), tis.lastseen) since
FROM tblItemSummary tis
left join tblItemHistoryDaily ihd on ihd.house=tis.house and ihd.item=tis.item and ihd.`when` = date(timestampadd(day,-1,now()))
WHERE tis.house = ?
EOF;
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $houses[$hx]);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = DBMapArray($result);
        $stmt->close();

        foreach ($prices as $item => $priceRow) {
            if (!isset($items[$item])) {
                $items[$item] = '';
            }
            if (strlen($items[$item]) < ($hx * 3)) {
                $items[$item] .= str_repeat(' ', $hx * 3 - strlen($items[$item]));
            }
            $items[$item] .= EncodePrice($priceRow['prc']) . EncodeDays($priceRow['since']);;
        }
    }

    $stmt = $db->prepare('SELECT item, median FROM tblItemGlobal');
    $stmt->execute();
    $result = $stmt->get_result();
    $globalPrices = DBMapArray($result);
    $stmt->close();

    foreach ($globalPrices as $item => $priceRow) {
        if (!isset($items[$item])) {
            $items[$item] = '';
        }
        $items[$item] = EncodePrice($priceRow['median']/100) . $items[$item];
    }

    ksort($items);

    foreach ($items as $item => $prices) {
        echo "[$item] = '$prices',\n";
    }

    $houseLookup = array_flip($houses);

    $stmt = $db->prepare('select name, house from tblRealm where region = ?');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $realms = DBMapArray($result);
    $stmt->close();

    foreach ($realms as $realmRow) {
        if (isset($houseLookup[$realmRow['house']])) {
            echo '["' . $realmRow['name'] . '"] = ' . $houseLookup[$realmRow['house']] . ",\n";
        }
    }
}

function EncodePrice($price) {
    global $priceLogFactor;

    if (is_null($price)) {
        return '  ';
    }
    if ($price < 50) {
        return substr(PRICE_ENCODING,0,1).substr(PRICE_ENCODING,0,1);
    }
    if ($price > MOST_COPPER) {
        return substr(PRICE_ENCODING,63,1).substr(PRICE_ENCODING,63,1);
    }

    $value = floor(log($price/10000 + 1, LOG_BASE)/$priceLogFactor);

    return substr(PRICE_ENCODING, $value >> 6, 1) . substr(PRICE_ENCODING, $value & 63, 1);
}

function EncodeDays($days) {
    if ($days > 60) {
        return substr(PRICE_ENCODING,63,1);
    }
    return substr(PRICE_ENCODING, $days, 1);
}