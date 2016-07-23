<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');

RunMeNTimes(2);
CatchKill();

define('SNAPSHOT_PATH', '/var/newsstand/snapshots/parse/');
define('MAX_BONUSES', 6); // is a count, 1 through N

define('EXISTING_SQL', 'SELECT a.id, a.bid, a.buy, a.timeleft+0 timeleft, concat_ws(\':\', a.item, ifnull(ae.bonusset,0)) infokey FROM tblAuction a LEFT JOIN tblAuctionExtra ae on a.house=ae.house and a.id=ae.id WHERE a.house = ?');
define('EXISTING_COL_BID', 0);
define('EXISTING_COL_BUY', 1);
define('EXISTING_COL_TIMELEFT', 2);
define('EXISTING_COL_INFOKEY', 3);

ini_set('memory_limit', '768M');

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

if (APIMaintenance()) {
    DebugMessage('API Maintenance in progress, not parsing snapshots!', E_USER_NOTICE);
    exit;
}

$stmt = $db->prepare('SELECT house, region FROM tblRealm GROUP BY house');
$stmt->execute();
$result = $stmt->get_result();
$houseRegionCache = DBMapArray($result);
$stmt->close();

$stmt = $db->prepare('SELECT id, region, name, ifnull(ownerrealm, replace(name, \' \', \'\')) AS ownerrealm FROM tblRealm');
$stmt->execute();
$result = $stmt->get_result();
$ownerRealmCache = DBMapArray($result, array('region', 'ownerrealm'));
$stmt->close();

$auctionExtraItemsCache = [];
$stmt = $db->prepare('SELECT id FROM tblDBCItem WHERE `class` in (2,4) AND `auctionable` = 1');
$stmt->execute();
$z = null;
$stmt->bind_result($z);
while ($stmt->fetch()) {
    $auctionExtraItemsCache[$z] = $z;
}
$stmt->close();

$bonusSetMemberCache = [];
$stmt = $db->prepare('SELECT id FROM tblDBCItemBonus WHERE `flags` & 1');
$stmt->execute();
$z = null;
$stmt->bind_result($z);
while ($stmt->fetch()) {
    $bonusSetMemberCache[$z] = $z;
}
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
while ((!$caughtKill) && (time() < ($loopStart + 60 * 30))) {
    heartbeat();
    sleep(min($toSleep, 10));
    if ($caughtKill || APIMaintenance()) {
        break;
    }
    ob_start();
    $toSleep = NextDataFile();
    ob_end_flush();
    if ($toSleep === false) {
        break;
    }
}
DebugMessage('Done! Started ' . TimeDiff($startTime));

function NextDataFile()
{
    $dir = scandir(substr(SNAPSHOT_PATH, 0, -1), SCANDIR_SORT_ASCENDING);
    $lockFail = false;
    $gotFile = false;
    foreach ($dir as $fileName) {
        if (preg_match('/^(\d+)-(\d+)\.json$/', $fileName, $res)) {
            if (($handle = fopen(SNAPSHOT_PATH . $fileName, 'rb')) === false) {
                continue;
            }

            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                $lockFail = true;
                fclose($handle);
                continue;
            }

            if (feof($handle)) {
                fclose($handle);
                unlink(SNAPSHOT_PATH . $fileName);
                continue;
            }

            $snapshot = intval($res[1], 10);
            $house = intval($res[2], 10);

            if (!MCHouseLock($house, 3)) {
                $lockFail = true;
                continue;
            }

            $gotFile = $fileName;
            break;
        }
    }
    unset($dir);

    if (!$gotFile) {
        return $lockFail ? 3 : 10;
    }

    DebugMessage(
        "House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " data file from " . TimeDiff(
            $snapshot, array(
                'parts'     => 2,
                'precision' => 'second'
            )
        )
    );
    $json = json_decode(fread($handle, filesize(SNAPSHOT_PATH . $fileName)), true);

    ftruncate($handle, 0);
    fclose($handle);
    unlink(SNAPSHOT_PATH . $fileName);

    if (json_last_error() != JSON_ERROR_NONE) {
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " $snapshot data file corrupted! " . json_last_error_msg(), E_USER_WARNING);
        MCHouseUnlock($house);
        return 0;
    }

    ParseAuctionData($house, $snapshot, $json);
    MCHouseUnlock($house);
    return 0;
}

