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
file_put_contents('../addon/MarketData-US.lua', BuildAddonData('US'));
file_put_contents('../addon/MarketData-EU.lua', BuildAddonData('EU'));
MakeZip();

DebugMessage('Done! Started '.TimeDiff($startTime));

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
    $item_days = [];

    DebugMessage('Finding global prices');

    $stmt = $db->prepare('SELECT item, median, mean, stddev FROM tblItemGlobal');
    $stmt->execute();
    $result = $stmt->get_result();
    $globalPrices = DBMapArray($result);
    $stmt->close();

    foreach ($globalPrices as $item => $priceRow) {
        $item_global[$item] = pack('LLL', round($priceRow['median']/100), round($priceRow['mean']/100), round($priceRow['stddev']/100));
    }

    $sql = <<<EOF
SELECT tis.item,
datediff(now(), tis.lastseen) since,
round(ifnull(avg(ih.price), tis.price)/100) price,
round(stddev(ih.price)/100) pricestddev
FROM tblItemSummary tis
join tblHouseCheck hc on hc.house = tis.house
left join tblItemHistory ih on ih.item=tis.item and ih.house = tis.house
WHERE tis.house = ?
group by tis.item
EOF;

    for ($hx = 0; $hx < count($houses); $hx++) {
        heartbeat();
        if ($caughtKill)
            return;

        DebugMessage('Finding prices in house '.$houses[$hx].' ('.round($hx/count($houses)*100).'%) '.round(memory_get_usage()/1048576));

        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $houses[$hx]);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($priceRow = $result->fetch_assoc()) {
            $item = intval($priceRow['item'],10);

            if (!isset($item_avg[$item])) {
                $item_avg[$item] = '';
            }
            if (!isset($item_stddev[$item])) {
                $item_stddev[$item] = '';
            }
            if (!isset($item_days[$item])) {
                $item_days[$item] = '';
            }

            $prc = intval($priceRow['price'], 10);

            $item_avg[$item] .= str_repeat(chr(0), 4 * $hx  - strlen($item_avg[$item])) . pack('L', $prc);
            $item_stddev[$item] .= str_repeat(chr(0), 4 * $hx - strlen($item_stddev[$item])) . pack('L', $priceRow['pricestddev'] ? intval($priceRow['pricestddev'],10) : $prc);
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
    foreach ($item_global as $item => $globalPriceList) {
        heartbeat();
        if ($caughtKill)
            return;

        $globalPrices = array_values(unpack('L*',$globalPriceList));
        $prices = isset($item_avg[$item]) ? array_values(unpack('L*',$item_avg[$item])) : [];
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
            if (!isset($prices[$x])) {
                $prices[$x] = 0;
                continue;
            }
            for ($y = 4; $y >= $priceBytes; $y--) {
                if ($prices[$x] >= pow(2,8*$y)) {
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
        $padding = 3 * ceil(($priceBytes * $globalSpots + 1)/3) - strlen($priceString);
        if ($padding) {
            $priceString .= str_repeat(chr(0),$padding);
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

            if (!isset($stddevs[$x])) {
                $stddevs[$x] = 0;
            }

            $priceBin = '';
            $price = $stddevs[$x];
            for ($y = 0; $y < $priceBytes; $y++) {
                $priceBin = chr($price % 256) . $priceBin;
                $price = $price >> 8;
            }
            $thisPriceString .= $priceBin;

            $priceString .= $thisPriceString;
        }
        $priceLua .= sprintf("addonTable.marketData[%d]=crop(%d,'%s')\n", $item, $priceBytes, base64_encode($priceString));
    }
    unset($items);

    heartbeat();
    if ($caughtKill)
        return;

    DebugMessage('Setting realm indexes');

    $houseLookup = array_flip($houses);

    $stmt = $db->prepare('select name, house, ownerrealm from tblRealm where region = ?');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $realms = DBMapArray($result);
    $stmt->close();

    $realmLua = '';
    $realmPattern = 'if realmName == "%s" then realmIndex = %d end'."\n";
    foreach ($realms as $realmRow) {
        if (isset($houseLookup[$realmRow['house']])) {
            $realmLua .= sprintf($realmPattern, mb_ereg_replace('[^\w]', '', mb_strtoupper($realmRow['name'])), $houseLookup[$realmRow['house']]);
            if (!is_null($realmRow['ownerrealm'])) {
                $realmLua .= sprintf($realmPattern, mb_ereg_replace('[^\w]', '', mb_strtoupper($realmRow['ownerrealm'])), $houseLookup[$realmRow['house']]);
            }
        }
    }

    heartbeat();
    if ($caughtKill)
        return;

    DebugMessage('Building final lua');
    $dataAge = time();

    $lua = <<<EOF
local addonName, addonTable = ...

if string.upper(GetCVar("portal")) ~= "$region" then
    return
end

local realmName = string.gsub(string.upper(GetRealmName()), '[^%w]', '')

addonTable.dataAge = $dataAge

local realmIndex = nil
$realmLua

if not realmIndex then
    print("The Undermine Journal - Warning: detected region $region but could not find data for realm "..GetRealmName())
    return
end

addonTable.marketData = {}
addonTable.realmIndex = realmIndex

local function crop(priceSize8, b)
    local headerSize6 = math.ceil((priceSize8 * 3 + 1) / 3) * 4

    local recordSize8 = priceSize8 + 1 + priceSize8
    local recordSize6 = math.ceil(recordSize8 / 3) * 4

    local offset6 = 1 + headerSize6 + math.floor(recordSize8 * realmIndex / 3) * 4
    local length6 = recordSize6 + 4

    return string.sub(b, 1, headerSize6)..string.sub(b, offset6, offset6 + length6 - 1)
end

EOF;

    return pack('CCC', 239, 187, 191).$lua.$priceLua;
}

function MakeZip()
{
    DebugMessage('Making zip file..');

    $zipFilename = tempnam('/tmp','addonzip');
    $zip = new ZipArchive;
    if (!$zip->open($zipFilename, ZipArchive::CREATE)) {
        @unlink($zipFilename);
        DebugMessage('Could not create zip file', E_USER_ERROR);;
    }

    $tocFile = file_get_contents('../addon/TheUndermineJournal.toc');
    $tocFile = sprintf($tocFile, Date('D, F j'));

    $zip->addEmptyDir('TheUndermineJournal');
    $zip->addFromString("TheUndermineJournal/TheUndermineJournal.toc",$tocFile);
    $zip->addFile('../addon/TheUndermineJournal.lua',"TheUndermineJournal/TheUndermineJournal.lua");
    $zip->addFile('../addon/MarketData-US.lua',"TheUndermineJournal/MarketData-US.lua");
    $zip->addFile('../addon/MarketData-EU.lua',"TheUndermineJournal/MarketData-EU.lua");
    //$zip->addFromString("TheUndermineJournal/SpellToItem.lua",getspelltoitemlua());
    $zip->close();

    rename($zipFilename, '../addon/TheUndermineJournal.zip');
}