<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');

RunMeNTimes(2);
CatchKill();

define('SNAPSHOT_PATH', '/var/newsstand/snapshots/');

ini_set('memory_limit','512M');

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

$stmt = $db->prepare('select house, region from tblRealm group by house');
$stmt->execute();
$result = $stmt->get_result();
$houseRegionCache = DBMapArray($result);
$stmt->close();

$stmt = $db->prepare('select id, region, name, ifnull(ownerrealm, replace(name, \' \', \'\')) as ownerrealm from tblRealm');
$stmt->execute();
$result = $stmt->get_result();
$ownerRealmCache = DBMapArray($result, array('region','ownerrealm'));
$stmt->close();

$maxPacketSize = 0;
$stmt = $db->prepare('show variables like \'max_allowed_packet\'');
$stmt->execute();
$stmt->bind_result($nonsense, $maxPacketSize);
$stmt->fetch();
$stmt->close();
unset($nonsense);

$loopStart = time();
$toSleep = 0;
while ((!$caughtKill) && (time() < ($loopStart + 60 * 30)))
{
    heartbeat();
    sleep(min($toSleep,10));
    if ($caughtKill)
        break;
    ob_start();
    $toSleep = NextDataFile();
    ob_end_flush();
    if ($toSleep === false)
        break;
}
DebugMessage('Done! Started '.TimeDiff($startTime));

function NextDataFile()
{
    $dir = scandir(substr(SNAPSHOT_PATH, 0, -1), SCANDIR_SORT_ASCENDING);
    $lockFail = false;
    $gotFile = false;
    foreach ($dir as $fileName)
    {
        if (preg_match('/^(\d+)-(\d+)\.json$/', $fileName, $res))
        {
            if (($handle = fopen(SNAPSHOT_PATH.$fileName, 'rb')) === false)
                continue;

            if (!flock($handle, LOCK_EX | LOCK_NB))
            {
                $lockFail = true;
                fclose($handle);
                continue;
            }

            if (feof($handle))
            {
                fclose($handle);
                unlink(SNAPSHOT_PATH.$fileName);
                continue;
            }

            $gotFile = $fileName;
            break;
        }
    }
    unset($dir);

    if (!$gotFile)
        return $lockFail ? 3 : 10;

    $snapshot = intval($res[1],10);
    $house = intval($res[2],10);

    DebugMessage("House ".str_pad($house, 5, ' ', STR_PAD_LEFT)." data file from ".TimeDiff($snapshot, array('parts' => 2, 'precision' => 'second')));
    $json = json_decode(fread($handle, filesize(SNAPSHOT_PATH.$fileName)), true);

    ftruncate($handle, 0);
    fclose($handle);
    unlink(SNAPSHOT_PATH.$fileName);

    if (json_last_error() != JSON_ERROR_NONE)
    {
        DebugMessage("House ".str_pad($house, 5, ' ', STR_PAD_LEFT)." $snapshot data file corrupted! ".json_last_error_msg(), E_USER_WARNING);
        return 0;
    }

    ParseAuctionData($house, $snapshot, $json);
    return 0;
}

