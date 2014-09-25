<?php

chdir(__DIR__);

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
DebugMessage('Done!');

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
        DebugMessage("House ".str_pad($house, 5, ' ', STR_PAD_LEFT)." $snapshot data file corrupted! ".json_last_error_msg());
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
    $hordeHouse = $house * -1;

    $ourDb->begin_transaction();

    $stmt = $ourDb->prepare('select id, bid, item, house from tblAuction where house in (?,?)');
    $stmt->bind_param('ii',$house, $hordeHouse);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingIds = DBMapArray($result);

    $stmt = $ourDb->prepare('select id, species, breed, house from tblAuctionPet where house in (?,?)');
    $stmt->bind_param('ii',$house, $hordeHouse);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingPetIds = DBMapArray($result);

    $naiveMax = 0;
    $lowMax = -1;
    $highMax = -1;
    $hasRollOver = false;
    foreach ($json as $faction => &$factionData)
        if (isset($factionData['auctions']))
        {
            if ($faction == 'neutral')
                continue;

            $auctionCount = count($factionData['auctions']);

            for ($x = 0; $x < $auctionCount; $x++)
            {
                $auctionId = $factionData['auctions'][$x]['auc'];

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

    $sqlStart = 'replace into tblAuction (house, id, item, quantity, bid, buy, seller, rand, seed) values ';
    $sqlStartPet = 'replace into tblAuctionPet (house, id, species, breed, `level`, quality) values ';
    $sql = '';
    $sqlPet = '';

    $totalAuctions = 0;
    $itemInfo = array();
    $petInfo = array();
    $sellerInfo = array();

    foreach ($json as $faction => &$factionData)
        if (isset($factionData['auctions']))
        {
            if ($faction == 'neutral')
                continue;
            $factionHouse = ($faction == 'horde') ? ($house * -1) : $house;

            DebugMessage("House ".str_pad($house, 5, ' ', STR_PAD_LEFT)." parsing ".count($factionData['auctions'])." $faction auctions");

            $auctionCount = count($factionData['auctions']);
            for ($x = 0; $x < $auctionCount; $x++)
            {
                $auction =& $factionData['auctions'][$x];
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

            for ($x = 0; $x < $auctionCount; $x++)
            {
                $auction =& $factionData['auctions'][$x];

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

                        $itemInfo[$auction['item']]['a'][] = array('q' => $auction['quantity'], 'p' => $auction['buyout']);
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

                $thisSql = sprintf('(%d, %u, %u, %u, %u, %u, %u, %d, %d)',
                    $factionHouse,
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
                    $ourDb->query($sql);
                    $sql = '';
                }
                $sql .= ($sql == '' ? $sqlStart : ',') . $thisSql;

                if (isset($auction['petSpeciesId']))
                {
                    $thisSql = sprintf('(%d, %u, %u, %u, %u, %u)',
                        $factionHouse,
                        $auction['auc'],
                        $auction['petSpeciesId'],
                        $auction['petBreedId'],
                        $auction['petLevel'],
                        $auction['petQualityId']);

                    if (strlen($sqlPet) + 5 + strlen($thisSql) > $maxPacketSize)
                    {
                        $ourDb->query($sqlPet);
                        $sqlPet = '';
                    }
                    $sqlPet .= ($sqlPet == '' ? $sqlStartPet : ',') . $thisSql;
                }
            }

            if ($sql != '')
                $ourDb->query($sql);

            if ($sqlPet != '')
                $ourDb->query($sqlPet);

            $sql = <<<EOF
insert ignore into tblAuctionRare (house, id, prevseen) (
select a.house, a.id, tis.lastseen
from tblAuction a
left join tblItemSummary tis on tis.house=a.house and tis.item=a.item
where a.house = %d
and a.id > %d
%s
and ifnull(tis.lastseen, '2000-01-01') < timestampadd(day,-14,'%s'))
EOF;
            $sql = sprintf($sql, $factionHouse, $lastMax, $hasRollOver ? ' and a.id < 0x20000000 ' : '', $snapshotString);
            $ourDb->query($sql);

            // move out of loop once no longer using $factionHouse
            DebugMessage("House ".str_pad($factionHouse, 5, ' ', STR_PAD_LEFT)." updating ".count($itemInfo)." item info");
            foreach ($existingIds as &$oldRow)
                if (($oldRow['house'] == $factionHouse) && (!isset($existingPetIds[$oldRow['id']])) && (!isset($itemInfo[$oldRow['item']])))
                    $itemInfo[$oldRow['item']] = array('tq' => 0, 'a' => array());
            unset($oldRow);
            UpdateItemInfo($factionHouse, $itemInfo, $snapshot);
            $itemInfo = array();

            DebugMessage("House ".str_pad($factionHouse, 5, ' ', STR_PAD_LEFT)." updating ".count($petInfo)." pet info");
            foreach ($existingPetIds as &$oldRow)
                if (($oldRow['house'] == $factionHouse) && (!isset($petInfo[$oldRow['species']][$oldRow['breed']])))
                    $petInfo[$oldRow['species']][$oldRow['breed']] = array('tq' => 0, 'a' => array());
            unset($oldRow);
            UpdatePetInfo($factionHouse, $petInfo, $snapshot);
            $petInfo = array();

            DebugMessage("House ".str_pad($factionHouse, 5, ' ', STR_PAD_LEFT)." updating seller history");
            UpdateSellerInfo($sellerInfo, $snapshot);
            $sellerInfo = array();
        }

    if (count($existingIds) > 0)
    {
        DebugMessage("House ".str_pad($house, 5, ' ', STR_PAD_LEFT)." deleting ".count($existingIds)." auctions");

        $sqlStart = sprintf('delete from tblAuction where house in (%d,%d) and id in (', $house, $hordeHouse);
        $sql = '';

        foreach ($existingIds as $lostId => &$lostRow)
        {
            if (strlen($sql) + 10 + strlen($lostId) > $maxPacketSize)
            {
                $ourDb->query($sql.')');
                $ourDb->query(preg_replace('/\btblAuction\b/', 'tblAuctionPet', $sql, 1).')');
                $ourDb->query(preg_replace('/\btblAuction\b/', 'tblAuctionRare', $sql, 1).')');
                $sql = '';
            }
            $sql .= ($sql == '' ? $sqlStart : ',') . $lostId;
        }
        unset($lostRow);

        if ($sql != '')
        {
            $ourDb->query($sql.')');
            $ourDb->query(preg_replace('/\btblAuction\b/', 'tblAuctionPet', $sql, 1).')');
            $ourDb->query(preg_replace('/\btblAuction\b/', 'tblAuctionRare', $sql, 1).')');
        }
    }

    $ourDb->commit();
    $ourDb->close();

    MCSetHouse($house, 'ts', $snapshot);
    MCSetHouse(-1 * $house, 'ts', $snapshot);

    DebugMessage("House ".str_pad($house, 5, ' ', STR_PAD_LEFT)." finished with $totalAuctions auctions in ".round(microtime(true) - $startTimer,2)." sec");

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
                     $db->query(sprintf('update tblSeller set lastseen = \'%s\' where id in (%s)', $snapshotString, implode(',',$lastSeenIds)));

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
                $db->query(sprintf('update tblSeller set lastseen = \'%s\' where id in (%s)', $snapshotString, implode(',',$lastSeenIds)));

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
                $db->query($sql);

                $sql = $sqlStart;
                $namesInQuery = 0;
            }
            $sql .= ($namesInQuery++ > 0 ? ',' : '') . $insertBit;
        }

        if ($namesInQuery > 0)
            $db->query($sql);
    }

    if ($neededInserts)
        GetSellerIds($region, $sellerInfo, $snapshot, true);

}