function ParseAuctionData($house, $snapshot, &$json)
{
    global $maxPacketSize;
    global $houseRegionCache;
    global $auctionExtraItemsCache;
    global $TIMELEFT_ENUM;

    $snapshotString = date('Y-m-d H:i:s', $snapshot);
    $startTimer = microtime(true);

    $ourDb = DBConnect(true);

    $region = $houseRegionCache[$house]['region'];

    $existingIds = [];
    $stmt = $ourDb->prepare(EXISTING_SQL);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $id = $bid = $buy = $timeLeft = $infoKey = null;
    $stmt->bind_result($id, $bid, $buy, $timeLeft, $infoKey);
    while ($stmt->fetch()) {
        $existingIds[$id] = [$bid, $buy, $timeLeft, $infoKey];
    }
    $stmt->close();

    $stmt = $ourDb->prepare('SELECT id, species, breed FROM tblAuctionPet WHERE house = ?');
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingPetIds = DBMapArray($result);
    $stmt->close();

    $naiveMax = 0;
    $lowMax = -1;
    $highMax = -1;
    $hasRollOver = false;

    $jsonAuctions = [];
    if (isset($json['auctions']['auctions'])) {
        $jsonAuctions =& $json['auctions']['auctions'];
    } elseif (isset($json['auctions']) && (count($json['auctions']) > 5)) {
        $jsonAuctions =& $json['auctions'];
    }

    if ($jsonAuctions) {
        $auctionCount = count($jsonAuctions);

        for ($x = 0; $x < $auctionCount; $x++) {
            $auctionId = $jsonAuctions[$x]['auc'];

            $naiveMax = max($naiveMax, $auctionId);
            if ($auctionId < 0x20000000) {
                $lowMax = max($lowMax, $auctionId);
            }
            if ($auctionId > 0x60000000) {
                $highMax = max($highMax, $auctionId);
            }
        }
    }

    if (($lowMax != -1) && ($highMax != -1)) {
        $hasRollOver = true;
        $max = $lowMax; // rolled over
    } else {
        $max = $naiveMax;
    }

    unset($naiveMax, $lowMax, $highMax);

    $stmt = $ourDb->prepare('SELECT ifnull(maxid,0) FROM tblSnapshot s WHERE house = ? AND updated = (SELECT max(s2.updated) FROM tblSnapshot s2 WHERE s2.house = s.house AND s2.updated < ?)');
    $stmt->bind_param('is', $house, $snapshotString);
    $stmt->execute();
    $stmt->bind_result($lastMax);
    if ($stmt->fetch() !== true) {
        $lastMax = 0;
    }
    $stmt->close();

    $stmt = $ourDb->prepare('UPDATE tblSnapshot SET maxid = ? WHERE house = ? AND updated = ?');
    $stmt->bind_param('iis', $max, $house, $snapshotString);
    $stmt->execute();
    $stmt->close();

    $stmt = $ourDb->prepare('SELECT unix_timestamp(updated) updated, maxid FROM tblSnapshot WHERE house = ? AND updated BETWEEN timestampadd(HOUR, -49, ?) AND ? ORDER BY updated ASC');
    $stmt->bind_param('iss', $house, $snapshotString, $snapshotString);
    $stmt->execute();
    $result = $stmt->get_result();
    $snapshotList = DBMapArray($result, null);
    $stmt->close();

    $prevSnapshot = $snapshot;
    if (count($snapshotList)) {
        $prevSnapshot = intval($snapshotList[count($snapshotList)-1]['updated'],10);
    }
    $snapshotWindow = $snapshot - $prevSnapshot;
    $expiredLength = 1; // any missing shorts can be considered expired
    if ($snapshotWindow > 1800) { // 30 mins
        $expiredLength = 2; // any missing shorts or mediums can be expired
    }

    $sqlStart = 'REPLACE INTO tblAuction (house, id, item, quantity, bid, buy, seller, timeleft) VALUES ';
    $sqlStartPet = 'REPLACE INTO tblAuctionPet (house, id, species, breed, `level`, quality) VALUES ';
    $sqlStartExtra = 'REPLACE INTO tblAuctionExtra (house, id, `rand`, `seed`, `context`, `bonusset`';
    for ($x = 1; $x <= MAX_BONUSES; $x++) {
        $sqlStartExtra .= ", bonus$x";
    }
    $sqlStartExtra .= ') VALUES ';

    $totalAuctions = 0;
    $itemInfo = array();
    $petInfo = array();
    $sellerInfo = array();
    $expiredItemInfo = array();

    if ($jsonAuctions) {
        $auctionCount = count($jsonAuctions);
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " parsing $auctionCount auctions");

        for ($x = 0; $x < $auctionCount; $x++) {
            $auction =& $jsonAuctions[$x];
            if ($auction['owner'] == '???') {
                continue;
            }
            if (!isset($sellerInfo[$auction['ownerRealm']])) {
                $sellerInfo[$auction['ownerRealm']] = array();
            }
            if (!isset($sellerInfo[$auction['ownerRealm']][$auction['owner']])) {
                $sellerInfo[$auction['ownerRealm']][$auction['owner']] = array(
                    'new'   => 0,
                    'total' => 0,
                    'id'    => 0,
                    'items' => [],
                );
            }
            $sellerInfo[$auction['ownerRealm']][$auction['owner']]['total']++;
            if ((!$hasRollOver || $auction['auc'] < 0x20000000) && ($auction['auc'] > $lastMax)) {
                $sellerInfo[$auction['ownerRealm']][$auction['owner']]['new']++;
                $itemId = intval($auction['item'], 10);
                if (!isset($sellerInfo[$auction['ownerRealm']][$auction['owner']]['items'][$itemId])) {
                    $sellerInfo[$auction['ownerRealm']][$auction['owner']]['items'][$itemId] = [0,0];
                }
                $sellerInfo[$auction['ownerRealm']][$auction['owner']]['items'][$itemId][0]++;
                $sellerInfo[$auction['ownerRealm']][$auction['owner']]['items'][$itemId][1] += $auction['quantity'];
            }
        }

        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " sellerInfo populated");

        GetSellerIds($region, $sellerInfo, $snapshot);

        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " seller IDs fetched");

        $sql = $sqlPet = $sqlExtra = '';
        $delayedAuctionSql = [];

        while ($auction = array_pop($jsonAuctions)) {
            if (isset($auction['petBreedId'])) {
                $auction['petBreedId'] = (($auction['petBreedId'] - 3) % 10) + 3; // squash gender
            }
            $auction['timeLeft'] = isset($TIMELEFT_ENUM[$auction['timeLeft']]) ? $TIMELEFT_ENUM[$auction['timeLeft']] : 0;

            $totalAuctions++;
            $itemInfoKey = false;
            if ($auction['buyout'] != 0) {
                if (isset($auction['petSpeciesId'])) {
                    if (!isset($petInfo[$auction['petSpeciesId']][$auction['petBreedId']])) {
                        $petInfo[$auction['petSpeciesId']][$auction['petBreedId']] = array('a' => array(), 'tq' => 0);
                    }

                    $petInfo[$auction['petSpeciesId']][$auction['petBreedId']]['a'][] = array(
                        'q' => $auction['quantity'],
                        'p' => $auction['buyout']
                    );
                    $petInfo[$auction['petSpeciesId']][$auction['petBreedId']]['tq'] += $auction['quantity'];
                } else {
                    $bonusSet = 0;
                    if (isset($auctionExtraItemsCache[$auction['item']]) && isset($auction['bonusLists'])) {
                        $bonusSet = GetBonusSet($auction['bonusLists']);
                    }
                    $itemInfoKey = $auction['item'] . ":$bonusSet";
                    if (!isset($itemInfo[$itemInfoKey])) {
                        $itemInfo[$itemInfoKey] = array('a' => array(), 'tq' => 0);
                    }

                    $itemInfo[$itemInfoKey]['a'][] = array(
                        'q'   => $auction['quantity'],
                        'p'   => $auction['buyout'],
                        'age' => min(254, max(0, floor(GetAuctionAge($auction['auc'], $snapshot, $snapshotList) / (48 * 60 * 60) * 255)))
                    );
                    $itemInfo[$itemInfoKey]['tq'] += $auction['quantity'];
                }
            }

            if (isset($existingIds[$auction['auc']])) {
                $needUpdate = ($auction['bid'] != $existingIds[$auction['auc']][EXISTING_COL_BID]);
                $needUpdate |= ($auction['timeLeft'] != $existingIds[$auction['auc']][EXISTING_COL_TIMELEFT]);
                unset($existingIds[$auction['auc']]);
                unset($existingPetIds[$auction['auc']]);
                if (!$needUpdate) {
                    continue;
                }
            } else {
                // new auction
                if ($auction['buyout'] != 0) {
                    if ($itemInfoKey !== false) {
                        if (!isset($expiredItemInfo['n'][$itemInfoKey])) {
                            $expiredItemInfo['n'][$itemInfoKey] = 0;
                        }
                        $expiredItemInfo['n'][$itemInfoKey]++;
                    }
                }
            }

            $thisSql = sprintf(
                '(%u, %u, %u, %u, %u, %u, %u, %u)',
                $house,
                $auction['auc'],
                $auction['item'],
                $auction['quantity'],
                $auction['bid'],
                $auction['buyout'],
                $auction['owner'] == '???' ? 0 : $sellerInfo[$auction['ownerRealm']][$auction['owner']]['id'],
                $auction['timeLeft']
            );
            if (strlen($sql) + 5 + strlen($thisSql) > $maxPacketSize) {
                DBQueryWithError($ourDb, $sql);
                $sql = '';
            }
            $sql .= ($sql == '' ? $sqlStart : ',') . $thisSql;

            if (isset($auction['petSpeciesId'])) {
                $thisSql = sprintf(
                    '(%u, %u, %u, %u, %u, %u)',
                    $house,
                    $auction['auc'],
                    $auction['petSpeciesId'],
                    $auction['petBreedId'],
                    $auction['petLevel'],
                    $auction['petQualityId']
                );

                if (strlen($sqlPet) + 5 + strlen($thisSql) > $maxPacketSize) {
                    $delayedAuctionSql[] = $sqlPet; // delayed since tblAuction row must be inserted first for foreign key
                    $sqlPet = '';
                }
                $sqlPet .= ($sqlPet == '' ? $sqlStartPet : ',') . $thisSql;
            } else if (isset($auctionExtraItemsCache[$auction['item']])) {
                $bonuses = [];
                if (isset($auction['bonusLists'])) {
                    for ($y = 0; $y < count($auction['bonusLists']); $y++) {
                        if (isset($auction['bonusLists'][$y]['bonusListId']) && $auction['bonusLists'][$y]['bonusListId']) {
                            $bonuses[] = intval($auction['bonusLists'][$y]['bonusListId'],10);
                        }
                    }
                }
                if (count($bonuses) || $auction['rand'] || $auction['context']) {
                    $bonuses = array_unique($bonuses, SORT_NUMERIC);
                    sort($bonuses, SORT_NUMERIC);
                    for ($y = count($bonuses); $y < MAX_BONUSES; $y++) {
                        $bonuses[] = 'null';
                    }
                    $bonuses = implode(',',$bonuses);
                    $thisSql = sprintf('(%u, %u, %d, %d, %u, %u, %s)',
                        $house,
                        $auction['auc'],
                        $auction['rand'],
                        $auction['seed'],
                        $auction['context'],
                        $bonusSet,
                        $bonuses
                    );

                    if (strlen($sqlExtra) + 5 + strlen($thisSql) > $maxPacketSize) {
                        $delayedAuctionSql[] = $sqlExtra; // delayed since tblAuction row must be inserted first for foreign key
                        $sqlExtra = '';
                    }
                    $sqlExtra .= ($sqlExtra == '' ? $sqlStartExtra : ',') . $thisSql;
                }
            }
        }

        if ($sql != '') {
            DBQueryWithError($ourDb, $sql);
        }

        if ($sqlPet != '') {
            $delayedAuctionSql[] = $sqlPet;
        }

        if ($sqlExtra != '') {
            $delayedAuctionSql[] = $sqlExtra;
        }

        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " running delayedAuctionSql");
        while (count($delayedAuctionSql)) {
            DBQueryWithError($ourDb, array_pop($delayedAuctionSql));
        }

        $sql = <<<EOF