function ParseAuctionData($house, $snapshot, &$json)
{
    global $maxPacketSize;
    global $houseRegionCache;

    $snapshotString = Date('Y-m-d H:i:s', $snapshot);
    $startTimer = microtime(true);

    $ourDb = DBConnect(true);

    $region = $houseRegionCache[$house]['region'];

    $stmt = $ourDb->prepare('select id, bid, item from tblAuction where house = ?');
    $stmt->bind_param('i',$house);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingIds = DBMapArray($result);
    $stmt->close();

    $stmt = $ourDb->prepare('select id, species, breed from tblAuctionPet where house = ?');
    $stmt->bind_param('i',$house);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingPetIds = DBMapArray($result);
    $stmt->close();

    $naiveMax = 0;
    $lowMax = -1;
    $highMax = -1;
    $hasRollOver = false;
    if (isset($json['auctions']['auctions'])) {
        $auctionCount = count($json['auctions']['auctions']);

        for ($x = 0; $x < $auctionCount; $x++)
        {
            $auctionId = $json['auctions']['auctions'][$x]['auc'];

            $naiveMax = max($naiveMax, $auctionId);
            if ($auctionId < 0x20000000)
                $lowMax = max($lowMax, $auctionId);
            if ($auctionId > 0x60000000)
                $highMax = max($highMax, $auctionId);
        }
    }

    if (($lowMax != -1) && ($highMax != -1))
    {
        $hasRollOver = true;
        $max = $lowMax; // rolled over
    }
    else
        $max = $naiveMax;

    unset($naiveMax, $lowMax, $highMax);

    $stmt = $ourDb->prepare('update tblSnapshot set maxid = ? where house = ? and updated = ?');
    $stmt->bind_param('iis', $max, $house, $snapshotString);
    $stmt->execute();
    $stmt->close();

    $stmt = $ourDb->prepare('select ifnull(maxid,0) from tblSnapshot s where house = ? and updated = (select max(s2.updated) from tblSnapshot s2 where s2.house = s.house and s2.updated < ?)');
    $stmt->bind_param('is', $house, $snapshotString);
    $stmt->execute();
    $stmt->bind_result($lastMax);
    if ($stmt->fetch() !== true)
        $lastMax = 0;
    $stmt->close();

    $stmt = $ourDb->prepare('select unix_timestamp(updated) updated, maxid from tblSnapshot where house = ? and updated between timestampadd(hour, -49, ?) and ? order by updated asc');
    $stmt->bind_param('iss', $house, $snapshotString, $snapshotString);
    $stmt->execute();
    $result = $stmt->get_result();
    $snapshotList = DBMapArray($result, null);
    $stmt->close();

    $sqlStart = 'replace into tblAuction (house, id, item, quantity, bid, buy, seller, rand, seed) values ';
    $sqlStartPet = 'replace into tblAuctionPet (house, id, species, breed, `level`, quality) values ';

    $totalAuctions = 0;
    $itemInfo = array();
    $petInfo = array();
    $sellerInfo = array();

    if (isset($json['auctions']['auctions'])) {
        $auctionCount = count($json['auctions']['auctions']);
        DebugMessage("House ".str_pad($house, 5, ' ', STR_PAD_LEFT)." parsing $auctionCount auctions");
        $ourDb->begin_transaction();

        for ($x = 0; $x < $auctionCount; $x++) {
            $auction =& $json['auctions']['auctions'][$x];
            if ($auction['owner'] == '???')
                continue;
            if (!isset($sellerInfo[$auction['ownerRealm']]))
                $sellerInfo[$auction['ownerRealm']] = array();
            if (!isset($sellerInfo[$auction['ownerRealm']][$auction['owner']]))
                $sellerInfo[$auction['ownerRealm']][$auction['owner']] = array(
                    'new' => 0,
                    'total' => 0,
                    'id' => 0,
                );
            $sellerInfo[$auction['ownerRealm']][$auction['owner']]['total']++;
            if ((!$hasRollOver || $auction['auc'] < 0x20000000) && ($auction['auc'] > $lastMax))
                $sellerInfo[$auction['ownerRealm']][$auction['owner']]['new']++;
        }

        GetSellerIds($region, $sellerInfo, $snapshot);

        $sql = $sqlPet = '';

        for ($x = 0; $x < $auctionCount; $x++)
        {
            $auction =& $json['auctions']['auctions'][$x];

            $totalAuctions++;
            if ($auction['buyout'] != 0)
            {
                if (isset($auction['petSpeciesId']))
                {
                    if ($auction['petBreedId'] > 12) $auction['petBreedId'] -= 10; // squash gender

                    if (!isset($petInfo[$auction['petSpeciesId']][$auction['petBreedId']]))
                        $petInfo[$auction['petSpeciesId']][$auction['petBreedId']] = array('a' => array(), 'tq' => 0);

                    $petInfo[$auction['petSpeciesId']][$auction['petBreedId']]['a'][] = array('q' => $auction['quantity'], 'p' => $auction['buyout']);
                    $petInfo[$auction['petSpeciesId']][$auction['petBreedId']]['tq'] += $auction['quantity'];
                }
                else
                {
                    if (!isset($itemInfo[$auction['item']]))
                        $itemInfo[$auction['item']] = array('a' => array(), 'tq' => 0);

                    $itemInfo[$auction['item']]['a'][] = array('q' => $auction['quantity'], 'p' => $auction['buyout'], 'age' => GetAuctionAge($auction['auc'], $snapshot, $snapshotList));
                    $itemInfo[$auction['item']]['tq'] += $auction['quantity'];
                }
            }

            if (isset($existingIds[$auction['auc']]))
            {
                $needUpdate = ($auction['bid'] != $existingIds[$auction['auc']]['bid']);
                unset($existingIds[$auction['auc']]);
                unset($existingPetIds[$auction['auc']]);
                if (!$needUpdate)
                    continue;
            }

            $thisSql = sprintf('(%u, %u, %u, %u, %u, %u, %u, %d, %d)',
                $house,
                $auction['auc'],
                $auction['item'],
                $auction['quantity'],
                $auction['bid'],
                $auction['buyout'],
                $auction['owner'] == '???' ? 0 : $sellerInfo[$auction['ownerRealm']][$auction['owner']]['id'],
                $auction['rand'],
                $auction['seed']);
            if (strlen($sql) + 5 + strlen($thisSql) > $maxPacketSize)
            {
                DBQueryWithError($ourDb, $sql);
                $sql = '';
            }
            $sql .= ($sql == '' ? $sqlStart : ',') . $thisSql;

            if (isset($auction['petSpeciesId']))
            {
                $thisSql = sprintf('(%u, %u, %u, %u, %u, %u)',
                    $house,
                    $auction['auc'],
                    $auction['petSpeciesId'],
                    $auction['petBreedId'],
                    $auction['petLevel'],
                    $auction['petQualityId']);

                if (strlen($sqlPet) + 5 + strlen($thisSql) > $maxPacketSize)
                {
                    DBQueryWithError($ourDb, $sqlPet);
                    $sqlPet = '';
                }
                $sqlPet .= ($sqlPet == '' ? $sqlStartPet : ',') . $thisSql;
            }
        }

        if ($sql != '')
            DBQueryWithError($ourDb,$sql);

        if ($sqlPet != '')
            DBQueryWithError($ourDb, $sqlPet);

        $ourDb->commit();

        $sql = <<<EOF
insert ignore into tblAuctionRare (house, id, prevseen) (
select a.house, a.id, tis.lastseen
from tblAuction a
left join tblItemSummary tis on tis.house=a.house and tis.item=a.item
where a.house = %d
and a.id > %d
and a.item not in (82800)
%s
and ifnull(tis.lastseen, '2000-01-01') < timestampadd(day,-14,'%s'))
EOF;
        $sql = sprintf($sql, $house, $lastMax, $hasRollOver ? ' and a.id < 0x20000000 ' : '', $snapshotString);
        DBQueryWithError($ourDb,$sql);
    }

    $preDeleted = count($itemInfo);
    foreach ($existingIds as &$oldRow)
        if ((!isset($existingPetIds[$oldRow['id']])) && (!isset($itemInfo[$oldRow['item']])))
            $itemInfo[$oldRow['item']] = array('tq' => 0, 'a' => array());
    unset($oldRow);
    DebugMessage("House ".str_pad($house, 5, ' ', STR_PAD_LEFT)." updating ".count($itemInfo)." item info (including ".(count($itemInfo) - $preDeleted)." no longer available)");
    UpdateItemInfo($house, $itemInfo, $snapshot);

    $preDeleted = count($petInfo);
    foreach ($existingPetIds as &$oldRow)
        if (!isset($petInfo[$oldRow['species']][$oldRow['breed']]))
            $petInfo[$oldRow['species']][$oldRow['breed']] = array('tq' => 0, 'a' => array());
    unset($oldRow);
    DebugMessage("House ".str_pad($house, 5, ' ', STR_PAD_LEFT)." updating ".count($petInfo)." pet info (including ".(count($petInfo) - $preDeleted)." no longer available)");
    UpdatePetInfo($house, $petInfo, $snapshot);

    DebugMessage("House ".str_pad($house, 5, ' ', STR_PAD_LEFT)." updating seller history");
    UpdateSellerInfo($sellerInfo, $snapshot);

    if (count($existingIds) > 0)
    {
        DebugMessage("House ".str_pad($house, 5, ' ', STR_PAD_LEFT)." deleting ".count($existingIds)." auctions");

        $sqlStart = sprintf('delete from tblAuction where house = %d and id in (', $house);
        $sql = '';

        foreach ($existingIds as $lostId => &$lostRow)
        {
            if (strlen($sql) + 10 + strlen($lostId) > $maxPacketSize)
            {
                DBQueryWithError($ourDb, $sql.')');
                DBQueryWithError($ourDb, preg_replace('/\btblAuction\b/', 'tblAuctionPet', $sql, 1).')');
                DBQueryWithError($ourDb, preg_replace('/\btblAuction\b/', 'tblAuctionRare', $sql, 1).')');
                $sql = '';
            }
            $sql .= ($sql == '' ? $sqlStart : ',') . $lostId;
        }
        unset($lostRow);

        if ($sql != '')
        {
            DBQueryWithError($ourDb, $sql.')');
            DBQueryWithError($ourDb, preg_replace('/\btblAuction\b/', 'tblAuctionPet', $sql, 1).')');
            DBQueryWithError($ourDb, preg_replace('/\btblAuction\b/', 'tblAuctionRare', $sql, 1).')');
        }
    }

    $ourDb->close();

    MCSetHouse($house, 'ts', $snapshot);

    DebugMessage("House ".str_pad($house, 5, ' ', STR_PAD_LEFT)." finished with $totalAuctions auctions in ".round(microtime(true) - $startTimer,2)." sec");
}