function UpdateSellerInfo(&$sellerInfo, $snapshot)
{
    global $db, $maxPacketSize;

    $snapshotString = Date('Y-m-d H:i:s', $snapshot);
    $realms = array_keys($sellerInfo);

    $sqlStart = 'insert into tblSellerHistory (seller, snapshot, `new`, `total`) values ';
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
                $db->query($sql);
                $sql = '';
            }
            $sql .= ($sql == '' ? $sqlStart : ',') . $sqlBit;
        }
        unset($info);
    }

    if ($sql != '')
        $db->query($sql);
}

function UpdateItemInfo($factionHouse, &$itemInfo, $snapshot)
{
    global $db, $maxPacketSize;

    $month = (2014 - intval(Date('Y', $snapshot),10)) * 12 + intval(Date('m', $snapshot),10);
    $day = Date('d', $snapshot);

    $snapshotString = Date('Y-m-d H:i:s', $snapshot);
    $sqlStart = 'insert into tblItemSummary (house, item, price, quantity, lastseen) values ';
    $sqlEnd = ' on duplicate key update quantity=values(quantity), price=if(quantity=0,price,values(price)), lastseen=if(quantity=0,lastseen,values(lastseen))';
    $sql = '';

    $sqlHistoryStart = 'replace into tblItemHistory (house, item, price, quantity, snapshot) values ';
    $sqlHistory = '';

    $sqlDeepStart = sprintf('insert into tblItemHistoryMonthly (house, item, mktslvr%1$s, qty%1$s, `month`) values ', $day);
    $sqlDeepEnd = sprintf(' on duplicate key update mktslvr%1$s=if(values(qty%1$s) > ifnull(qty%1$s,0), values(mktslvr%1$s), mktslvr%1$s), qty%1$s=if(values(qty%1$s) > ifnull(qty%1$s,0), values(qty%1$s), qty%1$s)', $day);
    $sqlDeep = '';

    foreach ($itemInfo as $item => &$info)
    {
        $price = GetMarketPrice($info);
        $sqlBit = sprintf('(%d,%u,%u,%u,\'%s\')', $factionHouse, $item, $price, $info['tq'], $snapshotString);
        if (strlen($sql) + strlen($sqlBit) + strlen($sqlEnd) + 5 > $maxPacketSize)
        {
            $db->query($sql.$sqlEnd);
            $sql = '';
        }
        $sql .= ($sql == '' ? $sqlStart : ',') . $sqlBit;

        if ($info['tq'] > 0)
        {
            if (strlen($sqlHistory) + strlen($sqlBit) + 5 > $maxPacketSize)
            {
                $db->query($sqlHistory);
                $sqlHistory = '';
            }
            $sqlHistory .= ($sqlHistory == '' ? $sqlHistoryStart : ',') . $sqlBit;

            $sqlDeepBit = sprintf('(%d,%u,%u,%u,%u)', $factionHouse, $item, round($price/100), $info['tq'], $month);
            if (strlen($sqlDeep) + strlen($sqlDeepBit) + strlen($sqlDeepEnd) + 5 > $maxPacketSize)
            {
                $db->query($sqlDeep.$sqlDeepEnd);
                $sqlDeep = '';
            }
            $sqlDeep .= ($sqlDeep == '' ? $sqlDeepStart : ',') . $sqlDeepBit;
        }
    }
    unset($info);

    if ($sql != '')
        $db->query($sql.$sqlEnd);
    if ($sqlHistory != '')
        $db->query($sqlHistory);
    if ($sqlDeep != '')
        $db->query($sqlDeep.$sqlDeepEnd);
}

function UpdatePetInfo($factionHouse, &$petInfo, $snapshot)
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
            $sqlBit = sprintf('(%d,%u,%u,%u,%u,\'%s\')', $factionHouse, $species, $breed, $price, $info['tq'], $snapshotString);
            if (strlen($sql) + strlen($sqlBit) + strlen($sqlEnd) + 5 > $maxPacketSize)
            {
                $db->query($sql.$sqlEnd);
                $sql = '';
            }
            $sql .= ($sql == '' ? $sqlStart : ',') . $sqlBit;

            if ($info['tq'] > 0)
            {
                if (strlen($sqlHistory) + strlen($sqlBit) + 5 > $maxPacketSize)
                {
                    $db->query($sqlHistory);
                    $sqlHistory = '';
                }
                $sqlHistory .= ($sqlHistory == '' ? $sqlHistoryStart : ',') . $sqlBit;
            }
        }
        unset($info);
    }
    unset($breeds);

    if ($sql != '')
        $db->query($sql.$sqlEnd);
    if ($sqlHistory != '')
        $db->query($sqlHistory);
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
