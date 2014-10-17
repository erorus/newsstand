<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');

define('PRICE_ENCODING', 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_!@#$%^&*()+=[]{}:;,.<>/?`~'); // space is null
define('MOST_COPPER', 500000000); // 50k gold
define('LOG_BASE', 10);

$priceLogFactor = log(MOST_COPPER/10000, LOG_BASE) / (pow(strlen(PRICE_ENCODING),2) - 1);

RunMeNTimes(1);
CatchKill();

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

echo BuildAddonData('US');
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
    $globalBytes = 2;

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
                $items[$item] = str_repeat(' ', $globalBytes);
            }
            if (strlen($items[$item]) < ($hx * 3 + $globalBytes)) {
                $items[$item] .= str_repeat(' ', $hx * 3 + $globalBytes - strlen($items[$item]));
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
            $items[$item] = str_repeat(' ', $globalBytes);
        }
        $items[$item] = EncodePrice($priceRow['median']/100) . substr($items[$item], $globalBytes);
    }

    ksort($items);

    $priceLua = [];
    $properLength = $hx*3+2;
    foreach ($items as $item => $prices) {
        if (strlen($prices) < $properLength) {
            $prices .= str_repeat(' ', $properLength - strlen($prices));
        }
        $priceLua[] = "addonTable.marketData[$item] = ParsePrice(\"$prices\")\n";
    }

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
            $realmLua .= 'if realmName = "' . mb_strtoupper($realmRow['name']) . '" then realmIndex = ' . $houseLookup[$realmRow['house']] . " end\n";
        }
    }

    $encoding = PRICE_ENCODING;
    $mostCopper = MOST_COPPER;

    $lua = <<<EOF
local addonName, addonTable = ...
local realmName = addonTable.realmName or string.upper(GetRealmName())

local encodingString = "$encoding"
local mostCopper = "$mostCopper"
local priceLogFactor = log10(mostCopper/10000) / (string.len(encodingString)^2) - 1)
local realmIndex = nil
$realmLua

if addonTable.region != "$region" then
    realmIndex = nil
end

if realmIndex then
    addonTable.marketData = {}
end

local function ParsePriceString(s)
    local market, regionmarket, lastseen;

    if (string.sub(s,1,2) != '  ') then regionmarket = ParsePriceBytes(string.sub(s,1,2)) end
    if (string.sub(s,realmIndex*3+2+1,2) != '  ') then market = ParsePriceBytes(string.sub(s,realmIndex*3+2+1,2)) end
    if (string.sub(s,realmIndex*3+2+1+2,1) != ' ') then lastseen = ParseDateByte(string.sub(s,realmIndex*3+2+1+2,1)) end

    if market or regionmarket or lastseen then
        return {
            ['market'] = market,
            ['regionmarket'] = regionmarket,
            ['lastseen'] = lastseen
        }
    end

    return nil
end

local function ParsePriceBytes(b)
    local value = 0

    if (b == string.rep(' ', string.len(b)) then
        return nil
    end

    if (b == string.rep(string.sub(encodingString, -1), string.len(b))) then
        return nil
    end

    for x=1,string.len(b),1 do
        value = value * string.len(encodingString) + (string.find(encodingString, string.sub(b,x,1), 1, true) - 1)
    end

    value = (10^(value * priceLogFactor) - 1) * 10000

    return value
end

local function ParseDateByte(b)
    return nil
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

function EncodePrice($price) {
    global $priceLogFactor;

    if (is_null($price)) {
        return '  ';
    }
    if ($price < 50) {
        return substr(PRICE_ENCODING,0,1).substr(PRICE_ENCODING,0,1);
    }
    if ($price > MOST_COPPER) {
        return substr(PRICE_ENCODING,-1).substr(PRICE_ENCODING,-1);
    }

    $value = floor(log($price/10000 + 1, LOG_BASE)/$priceLogFactor);

    return substr(PRICE_ENCODING, floor($value / strlen(PRICE_ENCODING)), 1) . substr(PRICE_ENCODING, $value % strlen(PRICE_ENCODING), 1);
}

function EncodeDays($days) {
    if ($days > (strlen(PRICE_ENCODING) - 2)) {
        return substr(PRICE_ENCODING,strlen(PRICE_ENCODING) - 1,1);
    }
    return substr(PRICE_ENCODING, $days, 1);
}