function GetAuctionAge($id, $now, &$snapshotList)
{
    $imin = 0;
    $imax = count($snapshotList) - 1;

    if ($imax <= 0)
        return 0;

    if ($snapshotList[$imin]['maxid'] > $snapshotList[$imax]['maxid']) {
        // have rollover, fix it
        for ($x = 0; $x < $imax; $x++) {
            if ($snapshotList[$x]['maxid'] > 0x40000000) {
                $snapshotList[$x]['maxid'] -= 0x80000000;
            }
        }
    }

    if (($snapshotList[0]['maxid'] < 0) && ($id > 0x40000000)) {
        // have rollover, fix order
        $id -= 0x80000000;
    }

    if ($snapshotList[$imax]['maxid'] < $id)
        return 0;

    while ($imin < $imax) {
        $imid = floor(($imin + $imax) / 2);
        if ($imid >= $imax) {
            break;
        }
        if ($snapshotList[$imid]['maxid'] < $id) {
            $imin = $imid + 1;
        } else {
            $imax = $imid;
        }
    }

    if ($imin == 0) {
        // id is older than oldest snapshot, assume just as old
        $seconds = $now - $snapshotList[$imin]['updated'];
    } else {
        $seconds = floor($now - (
                $snapshotList[$imin-1]['updated'] +
                ($snapshotList[$imin]['updated'] - $snapshotList[$imin-1]['updated']) *
                (($id - $snapshotList[$imin-1]['maxid']) / ($snapshotList[$imin]['maxid'] - $snapshotList[$imin-1]['maxid']))
            ));
    }

    return min(255, max(0, floor($seconds / (48 * 60 * 60) * 255)));
}