insert ignore into tblAuctionRare (house, id, prevseen) (
select a.house, a.id, tis.lastseen
from tblAuction a
left join tblAuctionExtra ae on ae.house=a.house and ae.id=a.id
left join tblItemSummary tis on tis.house=a.house and tis.item=a.item and tis.bonusset=ifnull(ae.bonusset,0)
where a.house = %d
and a.id > %d
and a.item not in (82800)
%s
and ifnull(tis.lastseen, '2000-01-01') < timestampadd(day,-14,'%s'))
EOF;
        $sql = sprintf($sql, $house, $lastMax, $hasRollOver ? ' and a.id < 0x20000000 ' : '', $snapshotString);
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " running tblAuctionRare");
        DBQueryWithError($ourDb, $sql);
    }

    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " finding missing auctions");
    foreach ($existingIds as $existingId => &$oldRow) {
        // all missing auctions
        if (!isset($existingPetIds[$existingId])) {
            // missing item auction
            if (($oldRow[EXISTING_COL_BUY] > 0) && ($oldRow[EXISTING_COL_TIMELEFT] > 0) && ($oldRow[EXISTING_COL_TIMELEFT] <= $expiredLength)) {
                // probably expired item with buyout
                $expiredPosted = date('Y-m-d', $snapshot - GetAuctionAge($existingId, $snapshot, $snapshotList));
                if (!isset($expiredItemInfo[$expiredPosted][$oldRow[EXISTING_COL_INFOKEY]])) {
                    $expiredItemInfo[$expiredPosted][$oldRow[EXISTING_COL_INFOKEY]] = 0;
                }
                $expiredItemInfo[$expiredPosted][$oldRow[EXISTING_COL_INFOKEY]]++;
            }
        }
    }
    unset($oldRow);

    $rareDeletes = [];

    $preDeleted = count($itemInfo);
    foreach ($existingIds as $existingId => &$oldRow) {
        if ((!isset($existingPetIds[$existingId])) && (!isset($itemInfo[$oldRow[EXISTING_COL_INFOKEY]]))) {
            list($itemId, $bonusSet) = explode(':', $oldRow[EXISTING_COL_INFOKEY]);
            $rareDeletes[$bonusSet][] = $itemId;
            $itemInfo[$oldRow[EXISTING_COL_INFOKEY]] = array('tq' => 0, 'a' => array());
        }
    }
    unset($oldRow);
    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating " . count($itemInfo) . " item info (including " . (count($itemInfo) - $preDeleted) . " no longer available)");
    UpdateItemInfo($house, $itemInfo, $snapshot);

    $sql = 'delete from tblUserRareReport where house = %d and bonusset = %d and item in (%s)';
    foreach ($rareDeletes as $bonusSet => $itemIds) {
        $chunked = array_chunk($itemIds, 200);
        foreach ($chunked as $chunk) {
            DBQueryWithError($ourDb, sprintf($sql, $house, $bonusSet, implode(',', $chunk)));
        }
    }

    $preDeleted = count($petInfo);
    foreach ($existingPetIds as &$oldRow) {
        if (!isset($petInfo[$oldRow['species']][$oldRow['breed']])) {
            $petInfo[$oldRow['species']][$oldRow['breed']] = array('tq' => 0, 'a' => array());
        }
    }
    unset($oldRow);
    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating " . count($petInfo) . " pet info (including " . (count($petInfo) - $preDeleted) . " no longer available)");
    UpdatePetInfo($house, $petInfo, $snapshot);

    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating seller history");
    UpdateSellerInfo($sellerInfo, $house, $snapshot);

    if (count($expiredItemInfo) > 0) {
        $sqlStart = 'INSERT INTO tblItemExpired (item, bonusset, house, `when`, created, expired) VALUES ';
        $sqlEnd = ' ON DUPLICATE KEY UPDATE created=created+values(created), expired=expired+values(expired)';
        $sql = '';

        if (isset($expiredItemInfo['n'])) {
            DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " adding new auctions for ".count($expiredItemInfo['n'])." items");

            $snapshotDay = date('Y-m-d', $snapshot);
            $expiredCount = 0;
            foreach ($expiredItemInfo['n'] as $infoKey => $createdCount) {
                $keyParts = explode(':', $infoKey);
                $sqlPart = sprintf('(%u, %u, %u, \'%s\', %u, %u)', $keyParts[0], $keyParts[1], $house, $snapshotDay, $createdCount, $expiredCount);

                if (strlen($sql) + 10 + strlen($sqlPart) + strlen($sqlEnd) > $maxPacketSize) {
                    DBQueryWithError($ourDb, $sql . $sqlEnd);
                    $sql = '';
                }

                $sql .= ($sql == '' ? $sqlStart : ',') . $sqlPart;
            }
            unset($expiredItemInfo['n']);
        }

        if ($sql != '') {
            DBQueryWithError($ourDb, $sql . $sqlEnd);
            $sql = '';
        }

        $createdCount = 0;
        $snapshotDays = array_keys($expiredItemInfo);
        foreach ($snapshotDays as $snapshotDay) {
            DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " adding expired auctions from $snapshotDay for ".count($expiredItemInfo[$snapshotDay])." items");
            foreach ($expiredItemInfo[$snapshotDay] as $infoKey => $expiredCount) {
                $keyParts = explode(':', $infoKey);
                $sqlPart = sprintf('(%u, %u, %u, \'%s\', %u, %u)', $keyParts[0], $keyParts[1], $house, $snapshotDay, $createdCount, $expiredCount);

                if (strlen($sql) + 10 + strlen($sqlPart) + strlen($sqlEnd) > $maxPacketSize) {
                    DBQueryWithError($ourDb, $sql . $sqlEnd);
                    $sql = '';
                }

                $sql .= ($sql == '' ? $sqlStart : ',') . $sqlPart;
            }
        }

        if ($sql != '') {
            DBQueryWithError($ourDb, $sql . $sqlEnd);
        }
    }

    if (count($existingIds) > 0) {
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " deleting " . count($existingIds) . " auctions");

        $sqlStart = sprintf('DELETE FROM tblAuction WHERE house = %d AND id IN (', $house);
        $sql = '';

        foreach ($existingIds as $lostId => &$lostRow) {
            if (strlen($sql) + 10 + strlen($lostId) > $maxPacketSize) {
                DBQueryWithError($ourDb, $sql . ')');
                $sql = '';
            }
            $sql .= ($sql == '' ? $sqlStart : ',') . $lostId;
        }
        unset($lostRow);

        if ($sql != '') {
            DBQueryWithError($ourDb, $sql . ')');
        }
    }

    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " finding missing prices");
    UpdateMissingPrices($house, $snapshot);

    $snapshotHourStart = date('Y-m-d H', $snapshot) . ':00:00';
    $stmt = $ourDb->prepare('UPDATE tblSnapshot SET flags = flags | 1 WHERE house = ? AND updated between ? and timestampadd(second, 3599, ?) AND updated != ?');
    $stmt->bind_param('isss', $house, $snapshotHourStart, $snapshotHourStart, $snapshotString);
    $stmt->execute();
    $stmt->close();

    $ourDb->close();

    MCSetHouse($house, 'ts', $snapshot);

    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " finished with $totalAuctions auctions in " . round(microtime(true) - $startTimer, 2) . " sec");
}

