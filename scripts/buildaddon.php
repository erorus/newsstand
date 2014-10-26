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

define('ITEM_COL_AVG', 0);
define('ITEM_COL_DAYS', 1);
define('ITEM_COL_MIN', 2);
define('ITEM_COL_MAX', 3);

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

    $item_avg = [];
    $item_days = [];
    $item_min = [];
    $item_max = [];

    DebugMessage('Finding global prices');

    $stmt = $db->prepare('SELECT item, median, mean, stddev FROM tblItemGlobal');
    $stmt->execute();
    $result = $stmt->get_result();
    $globalPrices = DBMapArray($result);
    $stmt->close();

    foreach ($globalPrices as $item => $priceRow) {
        $item_avg[$item] = pack('LLL', round($priceRow['median']/100), round($priceRow['mean']/100), round($priceRow['stddev']/100));
    }

    $ihmColsTemplate = '';
    for ($x = 1; $x <= 31; $x++) {
        $ihmColsTemplate .= " when $x then ihm%1\$s.mktslvr".($x < 10 ? '0' : '')."$x";
    }
    $ihmCols = sprintf($ihmColsTemplate, '');
    $ihm2Cols = sprintf($ihmColsTemplate, '2');
    $ihm3Cols = sprintf($ihmColsTemplate, '3');

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

            $c = 0; $s = 0;
            for ($x = 1; $x <=3 ; $x++) {
                if (!is_null($priceRow["priced$x"])) {
                    $s += intval($priceRow["priced$x"], 0);
                    $c++;
                }
            }
            if ($c > 0) {
                $avg = round($s / $c);
            } else {
                $avg = intval($priceRow['lastprice'],0);
            }

            if (!isset($item_avg[$item])) {
                $item_avg[$item] = '';
            }
            if (!isset($item_days[$item])) {
                $item_days[$item] = '';
            }
            $item_avg[$item] .= str_repeat(chr(0), 4 * ($hx + $globalSpots) - strlen($item_avg[$item])) . pack('L', $avg);
            $item_days[$item] .= str_repeat(chr(255), $hx - strlen($item_days[$item])) . chr(min(251, intval($priceRow['since'],10)));

            if ($priceRow['stacksize'] > 1) {
                if (!isset($item_min[$item])) {
                    $item_min[$item] = '';
                }
                if (!isset($item_max[$item])) {
                    $item_max[$item] = '';
                }
                $item_min[$item] .= str_repeat(chr(0), 4 * $hx - strlen($item_min[$item])) . pack('L', $priceRow['pricemin'] ? intval($priceRow['pricemin'],0) : $avg);
                $item_max[$item] .= str_repeat(chr(0), 4 * $hx - strlen($item_max[$item])) . pack('L', $priceRow['pricemax'] ? intval($priceRow['pricemax'],0) : $avg);
            }
        }
        $result->close();
        $stmt->close();
    }

    heartbeat();
    if ($caughtKill)
        return;

    DebugMessage('Making lua strings');

    $priceLua = '';
    foreach ($item_avg as $item => $priceList) {
        heartbeat();
        if ($caughtKill)
            return;

        $hasMinMax = isset($item_min[$item]) && isset($item_max[$item]);

        $prices = array_values(unpack('L*',$priceList));
        if ($hasMinMax) {
            $mins = array_values(unpack('L*',$item_min[$item]));
            $maxs = array_values(unpack('L*',$item_max[$item]));
        }

        $priceBytes = 0;
        for ($x = 0; $x < count($houses)+$globalSpots; $x++) {
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

        $priceString = chr($priceBytes + ($hasMinMax ? 128 : 0));

        for ($x = 0; $x < $globalSpots; $x++) {
            $priceBin = '';
            $price = $prices[$x];
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
            $x2 = $x - $globalSpots;

            if ((!isset($item_days[$item])) || (($thisPriceString = substr($item_days[$item], $x2, 1)) === false)) {
                $thisPriceString = chr(255);
            }
            $priceBin = '';
            $price = $prices[$x];
            for ($y = 0; $y < $priceBytes; $y++) {
                $priceBin = chr($price % 256) . $priceBin;
                $price = $price >> 8;
            }
            $thisPriceString .= $priceBin;

            if ($hasMinMax) {
                if (!isset($mins[$x])) {
                    $thisPriceString .= chr(0);
                } else {
                    $thisPriceString .= ($prices[$x] == 0) ? chr(0) : chr(round($mins[$x] / $prices[$x] * 255));
                }
                if (!isset($maxs[$x])) {
                    $thisPriceString .= chr(0);
                } else {
                    $thisPriceString .= ($maxs[$x] == 0) ? chr(0) : chr(round($prices[$x] / $maxs[$x] * 255));
                }
            }
            $priceString .= $thisPriceString;
        }
        $priceLua .= sprintf("addonTable.marketData[%d]=crop(%d,%s,'%s')\n", $item, $priceBytes, $hasMinMax ? 'true' : 'false', base64_encode($priceString));
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

if not string.upper(GetCVar("portal")) == "$region" then
    return
end

local realmName = string.gsub(string.upper(GetRealmName()), '[^%w]', '')

addonTable.dataAge = $dataAge

local realmIndex = nil
$realmLua

if not realmIndex then
    return
end

addonTable.marketData = {}
addonTable.realmIndex = realmIndex

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