function GetSellerIds($region, &$sellerInfo, $snapshot, $afterInsert = false)
{
    global $db, $ownerRealmCache, $maxPacketSize;

    $snapshotString = Date('Y-m-d H:i:s', $snapshot);
    $workingRealms = array_keys($sellerInfo);
    $neededInserts = false;

    for ($r = 0; $r < count($workingRealms); $r++)
    {
        if (!isset($ownerRealmCache[$region][$workingRealms[$r]]))
            continue;

        $realmName = $workingRealms[$r];

        $realmId = $ownerRealmCache[$region][$realmName]['id'];

        $sqlStart = "select name, id from tblSeller where realm = $realmId and name in (";
        $sql = $sqlStart;
        $namesInQuery = 0;
        $names = array_keys($sellerInfo[$realmName]);
        $nameCount = count($names);
        $needInserts = false;

        for ($s = 0; $s < $nameCount; $s++)
        {
            if ($sellerInfo[$realmName][$names[$s]]['id'] != 0)
                continue;

            $nameEscaped = '\''.$db->real_escape_string($names[$s]).'\'';
            if (strlen($sql) + strlen($nameEscaped) + 5 > $maxPacketSize)
            {
                $sql .= ')';

                $stmt = $db->prepare($sql);
                $stmt->execute();
                $result = $stmt->get_result();
                $someIds = DBMapArray($result, null);
                $foundNames = 0;
                $lastSeenIds = array();

                for ($n = 0; $n < count($someIds); $n++)
                    if (isset($sellerInfo[$realmName][$someIds[$n]['name']]))
                    {
                        $sellerInfo[$realmName][$someIds[$n]['name']]['id'] = $someIds[$n]['id'];
                        $foundNames++;
                        if ($sellerInfo[$realmName][$someIds[$n]['name']]['new'] > 0)
                            $lastSeenIds[] = $someIds[$n]['id'];
                    }

                if (count($lastSeenIds) > 0 && !$afterInsert)
                    DBQueryWithError($db, sprintf('update tblSeller set lastseen = \'%s\' where id in (%s)', $snapshotString, implode(',',$lastSeenIds)));

                $needInserts |= ($foundNames < $namesInQuery);

                $sql = $sqlStart;
                $namesInQuery = 0;
            }
            $sql .= ($namesInQuery++ > 0 ? ',' : '') . $nameEscaped;
        }

        if ($namesInQuery > 0)
        {
            $sql .= ')';

            $stmt = $db->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $someIds = DBMapArray($result, null);
            $foundNames = 0;
            $lastSeenIds = array();

            for ($n = 0; $n < count($someIds); $n++)
                if (isset($sellerInfo[$realmName][$someIds[$n]['name']]))
                {
                    $sellerInfo[$realmName][$someIds[$n]['name']]['id'] = $someIds[$n]['id'];
                    $foundNames++;
                    if ($sellerInfo[$realmName][$someIds[$n]['name']]['new'] > 0)
                        $lastSeenIds[] = $someIds[$n]['id'];
                }

            if (count($lastSeenIds) > 0 && !$afterInsert)
                DBQueryWithError($db, sprintf('update tblSeller set lastseen = \'%s\' where id in (%s)', $snapshotString, implode(',',$lastSeenIds)));

            $needInserts |= ($foundNames < $namesInQuery);
        }

        if ($afterInsert || !$needInserts)
            continue;

        $neededInserts = true;

        $sqlStart = "insert ignore into tblSeller (realm, name, firstseen, lastseen) values ";
        $sql = $sqlStart;
        $namesInQuery = 0;

        for ($s = 0; $s < $nameCount; $s++)
        {
            if ($sellerInfo[$realmName][$names[$s]]['id'] != 0)
                continue;

            $insertBit = sprintf('(%1$d,\'%2$s\',\'%3$s\',\'%3$s\')', $realmId, $db->real_escape_string($names[$s]), $snapshotString);
            if (strlen($sql) + strlen($insertBit) + 5 > $maxPacketSize)
            {
                DBQueryWithError($db, $sql);

                $sql = $sqlStart;
                $namesInQuery = 0;
            }
            $sql .= ($namesInQuery++ > 0 ? ',' : '') . $insertBit;
        }

        if ($namesInQuery > 0)
            DBQueryWithError($db, $sql);
    }

    if ($neededInserts)
        GetSellerIds($region, $sellerInfo, $snapshot, true);

}