function GetBonusSet($bonusList)
{
    global $bonusSetMemberCache, $db;
    static $bonusSetCache = [];

    $bonuses = [];
    for ($y = 0; $y < count($bonusList); $y++) {
        if (isset($bonusList[$y]['bonusListId'])) {
            $bonus = intval($bonusList[$y]['bonusListId'],10);
            if (isset($bonusSetMemberCache[$bonus])) {
                $bonuses[] = $bonus;
            }
        }
    }

    if (count($bonuses) == 0) {
        return 0;
    }

    sort($bonuses, SORT_NUMERIC);

    // check local static cache
    $bonusesKey = implode(':', $bonuses);
    if (isset($bonusSetCache[$bonusesKey])) {
        return $bonusSetCache[$bonusesKey];
    }

    // not in cache, check db
    $stmt = $db->prepare('SELECT `set`, GROUP_CONCAT(`bonus` ORDER BY 1 SEPARATOR \':\') `bonus` from tblBonusSet GROUP BY `set`');
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bonusSetCache[$row['bonus']] = $row['set'];
    }
    $result->close();
    $stmt->close();

    // check updated local cache now that we're synced with db
    if (isset($bonusSetCache[$bonusesKey])) {
        return $bonusSetCache[$bonusesKey];
    }

    // still don't have it, make a new one
    if (!DBQueryWithError($db, 'lock tables tblBonusSet write')) {
        return 0;
    };
    $newSet = 0;

    $stmt = $db->prepare('select ifnull(max(`set`),0)+1 from tblBonusSet');
    $stmt->execute();
    $stmt->bind_result($newSet);
    $stmt->fetch();
    $stmt->close();

    if ($newSet) {
        $sql = 'insert into tblBonusSet (`set`, `bonus`) VALUES ';
        for ($x = 0; $x < count($bonuses); $x++) {
            $sql .= ($x > 0 ? ',' : '') . "($newSet,{$bonuses[$x]})";
        }
        if (!DBQueryWithError($db, $sql)) {
            $newSet = 0;
        }
    }
    DBQueryWithError($db, 'unlock tables');

    if ($newSet) {
        $bonusSetCache[$bonusesKey] = $newSet;
    }

    return $newSet;
}

