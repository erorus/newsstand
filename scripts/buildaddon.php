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
file_put_contents('../addon/BonusSets.lua', BuildBonusSets());
file_put_contents('../addon/MarketData-US.lua', BuildAddonData('US'));
file_put_contents('../addon/MarketData-EU.lua', BuildAddonData('EU'));
MakeZip($zipPath);

DebugMessage('Done! Started '.TimeDiff($startTime));

function BuildBonusSets()
{
    global $db;

    $stmt = $db->prepare('SELECT `set`, concat(\'\'\'\', cast(group_concat(bonus order by 1 separator \'\'\',\'\'\') as char), \'\'\'\') bonuses FROM `tblBonusSet` group by `set`');
    $stmt->execute();
    $result = $stmt->get_result();
    $sets = DBMapArray($result);
    $stmt->close();

    $lua = "local addonName, addonTable = ...\naddonTable.bonusSets = {\n";

    foreach ($sets as $row) {
        $lua .= "\t[".$row['set'].']={'.$row['bonuses']."},\n";
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
where 1=1
$itemExcludeSql
EOF;

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $globalPrices = DBMapArray($result, null);
    $stmt->close();

    foreach ($globalPrices as $priceRow) {
        $item = ''.$priceRow['item'].($priceRow['bonusset'] != '0' ? ('x'.$priceRow['bonusset']) : '');
        $item_global[$item] = pack('LLL', round($priceRow['median']/100), round($priceRow['mean']/100), round($priceRow['stddev']/100));
    }
    unset($globalPrices);

    $sql = <<<EOF
SELECT tis.item, tis.bonusset,
datediff(now(), tis.lastseen) since,
round(ifnull(avg(ih.price), tis.price)/100) price,
round(ifnull(avg(if(ih.snapshot > timestampadd(hour, -72, now()), ih.price, null)), tis.price)/100) pricerecent,
round(stddev(ih.price)/100) pricestddev,
ceil(ivc.copper/100) vendorprice
FROM tblItemSummary tis
join tblDBCItem i on i.id = tis.item
join tblHouseCheck hc on hc.house = tis.house
left join tblItemHistory ih on ih.item=tis.item and ih.house = tis.house and ih.bonusset=tis.bonusset
left join tblDBCItemVendorCost ivc on ivc.item = i.id
WHERE tis.house = ?
$itemExcludeSql
group by tis.item, tis.bonusset
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
            $usingVendor = $priceRow['vendorPrice'] && (intval($priceRow['vendorPrice'],10) < $prc) && ($priceRow['bonusset'] == '0');
            if ($usingVendor) {
                $prc = intval($priceRow['vendorPrice'],10);
            }

            $item_avg[$item] .= str_repeat(chr(0), 4 * $hx  - strlen($item_avg[$item])) . pack('L', $prc);
            $item_stddev[$item] .= str_repeat(chr(0), 4 * $hx - strlen($item_stddev[$item])) . pack('L', (!$usingVendor && $priceRow['pricestddev']) ? intval($priceRow['pricestddev'],10) : 0);
            $item_recent[$item] .= str_repeat(chr(0), 4 * $hx - strlen($item_recent[$item])) . pack('L', (!$usingVendor && $priceRow['pricerecent']) ? intval($priceRow['pricerecent'],10) : $prc);
            $item_days[$item] .= str_repeat(chr(255), $hx - strlen($item_days[$item])) . chr($priceRow['vendorprice'] ? 252 : min(251, intval($priceRow['since'],10)));
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
    $tocFile = sprintf($tocFile, Date('D, F j'), Date('Ymd'));

    $zip->addFromString("TheUndermineJournal/TheUndermineJournal.toc",$tocFile);
    $zip->addFile('../addon/libs/LibStub.lua',"TheUndermineJournal/libs/LibStub.lua");
    $zip->addFile('../addon/libs/LibRealmInfo.lua',"TheUndermineJournal/libs/LibRealmInfo.lua");
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