function UpdateSellerInfo(&$sellerInfo, $snapshot)
{
    global $db, $maxPacketSize;

    $snapshotString = Date('Y-m-d H:i:s', $snapshot);
    $realms = array_keys($sellerInfo);

    $sqlStart = 'insert ignore into tblSellerHistory (seller, snapshot, `new`, `total`) values ';
    $sql = '';

    for ($r = 0; $r < count($realms); $r++)
    {
        foreach ($sellerInfo[$realms[$r]] as &$info)
        {
            if ($info['id'] == 0)
                continue;

            $sqlBit = sprintf('(%d,\'%s\',%d,%d)', $info['id'], $snapshotString, $info['new'], $info['total']);
            if (strlen($sql) + strlen($sqlBit) + 5 > $maxPacketSize)
            {
                DBQueryWithError($db, $sql);
                $sql = '';
            }
            $sql .= ($sql == '' ? $sqlStart : ',') . $sqlBit;
        }
        unset($info);
    }

    if ($sql != '')
        DBQueryWithError($db, $sql);
}

function UpdateItemInfo($house, &$itemInfo, $snapshot)
{
    global $db, $maxPacketSize;

    $month = (2014 - intval(Date('Y', $snapshot),10)) * 12 + intval(Date('m', $snapshot),10);
    $day = Date('d', $snapshot);

    $snapshotString = Date('Y-m-d H:i:s', $snapshot);
    $sqlStart = 'insert into tblItemSummary (house, item, price, quantity, lastseen, age) values ';
    $sqlEnd = ' on duplicate key update quantity=values(quantity), price=if(quantity=0,price,values(price)), lastseen=if(quantity=0,lastseen,values(lastseen)), age=values(age)';
    $sql = '';

    $sqlHistoryStart = 'replace into tblItemHistory (house, item, price, quantity, snapshot, age) values ';
    $sqlHistory = '';

    $sqlDeepStart = sprintf('insert into tblItemHistoryMonthly (house, item, mktslvr%1$s, qty%1$s, `month`) values ', $day);
    $sqlDeepEnd = sprintf(' on duplicate key update mktslvr%1$s=if(values(qty%1$s) > ifnull(qty%1$s,0), values(mktslvr%1$s), mktslvr%1$s), qty%1$s=if(values(qty%1$s) > ifnull(qty%1$s,0), values(qty%1$s), qty%1$s)', $day);
    $sqlDeep = '';

    foreach ($itemInfo as $item => &$info)
    {
        $price = GetMarketPrice($info);
        $age = GetAverageAge($info);

        $sqlBit = sprintf('(%d,%u,%u,%u,\'%s\',%u)', $house, $item, $price, $info['tq'], $snapshotString, $age);
        if (strlen($sql) + strlen($sqlBit) + strlen($sqlEnd) + 5 > $maxPacketSize)
        {
            DBQueryWithError($db, $sql.$sqlEnd);
            $sql = '';
        }
        $sql .= ($sql == '' ? $sqlStart : ',') . $sqlBit;

        if ($info['tq'] > 0)
        {
            if (strlen($sqlHistory) + strlen($sqlBit) + 5 > $maxPacketSize)
            {
                DBQueryWithError($db, $sqlHistory);
                $sqlHistory = '';
            }
            $sqlHistory .= ($sqlHistory == '' ? $sqlHistoryStart : ',') . $sqlBit;

            $sqlDeepBit = sprintf('(%d,%u,%u,%u,%u)', $house, $item, round($price/100), $info['tq'], $month);
            if (strlen($sqlDeep) + strlen($sqlDeepBit) + strlen($sqlDeepEnd) + 5 > $maxPacketSize)
            {
                DBQueryWithError($db, $sqlDeep.$sqlDeepEnd);
                $sqlDeep = '';
            }
            $sqlDeep .= ($sqlDeep == '' ? $sqlDeepStart : ',') . $sqlDeepBit;
        }
    }
    unset($info);

    if ($sql != '')
        DBQueryWithError($db, $sql.$sqlEnd);
    if ($sqlHistory != '')
        DBQueryWithError($db, $sqlHistory);
    if ($sqlDeep != '')
        DBQueryWithError($db, $sqlDeep.$sqlDeepEnd);
}