function GetAuctionAge($id, $now, &$snapshotList)
{
    $imin = 0;
    $imax = count($snapshotList) - 1;

    if ($imax <= 0) {
        return 0;
    }

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

    if ($snapshotList[$imax]['maxid'] < $id) {
        return 0;
    }

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
        $seconds = floor(
            $now - (
                $snapshotList[$imin - 1]['updated'] +
                ($snapshotList[$imin]['updated'] - $snapshotList[$imin - 1]['updated']) *
                (($id - $snapshotList[$imin - 1]['maxid']) / ($snapshotList[$imin]['maxid'] - $snapshotList[$imin - 1]['maxid']))
            )
        );
    }

    return $seconds;
}

function GetSellerIds($region, &$sellerInfo, $snapshot, $afterInsert = false)
{
    global $db, $ownerRealmCache, $maxPacketSize;

    $snapshotString = date('Y-m-d H:i:s', $snapshot);
    $workingRealms = array_keys($sellerInfo);
    $neededInserts = false;

    for ($r = 0; $r < count($workingRealms); $r++) {
        if (!isset($ownerRealmCache[$region][$workingRealms[$r]])) {
            continue;
        }

        $realmName = $workingRealms[$r];

        $realmId = $ownerRealmCache[$region][$realmName]['id'];

        $sqlStart = "SELECT name, id FROM tblSeller WHERE realm = $realmId AND name IN (";
        $sql = $sqlStart;
        $namesInQuery = 0;
        $names = array_keys($sellerInfo[$realmName]);
        $nameCount = count($names);
        $needInserts = false;

        for ($s = 0; $s < $nameCount; $s++) {
            if ($sellerInfo[$realmName][$names[$s]]['id'] != 0) {
                continue;
            }

            $nameEscaped = '\'' . $db->real_escape_string($names[$s]) . '\'';
            if (strlen($sql) + strlen($nameEscaped) + 5 > $maxPacketSize) {
                $sql .= ')';

                $stmt = $db->prepare($sql);
                $stmt->execute();
                $result = $stmt->get_result();
                $someIds = DBMapArray($result, null);
                $foundNames = 0;
                $lastSeenIds = array();

                for ($n = 0; $n < count($someIds); $n++) {
                    if (isset($sellerInfo[$realmName][$someIds[$n]['name']])) {
                        $sellerInfo[$realmName][$someIds[$n]['name']]['id'] = $someIds[$n]['id'];
                        $foundNames++;
                        if ($sellerInfo[$realmName][$someIds[$n]['name']]['new'] > 0) {
                            $lastSeenIds[] = $someIds[$n]['id'];
                        }
                    }
                }

                if (count($lastSeenIds) > 0 && !$afterInsert) {
                    DBQueryWithError($db, sprintf('UPDATE tblSeller SET lastseen = \'%s\' WHERE id IN (%s)', $snapshotString, implode(',', $lastSeenIds)));
                }

                $needInserts |= ($foundNames < $namesInQuery);

                $sql = $sqlStart;
                $namesInQuery = 0;
            }
            $sql .= ($namesInQuery++ > 0 ? ',' : '') . $nameEscaped;
        }

        if ($namesInQuery > 0) {
            $sql .= ')';

            $stmt = $db->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $someIds = DBMapArray($result, null);
            $foundNames = 0;
            $lastSeenIds = array();

            for ($n = 0; $n < count($someIds); $n++) {
                if (isset($sellerInfo[$realmName][$someIds[$n]['name']])) {
                    $sellerInfo[$realmName][$someIds[$n]['name']]['id'] = $someIds[$n]['id'];
                    $foundNames++;
                    if ($sellerInfo[$realmName][$someIds[$n]['name']]['new'] > 0) {
                        $lastSeenIds[] = $someIds[$n]['id'];
                    }
                }
            }

            if (count($lastSeenIds) > 0 && !$afterInsert) {
                DBQueryWithError($db, sprintf('UPDATE tblSeller SET lastseen = \'%s\' WHERE id IN (%s)', $snapshotString, implode(',', $lastSeenIds)));
            }

            $needInserts |= ($foundNames < $namesInQuery);
        }

        if ($afterInsert || !$needInserts) {
            continue;
        }

        $neededInserts = true;

        $sqlStart = "INSERT IGNORE INTO tblSeller (realm, name, firstseen, lastseen) VALUES ";
        $sql = $sqlStart;
        $namesInQuery = 0;

        for ($s = 0; $s < $nameCount; $s++) {
            if ($sellerInfo[$realmName][$names[$s]]['id'] != 0) {
                continue;
            }

            $insertBit = sprintf('(%1$d,\'%2$s\',\'%3$s\',\'%3$s\')', $realmId, $db->real_escape_string($names[$s]), $snapshotString);
            if (strlen($sql) + strlen($insertBit) + 5 > $maxPacketSize) {
                DBQueryWithError($db, $sql);

                $sql = $sqlStart;
                $namesInQuery = 0;
            }
            $sql .= ($namesInQuery++ > 0 ? ',' : '') . $insertBit;
        }

        if ($namesInQuery > 0) {
            DBQueryWithError($db, $sql);
        }
    }

    if ($neededInserts) {
        GetSellerIds($region, $sellerInfo, $snapshot, true);
    }

}

