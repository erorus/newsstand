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

$luaQuoteChange = [
    "\r" => '\\r',
    "\n" => '\\n',
    chr(26) => '\\026'
];

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
    $item_recent = [];
    $item_days = [];

    DebugMessage('Finding global prices');

    $stmt = $db->prepare('SELECT item, median, mean, stddev FROM tblItemGlobal where bonusset=0');
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
round(ifnull(avg(if(ih.snapshot > timestampadd(hour, -72, now()), ih.price, null)), tis.price)/100) pricerecent,
round(stddev(ih.price)/100) pricestddev
FROM tblItemSummary tis
join tblHouseCheck hc on hc.house = tis.house
left join tblItemHistory ih on ih.item=tis.item and ih.house = tis.house and ih.bonusset=tis.bonusset
WHERE tis.house = ? and tis.bonusset=0
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
            $priceLua .= "somedata = function()\n";
        }
        $luaQuoted = luaQuote($priceString);
        if (!is_array($luaQuoted)) {
            $luaQuoteFull = $luaQuoted;
        } else {
            $luaQuoteFull = 'concathelp';
            for ($x = 0; $x < count($luaQuoted); $x++) {
                $priceLua .= 'concathelp = ' . ($x > 0 ? 'concathelp..' : '') . $luaQuoted[$x] . "\n";
            }
        }
        $priceLua .= sprintf("addonTable.marketData[%d]=crop(%d,%s)\n", $item, $priceBytes, $luaQuoteFull);
        if (++$luaLines >= 2000) {
            $priceLua .= "end\nsomedata()\n";
            $luaLines = 0;
        }
    }
    unset($items);
    if ($luaLines > 0) {
        $priceLua .= "end\nsomedata()\n";
    }

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

local realmName = string.gsub(string.upper(GetRealmName()), '[- ()\']', '')

addonTable.dataAge = $dataAge

local realmIndex = nil
$realmLua

if not realmIndex then
    print("The Undermine Journal - Warning: detected region $region but could not find data for realm "..GetRealmName())
    return
end

addonTable.marketData = {}
addonTable.realmIndex = realmIndex

local function crop(priceSize, b)
    local headerSize = 1 + priceSize * 3
    local recordSize = 1 + priceSize * 3

    local offset = 1 + headerSize + recordSize * realmIndex

    return string.sub(b, 1, headerSize)..string.sub(b, offset, offset + recordSize - 1)
end

local somedata = function() end
local concathelp = ''

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
    $tocFile = sprintf($tocFile, Date('D, F j'), Date('Ymd'));

    $zip->addEmptyDir('TheUndermineJournal');
    $zip->addFromString("TheUndermineJournal/TheUndermineJournal.toc",$tocFile);
    $zip->addFile('../addon/TheUndermineJournal.lua',"TheUndermineJournal/TheUndermineJournal.lua");
    $zip->addFile('../addon/MarketData-US.lua',"TheUndermineJournal/MarketData-US.lua");
    $zip->addFile('../addon/MarketData-EU.lua',"TheUndermineJournal/MarketData-EU.lua");
    //$zip->addFromString("TheUndermineJournal/SpellToItem.lua",getspelltoitemlua());
    $zip->close();

    rename($zipFilename, '../addon/TheUndermineJournal.zip');
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
        $resultIdx = floor($x / 30);
        if (!isset($result[$resultIdx])) {
            $result[$resultIdx] = '';
        }
        $c = 0;
        $p = preg_replace_callback($regex, 'luaPreg', $parts[$x], -1, $c);
        if ($c == 0) {
            $p = luaBracket($p);
        }
        $result[$resultIdx] .= ($result[$resultIdx] == '' ? '' : '..') . $p;
    }

    if (count($result) == 1) {
        return array_pop($result);
    }

    return $result;
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