function UpdatePetInfo($house, &$petInfo, $snapshot)
{
    global $db, $maxPacketSize;

    $snapshotString = Date('Y-m-d H:i:s', $snapshot);
    $sqlStart = 'insert into tblPetSummary (house, species, breed, price, quantity, lastseen) values ';
    $sqlEnd = ' on duplicate key update quantity=values(quantity), price=if(quantity=0,price,values(price)), lastseen=if(quantity=0,lastseen,values(lastseen))';
    $sql = '';

    $sqlHistoryStart = 'replace into tblPetHistory (house, species, breed, price, quantity, snapshot) values ';
    $sqlHistory = '';

    foreach ($petInfo as $species => &$breeds)
    {
        foreach ($breeds as $breed => &$info)
        {
            $price = GetMarketPrice($info);
            $sqlBit = sprintf('(%d,%u,%u,%u,%u,\'%s\')', $house, $species, $breed, $price, $info['tq'], $snapshotString);
            if (strlen($sql) + strlen($sqlBit) + strlen($sqlEnd) + 5 > $maxPacketSize)
            {
                DBQueryWithError($db, $sql.$sqlEnd);
                $sql = '';
            }
            $sql .= ($sql == '' ? $sqlStart : ',') . $sqlBit;

            if ($info['tq'] > 0)
            {
                if (strlen($sqlHistory) + strlen($sqlBit) + 5 > $maxPacketSize)
                {
                    DBQueryWithError($db, $sqlHistory);
                    $sqlHistory = '';
                }
                $sqlHistory .= ($sqlHistory == '' ? $sqlHistoryStart : ',') . $sqlBit;
            }
        }
        unset($info);
    }
    unset($breeds);

    if ($sql != '')
        DBQueryWithError($db, $sql.$sqlEnd);
    if ($sqlHistory != '')
        DBQueryWithError($db, $sqlHistory);
}