function UpdateSellerInfo(&$sellerInfo, $house, $snapshot)
{
    global $db, $maxPacketSize;

    $hour = date('H', $snapshot);
    $dateString = date('Y-m-d', $snapshot);

    $snapshotString = date('Y-m-d H:i:s', $snapshot);
    $realms = array_keys($sellerInfo);

    $sqlStart = sprintf('INSERT IGNORE INTO tblSellerHistoryHourly (seller, `when`, `new%1$s`, `total%1$s`) VALUES ', $hour);
    $sqlEnd = sprintf(' on duplicate key update `new%1$s` = values(`new%1$s`) + ifnull(`new%1$s`, 0), `total%1$s`=if(values(`total%1$s`) > ifnull(`total%1$s`,0), values(`total%1$s`), `total%1$s`)', $hour);

    $sqlItemStart = 'INSERT IGNORE INTO tblSellerItemHistory (item, seller, snapshot, house, auctions, quantity) VALUES ';
    $sql = '';
    $sqlItem = '';

    for ($r = 0; $r < count($realms); $r++) {
        foreach ($sellerInfo[$realms[$r]] as &$info) {
            if ($info['id'] == 0) {
                continue;
            }

            $sqlBit = sprintf('(%d,\'%s\',%d,%d)', $info['id'], $dateString, $info['new'], $info['total']);
            if (strlen($sql) + strlen($sqlBit) + strlen($sqlEnd) + 5 > $maxPacketSize) {
                DBQueryWithError($db, $sql . $sqlEnd);
                $sql = '';
            }
            $sql .= ($sql == '' ? $sqlStart : ',') . $sqlBit;

            foreach ($info['items'] as $item => $details) {
                $sqlBit = sprintf('(%d,%d,\'%s\',%d,%d,%d)', $item, $info['id'], $snapshotString, $house, $details[0], $details[1]);
                if (strlen($sqlItem) + strlen($sqlBit) + 5 > $maxPacketSize) {
                    DBQueryWithError($db, $sqlItem);
                    $sqlItem = '';
                }
                $sqlItem .= ($sqlItem == '' ? $sqlItemStart : ',') . $sqlBit;
            }
        }
        unset($info);
    }

    if ($sql != '') {
        DBQueryWithError($db, $sql . $sqlEnd);
    }
    if ($sqlItem != '') {
        DBQueryWithError($db, $sqlItem);
    }
}

function UpdateItemInfo($house, &$itemInfo, $snapshot, $substitutePrices = false)
{
    global $db, $maxPacketSize;

    $month = (intval(date('Y', $snapshot), 10) - 2014) * 12 + intval(date('m', $snapshot), 10);
    $day = date('d', $snapshot);
    $hour = date('H', $snapshot);
    $dateString = date('Y-m-d', $snapshot);

    $snapshotString = date('Y-m-d H:i:s', $snapshot);
    $sqlStart = 'INSERT INTO tblItemSummary (house, item, bonusset, price, quantity, lastseen, age) VALUES ';
    $sqlEnd = ' on duplicate key update quantity=values(quantity), price=if(quantity=0,price,values(price)), lastseen=if(quantity=0,lastseen,values(lastseen)), age=values(age)';
    $sql = '';

    $sqlHistoryStart = sprintf('INSERT INTO tblItemHistoryHourly (house, item, bonusset, `when`, `silver%1$s`, `quantity%1$s`) VALUES ', $hour);
    $sqlHistoryEnd = sprintf(' on duplicate key update `silver%1$s`=if(values(`quantity%1$s`) > ifnull(`quantity%1$s`,0), values(`silver%1$s`), `silver%1$s`), `quantity%1$s`=if(values(`quantity%1$s`) > ifnull(`quantity%1$s`,0), values(`quantity%1$s`), `quantity%1$s`)', $hour);
    $sqlHistory = '';

    $sqlDeepStart = sprintf('INSERT INTO tblItemHistoryMonthly (house, item, bonusset, mktslvr%1$s, qty%1$s, `month`) VALUES ', $day);
    $sqlDeepEnd = sprintf(' on duplicate key update mktslvr%1$s=if(values(qty%1$s) > ifnull(qty%1$s,0), values(mktslvr%1$s), mktslvr%1$s), qty%1$s=if(values(qty%1$s) > ifnull(qty%1$s,0), values(qty%1$s), qty%1$s)', $day);
    $sqlDeep = '';

    if ($substitutePrices) {
        $sqlEnd = ' on duplicate key update quantity=values(quantity), price=values(price), lastseen=values(lastseen), age=values(age)';
        $sqlDeepEnd = sprintf(' on duplicate key update mktslvr%1$s=ifnull(least(values(mktslvr%1$s), mktslvr%1$s), values(mktslvr%1$s)), qty%1$s=if(values(qty%1$s) >= ifnull(qty%1$s,0), values(qty%1$s), qty%1$s)', $day);
    }

    foreach ($itemInfo as $itemKey => &$info) {
        list($item, $bonusSet) = explode(':', $itemKey, 2);
        if ($substitutePrices) {
            $price = $info['price'];
            $age = 255;
        } else {
            $price = GetMarketPrice($info);
            $age = GetAverageAge($info, $price);
        }

        $sqlBit = sprintf('(%d,%u,%u,%u,%u,\'%s\',%u)', $house, $item, $bonusSet, $price, $info['tq'], $snapshotString, $age);
        if (strlen($sql) + strlen($sqlBit) + strlen($sqlEnd) + 5 > $maxPacketSize) {
            DBQueryWithError($db, $sql . $sqlEnd);
            $sql = '';
        }
        $sql .= ($sql == '' ? $sqlStart : ',') . $sqlBit;

        if ($substitutePrices || ($info['tq'] > 0)) {
            $price = round($price / 100);

            $sqlHistoryBit = sprintf('(%d,%u,%u,\'%s\',%u,%u)', $house, $item, $bonusSet, $dateString, $price, $info['tq']);
            if (strlen($sqlHistory) + strlen($sqlHistoryBit) + strlen($sqlHistoryEnd) + 5 > $maxPacketSize) {
                DBQueryWithError($db, $sqlHistory . $sqlHistoryEnd);
                $sqlHistory = '';
            }
            $sqlHistory .= ($sqlHistory == '' ? $sqlHistoryStart : ',') . $sqlHistoryBit;

            $sqlDeepBit = sprintf('(%d,%u,%u,%u,%u,%u)', $house, $item, $bonusSet, $price, $info['tq'], $month);
            if (strlen($sqlDeep) + strlen($sqlDeepBit) + strlen($sqlDeepEnd) + 5 > $maxPacketSize) {
                DBQueryWithError($db, $sqlDeep . $sqlDeepEnd);
                $sqlDeep = '';
            }
            $sqlDeep .= ($sqlDeep == '' ? $sqlDeepStart : ',') . $sqlDeepBit;
        }
    }
    unset($info);

    if ($sql != '') {
        DBQueryWithError($db, $sql . $sqlEnd);
    }
    if ($sqlHistory != '') {
        DBQueryWithError($db, $sqlHistory . $sqlHistoryEnd);
    }
    if ($sqlDeep != '') {
        DBQueryWithError($db, $sqlDeep . $sqlDeepEnd);
    }
}

function UpdatePetInfo($house, &$petInfo, $snapshot)
{
    global $db, $maxPacketSize;

    $hour = date('H', $snapshot);
    $dateString = date('Y-m-d', $snapshot);

    $snapshotString = date('Y-m-d H:i:s', $snapshot);
    $sqlStart = 'INSERT INTO tblPetSummary (house, species, breed, price, quantity, lastseen) VALUES ';
    $sqlEnd = ' on duplicate key update quantity=values(quantity), price=if(quantity=0,price,values(price)), lastseen=if(quantity=0,lastseen,values(lastseen))';
    $sql = '';

    $sqlHistoryStart = sprintf('INSERT INTO tblPetHistoryHourly (house, species, breed, `when`, `silver%1$s`, `quantity%1$s`) VALUES ', $hour);
    $sqlHistoryEnd = sprintf(' on duplicate key update `silver%1$s`=if(values(`quantity%1$s`) > ifnull(`quantity%1$s`,0), values(`silver%1$s`), `silver%1$s`), `quantity%1$s`=if(values(`quantity%1$s`) > ifnull(`quantity%1$s`,0), values(`quantity%1$s`), `quantity%1$s`)', $hour);
    $sqlHistory = '';

    foreach ($petInfo as $species => &$breeds) {
        foreach ($breeds as $breed => &$info) {
            $price = GetMarketPrice($info);
            $sqlBit = sprintf('(%d,%u,%u,%u,%u,\'%s\')', $house, $species, $breed, $price, $info['tq'], $snapshotString);
            if (strlen($sql) + strlen($sqlBit) + strlen($sqlEnd) + 5 > $maxPacketSize) {
                DBQueryWithError($db, $sql . $sqlEnd);
                $sql = '';
            }
            $sql .= ($sql == '' ? $sqlStart : ',') . $sqlBit;

            if ($info['tq'] > 0) {
                $sqlHistoryBit = sprintf('(%d,%u,%u,\'%s\',%u,%u)', $house, $species, $breed, $dateString, round($price / 100), $info['tq']);
                if (strlen($sqlHistory) + strlen($sqlHistoryBit) + strlen($sqlHistoryEnd) + 5 > $maxPacketSize) {
                    DBQueryWithError($db, $sqlHistory . $sqlHistoryEnd);
                    $sqlHistory = '';
                }
                $sqlHistory .= ($sqlHistory == '' ? $sqlHistoryStart : ',') . $sqlHistoryBit;
            }
        }
        unset($info);
    }
    unset($breeds);

    if ($sql != '') {
        DBQueryWithError($db, $sql . $sqlEnd);
    }
    if ($sqlHistory != '') {
        DBQueryWithError($db, $sqlHistory . $sqlHistoryEnd);
    }
}