function GetAverageAge(&$info)
{
    if ($info['tq'] == 0)
        return 0;

    $s = 0;
    $c = count($info['a']) - 1;
    for ($x = 0; $x < $c; $x++) {
        $s += $info['a'][$x]['age'];
    }

    return floor($s / ($c + 1));
}

function GetMarketPrice(&$info)
{
    if ($info['tq'] == 0)
        return 0;

    usort($info['a'], 'MarketPriceSort');
    $gq = 0;
    $gp = 0;
    $x = 0;
    while ($gq < ceil($info['tq'] * 0.15))
    {
        $gq += $info['a'][$x]['q'];
        $gp += $info['a'][$x]['p'];
        $x++;
    }
    return ceil($gp/$gq);
}

function MarketPriceSort($a,$b)
{
    $ap = ceil($a['p']/$a['q']);
    $bp = ceil($b['p']/$b['q']);
    if ($ap - $bp != 0)
        return ($ap - $bp);
    return ($a['q'] - $b['q']);
}

function DBQueryWithError(&$db, $sql)
{
    $queryOk = false;
    $retryCount = 0;

    while (!($queryOk = $db->query($sql))) {
        if ($db->errno == 1213) { // deadlock
            if ($retryCount++ >= 3) {
                break;
            }
            sleep($retryCount);
        } else {
            break;
        }
    }

    if (!$queryOk) {
        DebugMessage("SQL error: ".$db->errno.' '.$db->error." - ".substr(preg_replace('/[\r\n]/', ' ', $sql), 0, 500), E_USER_WARNING);
    }

    return $queryOk;
}