function UpdateMissingPrices($house, $snapshot) {
    global $db;

    $sql = <<<EOF
select bb.reagent, ((createdprice - sum(quantity * reagentprice)) / missingqty) as price
from (
   select aa.*, irs2.quantity, min(s2.price) reagentprice
   from
     (SELECT irs.spell, irs.reagent, irs.quantity missingqty, min(s.price) createdprice
      from tblDBCItemReagents irs
        join tblItemSummary s on s.item = irs.item and s.house = %1\$d and s.quantity > 0
      where irs.reagent in (%2\$s)
      group by irs.spell, irs.reagent) aa
     join tblDBCItemReagents irs2 on irs2.spell = aa.spell
     join tblItemSummary s2 on s2.item = irs2.reagent and s2.house = %1\$d and not (s2.age = 255 and s2.quantity = 0)
   group by aa.spell, irs2.reagent) bb
group by bb.spell, bb.reagent
order by 2
EOF;

    $stmt = $db->prepare(sprintf($sql, $house, implode(',', GetMissingItems())));
    $stmt->execute();
    $result = $stmt->get_result();
    $allPrices = [];
    while ($row = $result->fetch_assoc()) {
        $allPrices[$row['reagent']][] = $row['price'];
    }
    $result->close();
    $stmt->close();

    $itemInfo = [];

    foreach ($allPrices as $item => &$prices) {
        if (count($prices) >= 4) {
            $toCut = count($prices) / 4;
            array_splice($prices, 0, floor($toCut));
            array_splice($prices, count($prices) - ceil($toCut));
        }

        if (count($prices) == 0) {
            continue;
        }

        $itemInfo["$item:0"] = [
            'price' => max(0, floor(array_sum($prices) / count($prices))),
            'tq' => 0
        ];

    }
    unset($prices);

    if (count($itemInfo)) {
        UpdateItemInfo($house, $itemInfo, $snapshot, true);
    }
}

function GetMissingItems() {
    static $missingItems = false;

    global $db;

    $cacheKey = 'parse_missing_items';

    if ($missingItems === false) {
        $missingItems = MCGet($cacheKey);
    }

    if ($missingItems === false) {
        $sql = <<<EOF
SELECT i.id
from tblDBCItemReagents dir
join tblDBCItem i on dir.reagent=i.id
join tblDBCItem i2 on dir.item=i2.id
where not exists (select 1 from tblItemSummary s join tblRealm r on s.house=r.house and r.canonical is not null where s.item=i.id and not (s.quantity=0 and s.age=255) limit 1)
and exists (select 1 from tblItemSummary s join tblRealm r on s.house=r.house and r.canonical is not null where s.item=i2.id and not (s.quantity=0 and s.age=255) limit 1)
and not exists (select 1 from tblDBCItemVendorCost v where v.item = i.id limit 1)
and i2.auctionable = 1
and i.id not in (120945)
group by i.id
EOF;
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $missingItems = array_keys(DBMapArray($result));
        $stmt->close();

        MCSet($cacheKey, $missingItems, 43200);
    }

    return $missingItems;
}

function GetAverageAge(&$info, $price)
{
    if ($info['tq'] == 0) {
        return 0;
    }

    $c = $s = 0;
    $tc = count($info['a']);
    for ($x = 0; $x < $tc; $x++) {
        $p = floor($info['a'][$x]['p'] / $info['a'][$x]['q']);
        if ($p <= $price) {
            $s += $info['a'][$x]['age'];
            $c++;
        } elseif ($c > 0) {
            break; // already sorted by market price
        }
    }

    return floor($s / $c);
}

function GetMarketPrice(&$info)
{
    if ($info['tq'] == 0) {
        return 0;
    }

    usort($info['a'], 'MarketPriceSort');
    $gq = 0;
    $gp = 0;
    $x = 0;
    while ($gq < ceil($info['tq'] * 0.15)) {
        $gq += $info['a'][$x]['q'];
        $gp += $info['a'][$x]['p'];
        $x++;
    }
    return ceil($gp / $gq);
}

function MarketPriceSort($a, $b)
{
    $ap = ceil($a['p'] / $a['q']);
    $bp = ceil($b['p'] / $b['q']);
    if ($ap - $bp != 0) {
        return ($ap - $bp);
    }
    return ($a['q'] - $b['q']);
}

function DBQueryWithError(&$db, $sql)
{
    $queryOk = $db->query($sql);
    if (!$queryOk) {
        DebugMessage("SQL error: " . $db->errno . ' ' . $db->error . " - " . substr(preg_replace('/[\r\n]/', ' ', $sql), 0, 500), E_USER_WARNING);
    }

    return $queryOk;
}