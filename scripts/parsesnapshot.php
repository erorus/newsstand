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
define('ITEM_ID_PAD', 7); // min number of chars for item id, for sorting infokeys
define('OBSERVED_WITHOUT_BONUSES_LIMIT', 500); // if we see this many auctions of an item without any having bonuses, assume the item doesn't get bonuses

define('EXISTING_SQL', 'SELECT a.id, a.bid, a.buy, a.timeleft+0 timeleft, concat_ws(\':\', lpad(a.item,' . ITEM_ID_PAD . ',\'0\'), ifnull(ae.bonusset,0)) infokey FROM tblAuction a LEFT JOIN tblAuctionExtra ae on a.house=ae.house and a.id=ae.id WHERE a.house = ?');
define('EXISTING_COL_BID', 0);
define('EXISTING_COL_BUY', 1);
define('EXISTING_COL_TIMELEFT', 2);
define('EXISTING_COL_INFOKEY', 3);

define('DB_LOCK_SEEN_BONUSES', 'update_seen_bonuses');
define('DB_LOCK_SEEN_ILVLS', 'update_seen_ilvls');

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

$stmt = $db->prepare('SELECT id, region, name, ifnull(ownerrealm, name) AS ownerrealm FROM tblRealm');
$stmt->execute();
$result = $stmt->get_result();
$ownerRealmCache = DBMapArray($result, array('region', 'ownerrealm'));
$stmt->close();

$equipBaseItemLevel = [];
$stmt = $db->prepare('SELECT id, level FROM tblDBCItem WHERE `class` in (2,4) AND `auctionable` = 1');
$stmt->execute();
$id = $level = null;
$stmt->bind_result($id, $level);
while ($stmt->fetch()) {
    $equipBaseItemLevel[$id] = $level;
}
$stmt->close();

$bonusTagIdCache = [];
$stmt = $db->prepare('SELECT id, tagid FROM tblDBCItemBonus WHERE tagid IS NOT NULL ORDER BY 1');
$stmt->execute();
$id = $tagId = null;
$stmt->bind_result($id, $tagId);
while ($stmt->fetch()) {
    $bonusTagIdCache[$id] = $tagId;
}
$stmt->close();

$usefulBonusesCache = [];
$stmt = $db->prepare('select id from tblDBCItemBonus where (quality is not null or level is not null or previewlevel is not null or levelcurve is not null or tagid is not null or socketmask is not null) order by 1');
$stmt->execute();
$id = null;
$stmt->bind_result($id);
while ($stmt->fetch()) {
    $usefulBonusesCache[$id] = $id;
}
$stmt->close();

$enoughBonusesSeenCache = [];
$stmt = $db->prepare('SELECT concat_ws(\':\', item, bonusset) FROM `tblItemBonusesSeen` WHERE observed > 100000 group by item, bonusset');
$stmt->execute();
$id = null;
$stmt->bind_result($id);
while ($stmt->fetch()) {
    $enoughBonusesSeenCache[$id] = $id;
}
$stmt->close();

$observedWithoutBonusesCache = [];
$sql = <<<'EOF'
select i.id, ibs.observed
from tblDBCItem i
join tblItemBonusesSeen ibs on ibs.item = i.id and ibs.bonus1 = 0
left join tblItemBonusesSeen ibs2 on ibs2.item = i.id and ibs2.bonus1 != 0
where i.class in (2,4)
and i.auctionable = 1
and ibs2.item is null
EOF;
$stmt = $db->prepare($sql);
$stmt->execute();
$id = $observed = null;
$stmt->bind_result($id, $observed);
while ($stmt->fetch()) {
    $observedWithoutBonusesCache[$id] = $observed;
}
$stmt->close();

$bonusCurveCache = [];
$stmt = $db->prepare('select id, levelcurve from tblDBCItemBonus where levelcurve is not null');
$stmt->execute();
$id = $curve = null;
$stmt->bind_result($id, $curve);
while ($stmt->fetch()) {
    $bonusCurveCache[$id] = $curve;
}
$stmt->close();

$curvePointCache = [];
$stmt = $db->prepare('select curve, `key`, `value` from tblDBCCurvePoint cp join (select distinct levelcurve from tblDBCItemBonus) curves on cp.curve = curves.levelcurve order by curve, `step`');
$stmt->execute();
$curve = $key = $value = null;
$stmt->bind_result($curve, $key, $value);
while ($stmt->fetch()) {
    $curvePointCache[$curve][$key] = $value;
}
$stmt->close();

$bonusLevelCache = [];
$stmt = $db->prepare('select id, level from tblDBCItemBonus where level is not null');
$stmt->execute();
$id = $level = null;
$stmt->bind_result($id, $level);
while ($stmt->fetch()) {
    $bonusLevelCache[$id] = $level;
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

function GetDBLock($db, $lockName)
{
    $now = microtime(true);
    $stmt = $db->prepare('select get_lock(?, 30)');
    $stmt->bind_param('s', $lockName);
    $stmt->execute();
    $lockSuccess = null;
    $stmt->bind_result($lockSuccess);
    if (!$stmt->fetch()) {
        $lockSuccess = null;
    }
    $stmt->close();
    $duration = microtime(true) - $now;
    if ($duration >= 3) {
        DebugMessage("Waited ".round($duration, 2)." seconds for DB lock $lockName; " . ($lockSuccess === 1 ? "successful." : "failed."));
    }

    return $lockSuccess === 1;
}

function ReleaseDBLock($db, $lockName) {
    $stmt = $db->prepare('do release_lock(?)');
    $stmt->bind_param('s', $lockName);
    $stmt->execute();
    $stmt->close();
}

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
    global $equipBaseItemLevel;
    global $usefulBonusesCache, $enoughBonusesSeenCache, $observedWithoutBonusesCache;
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

    $stmt = $ourDb->prepare('SELECT ifnull(maxid,0) FROM tblSnapshot s WHERE house = ? AND updated = (SELECT max(s2.updated) FROM tblSnapshot s2 WHERE s2.house = ? AND s2.updated < ?)');
    $stmt->bind_param('iis', $house, $house, $snapshotString);
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
    $sqlStartExtra = 'REPLACE INTO tblAuctionExtra (house, id, `rand`, `seed`, `context`, `lootedlevel`, `level`, `bonusset`';
    for ($x = 1; $x <= MAX_BONUSES; $x++) {
        $sqlStartExtra .= ", bonus$x";
    }
    $sqlStartExtra .= ') VALUES ';
    $sqlStartBonusesSeen = 'INSERT INTO tblItemBonusesSeen (item, bonusset, bonus1, bonus2, bonus3, bonus4, observed) VALUES ';
    $sqlEndBonusesSeen = ' ON DUPLICATE KEY UPDATE observed = observed + 1';
    $sqlStartLevelsSeen = 'INSERT IGNORE INTO tblItemLevelsSeen (item, bonusset, `level`) VALUES ';

    $totalAuctions = 0;
    $itemInfo = array();
    $petInfo = array();
    $sellerInfo = array();
    $expiredItemInfo = array();

    if ($jsonAuctions) {
        $auctionCount = count($jsonAuctions);
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " prepping $auctionCount auctions");

        $sellerCount = 0;
        for ($x = 0; $x < $auctionCount; $x++) {
            $auction =& $jsonAuctions[$x];
            if ($auction['owner'] == '???') {
                continue;
            }
            if (!isset($sellerInfo[$auction['ownerRealm']])) {
                $sellerInfo[$auction['ownerRealm']] = array();
            }
            if (!isset($sellerInfo[$auction['ownerRealm']][$auction['owner']])) {
                $sellerCount++;
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

        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " getting $sellerCount seller IDs");
        GetSellerIds($region, $sellerInfo, $snapshot);

        $sql = $sqlPet = $sqlExtra = $sqlBonusesSeen = $sqlLevelsSeen = '';
        $delayedAuctionSql = [];

        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " parsing $auctionCount auctions");
        while ($auction = array_pop($jsonAuctions)) {
            if (isset($auction['petBreedId'])) {
                $auction['petBreedId'] = (($auction['petBreedId'] - 3) % 10) + 3; // squash gender
            }
            $auction['timeLeft'] = isset($TIMELEFT_ENUM[$auction['timeLeft']]) ? $TIMELEFT_ENUM[$auction['timeLeft']] : 0;

            $auction['lootedLevel'] = null;
            if (isset($auction['modifiers'])) {
                foreach ($auction['modifiers'] as $modObj) {
                    if (isset($modObj['type']) && ($modObj['type'] == 9)) {
                        $auction['lootedLevel'] = intval($modObj['value']);
                    }
                }
            }

            $totalAuctions++;
            $itemInfoKey = false;
            $bonusSet = 0;
            $bonuses = [];
            $bonusItemLevel = null;
            $priceScaling = null;
            if (!isset($auction['petSpeciesId']) && isset($equipBaseItemLevel[$auction['item']]) && isset($auction['bonusLists'])) {
                for ($y = 0; $y < count($auction['bonusLists']); $y++) {
                    if (isset($auction['bonusLists'][$y]['bonusListId']) && $auction['bonusLists'][$y]['bonusListId']) {
                        $bonuses[] = intval($auction['bonusLists'][$y]['bonusListId'],10);
                    }
                }
                $bonuses = array_unique($bonuses, SORT_NUMERIC);
                sort($bonuses, SORT_NUMERIC);

                $usefulBonuses = [];
                foreach ($bonuses as $bonus) {
                    if (isset($usefulBonusesCache[$bonus])) {
                        $usefulBonuses[$bonus] = $bonus;
                    }
                }

                if ($usefulBonuses && isset($observedWithoutBonusesCache[$auction['item']])) {
                    if ($observedWithoutBonusesCache[$auction['item']] >= OBSERVED_WITHOUT_BONUSES_LIMIT) {
                        $bonuses = []; // remove bonuses attached to auction, they probably don't belong
                    }
                    if (!isset($existingIds[$auction['auc']])) { // new auction
                        DebugMessage(sprintf(
                            'House %s new item %d has useful bonuses %s, first auction with them after %d observations, %s bonuses!',
                            str_pad($house, 5, ' ', STR_PAD_LEFT), $auction['item'], implode(':', $usefulBonuses),
                            $observedWithoutBonusesCache[$auction['item']], $bonuses ? 'using' : 'ignoring'));
                    }
                }

                $bonusSet = $bonuses ? GetBonusSet($bonuses) : 0;
                $bonusItemLevel = GetBonusItemLevel($bonuses, $equipBaseItemLevel[$auction['item']], $auction['lootedLevel']);
                if ($bonusItemLevel - $equipBaseItemLevel[$auction['item']] != 0) {
                    $priceScaling = pow(1.15, ($bonusItemLevel - $equipBaseItemLevel[$auction['item']]) / 15);
                }
            }
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
                    $itemInfoKey = str_pad($auction['item'], ITEM_ID_PAD, '0', STR_PAD_LEFT) . ":$bonusSet";
                    if (!isset($itemInfo[$itemInfoKey])) {
                        $itemInfo[$itemInfoKey] = array('a' => array(), 'tq' => 0);
                    }

                    $itemInfo[$itemInfoKey]['a'][] = array(
                        'q'   => $auction['quantity'],
                        'p'   => isset($priceScaling) ? $auction['buyout'] / $priceScaling : $auction['buyout'],
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
                if (isset($equipBaseItemLevel[$auction['item']])) {
                    if (!isset($enoughBonusesSeenCache["{$auction['item']}:$bonusSet"])) {
                        $usefulBonuses = [];
                        foreach ($bonuses as $bonus) {
                            if (isset($usefulBonusesCache[$bonus])) {
                                $usefulBonuses[$bonus] = $bonus;
                            }
                        }

                        sort($usefulBonuses, SORT_NUMERIC);
                        switch (count($usefulBonuses)) {
                            case 0:
                                $usefulBonuses[] = 0;
                            case 1:
                                $usefulBonuses[] = 0;
                            case 2:
                                $usefulBonuses[] = 0;
                            case 3:
                                $usefulBonuses[] = 0;
                        }

                        $thisSql = sprintf(
                            '(%u,%u,%u,%u,%u,%u,1)',
                            $auction['item'],
                            $bonusSet,
                            $usefulBonuses[0],
                            $usefulBonuses[1],
                            $usefulBonuses[2],
                            $usefulBonuses[3]
                        );

                        if (strlen($sqlBonusesSeen) + 5 + strlen($thisSql) + strlen($sqlEndBonusesSeen) > $maxPacketSize) {
                            if (GetDBLock($ourDb, DB_LOCK_SEEN_BONUSES)) {
                                DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating seen bonuses (" . round($totalAuctions / $auctionCount * 100) . '%)');
                                DBQueryWithError($ourDb, $sqlBonusesSeen . $sqlEndBonusesSeen);
                                ReleaseDBLock($ourDb, DB_LOCK_SEEN_BONUSES);
                            } else {
                                DebugMessage("Could not obtain ".DB_LOCK_SEEN_BONUSES." DB lock, skipping update of seen bonuses.", E_USER_WARNING);
                            }
                            $sqlBonusesSeen = '';
                        }
                        $sqlBonusesSeen .= ($sqlBonusesSeen ? ',' : $sqlStartBonusesSeen) . $thisSql;
                    }

                    if (!is_null($bonusItemLevel)) {
                        $thisSql = sprintf('(%u,%u,%u)', $auction['item'], $bonusSet, $bonusItemLevel);
                        if (strlen($sqlLevelsSeen) + 5 + strlen($thisSql) > $maxPacketSize) {
                            if (GetDBLock($ourDb, DB_LOCK_SEEN_ILVLS)) {
                                DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating seen levels (" . round($totalAuctions / $auctionCount * 100) . '%)');
                                DBQueryWithError($ourDb, $sqlLevelsSeen);
                                ReleaseDBLock($ourDb, DB_LOCK_SEEN_ILVLS);
                            } else {
                                DebugMessage("Could not obtain ".DB_LOCK_SEEN_ILVLS." DB lock, skipping update of seen levels.", E_USER_WARNING);
                            }
                            $sqlLevelsSeen = '';
                        }
                        $sqlLevelsSeen .= ($sqlLevelsSeen ? ',' : $sqlStartLevelsSeen) . $thisSql;
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
                DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating tblAuction (" . round($totalAuctions / $auctionCount * 100) . '%)');
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
            } else if (isset($equipBaseItemLevel[$auction['item']])) {
                if (count($bonuses) || $auction['rand'] || $auction['context']) {
                    for ($y = count($bonuses); $y < MAX_BONUSES; $y++) {
                        $bonuses[] = 'null';
                    }
                    $bonuses = implode(',',$bonuses);
                    $thisSql = sprintf('(%u,%u,%d,%d,%u,%s,%s,%u,%s)',
                        $house,
                        $auction['auc'],
                        $auction['rand'],
                        $auction['seed'],
                        $auction['context'],
                        isset($auction['lootedLevel']) ? $auction['lootedLevel'] : 'null',
                        isset($bonusItemLevel) ? $bonusItemLevel : 'null',
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

        if ($sqlBonusesSeen != '') {
            if (GetDBLock($ourDb, DB_LOCK_SEEN_BONUSES)) {
                DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating seen bonuses");
                DBQueryWithError($ourDb, $sqlBonusesSeen . $sqlEndBonusesSeen);
                ReleaseDBLock($ourDb, DB_LOCK_SEEN_BONUSES);
            } else {
                DebugMessage("Could not obtain ".DB_LOCK_SEEN_BONUSES." DB lock, skipping update of seen bonuses.", E_USER_WARNING);
            }
        }

        if ($sqlLevelsSeen != '') {
            if (GetDBLock($ourDb, DB_LOCK_SEEN_ILVLS)) {
                DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating seen levels");
                DBQueryWithError($ourDb, $sqlLevelsSeen);
                ReleaseDBLock($ourDb, DB_LOCK_SEEN_ILVLS);
            } else {
                DebugMessage("Could not obtain ".DB_LOCK_SEEN_ILVLS." DB lock, skipping update of seen levels.", E_USER_WARNING);
            }
        }

        if ($sql != '') {
            DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating tblAuction");
            DBQueryWithError($ourDb, $sql);
        }

        if ($sqlPet != '') {
            $delayedAuctionSql[] = $sqlPet;
        }

        if ($sqlExtra != '') {
            $delayedAuctionSql[] = $sqlExtra;
        }

        if (count($delayedAuctionSql)) {
            DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating tblAuctionExtra, tblAuctionPet");
        }
        while (count($delayedAuctionSql)) {
            DBQueryWithError($ourDb, array_pop($delayedAuctionSql));
        }
        unset($sqlBonusesSeen, $sqlLevelsSeen, $sqlPet, $sqlExtra, $delayedAuctionSql);

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
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating tblAuctionRare");
        DBQueryWithError($ourDb, $sql);
    }

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
            //DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " adding expired auctions from $snapshotDay for ".count($expiredItemInfo[$snapshotDay])." items");
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

    $snapshotHourStart = date('Y-m-d H', $snapshot) . ':00:00';
    $stmt = $ourDb->prepare('UPDATE tblSnapshot SET flags = flags | 1 WHERE house = ? AND updated between ? and timestampadd(second, 3599, ?) AND updated != ?');
    $stmt->bind_param('isss', $house, $snapshotHourStart, $snapshotHourStart, $snapshotString);
    $stmt->execute();
    $stmt->close();

    $ourDb->close();

    MCSetHouse($house, 'ts', $snapshot);

    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " finished with $totalAuctions auctions in " . round(microtime(true) - $startTimer, 2) . " sec");
}

function GetBonusSet($bonuses)
{
    global $bonusTagIdCache, $db;
    static $bonusSetCache = [];

    $tagIds = [];
    for ($y = 0; $y < count($bonuses); $y++) {
        if (isset($bonusTagIdCache[$bonuses[$y]])) {
            $tagIds[$bonusTagIdCache[$bonuses[$y]]] = $bonusTagIdCache[$bonuses[$y]];
        }
    }

    if (count($tagIds) == 0) {
        return 0;
    }

    sort($tagIds, SORT_NUMERIC);

    // check local static cache
    $tagIdsKey = implode(':', $tagIds);
    if (isset($bonusSetCache[$tagIdsKey])) {
        return $bonusSetCache[$tagIdsKey];
    }

    // not in cache, check db
    if (!DBQueryWithError($db, 'lock tables tblBonusSet write')) {
        return 0;
    };

    $stmt = $db->prepare('SELECT `set`, GROUP_CONCAT(`tagid` ORDER BY 1 SEPARATOR \':\') `tagidkey` from tblBonusSet GROUP BY `set`');
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bonusSetCache[$row['tagidkey']] = $row['set'];
    }
    $result->close();
    $stmt->close();

    // check updated local cache now that we're synced with db
    if (isset($bonusSetCache[$tagIdsKey])) {
        DBQueryWithError($db, 'unlock tables');
        return $bonusSetCache[$tagIdsKey];
    }

    // still don't have it, make a new one
    $newSet = 0;

    $stmt = $db->prepare('select ifnull(max(`set`),0)+1 from tblBonusSet');
    $stmt->execute();
    $stmt->bind_result($newSet);
    $stmt->fetch();
    $stmt->close();

    if ($newSet) {
        $sql = 'insert into tblBonusSet (`set`, `tagid`) VALUES ';
        $x = 0;
        foreach ($tagIds as $tagId) {
            $sql .= ($x++ > 0 ? ',' : '') . sprintf('(%d,%d)', $newSet, $tagId);
        }
        if (!DBQueryWithError($db, $sql)) {
            $newSet = 0;
        }
    }
    DBQueryWithError($db, 'unlock tables');

    if ($newSet) {
        $bonusSetCache[$tagIdsKey] = $newSet;
    }

    return $newSet;
}

function GetBonusItemLevel($bonuses, $defaultItemLevel, $lootedLevel) {
    global $bonusCurveCache, $bonusLevelCache;

    $levelSum = $defaultItemLevel;

    foreach ($bonuses as $bonus) {
        if (isset($bonusCurveCache[$bonus])) {
            return GetCurvePoint($bonusCurveCache[$bonus], $lootedLevel);
        }
        $levelSum += isset($bonusLevelCache[$bonus]) ? $bonusLevelCache[$bonus] : 0;
    }

    return $levelSum;
}

function GetCurvePoint($curve, $point) {
    global $curvePointCache;
    if (!isset($curvePointCache[$curve])) {
        return null;
    }

    reset($curvePointCache[$curve]);
    $lastKey = key($curvePointCache[$curve]);
    $lastValue = $curvePointCache[$curve][$lastKey];

    if ($lastKey > $point) {
        return $lastValue;
    }

    foreach ($curvePointCache[$curve] as $key => $value) {
        if ($point == $key) {
            return $value;
        }
        if ($point < $key) {
            return round(($value - $lastValue) / ($key - $lastKey) * ($point - $lastKey) + $lastValue);
        }
        $lastKey = $key;
        $lastValue = $value;
    }

    return $lastValue;
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
    $sqlEnd = ' on duplicate key update quantity=values(quantity), price=if(quantity=0,price,values(price)), lastseen=if(quantity=0,lastseen,values(lastseen))';
    $sql = '';

    $sqlHistoryStart = sprintf('INSERT INTO tblItemHistoryHourly (house, item, bonusset, `when`, `silver%1$s`, `quantity%1$s`) VALUES ', $hour);
    $sqlHistoryEnd = sprintf(' on duplicate key update `silver%1$s`=if(values(`quantity%1$s`) > ifnull(`quantity%1$s`,0), values(`silver%1$s`), `silver%1$s`), `quantity%1$s`=if(values(`quantity%1$s`) > ifnull(`quantity%1$s`,0), values(`quantity%1$s`), `quantity%1$s`)', $hour);
    $sqlHistory = '';

    $sqlDeepStart = sprintf('INSERT INTO tblItemHistoryMonthly (house, item, bonusset, mktslvr%1$s, qty%1$s, `month`) VALUES ', $day);
    $sqlDeepEnd = sprintf(' on duplicate key update mktslvr%1$s=if(values(qty%1$s) > ifnull(qty%1$s,0), values(mktslvr%1$s), mktslvr%1$s), qty%1$s=if(values(qty%1$s) > ifnull(qty%1$s,0), values(qty%1$s), qty%1$s)', $day);
    $sqlDeep = '';

    if ($substitutePrices) {
        $sqlEnd = ' on duplicate key update quantity=values(quantity), price=values(price), lastseen=values(lastseen)';
        $sqlDeepEnd = sprintf(' on duplicate key update mktslvr%1$s=ifnull(least(values(mktslvr%1$s), mktslvr%1$s), values(mktslvr%1$s)), qty%1$s=if(values(qty%1$s) >= ifnull(qty%1$s,0), values(qty%1$s), qty%1$s)', $day);
    }

    ksort($itemInfo); // improves insert performance(?)
    $itemRows = 0;

    foreach ($itemInfo as $itemKey => &$info) {
        list($item, $bonusSet) = explode(':', $itemKey, 2);
        if ($substitutePrices) {
            $price = $info['price'];
            $age = 255;
        } else {
            $price = GetMarketPrice($info);
            $age = 0; //GetAverageAge($info, $price);
        }

        $sqlBit = sprintf('(%d,%u,%u,%u,%u,\'%s\',%u)', $house, $item, $bonusSet, $price, $info['tq'], $snapshotString, $age);
        if (strlen($sql) + strlen($sqlBit) + strlen($sqlEnd) + 5 > $maxPacketSize) {
            DBQueryWithError($db, $sql . $sqlEnd);
            $sql = '';
        }
        $sql .= ($sql == '' ? $sqlStart : ',') . $sqlBit;

        if ($substitutePrices || ($info['tq'] > 0)) {
            $itemRows++;

            $price = round($price / 100);

            $sqlHistoryBit = sprintf('(%d,%u,%u,\'%s\',%u,%u)', $house, $item, $bonusSet, $dateString, $price, $info['tq']);
            if (($itemRows % 1000 == 0) || strlen($sqlHistory) + strlen($sqlHistoryBit) + strlen($sqlHistoryEnd) + 5 > $maxPacketSize) {
                DBQueryWithError($db, $sqlHistory . $sqlHistoryEnd);
                $sqlHistory = '';
            }
            $sqlHistory .= ($sqlHistory == '' ? $sqlHistoryStart : ',') . $sqlHistoryBit;

            $sqlDeepBit = sprintf('(%d,%u,%u,%u,%u,%u)', $house, $item, $bonusSet, $price, $info['tq'], $month);
            if (($itemRows % 1000 == 0) || strlen($sqlDeep) + strlen($sqlDeepBit) + strlen($sqlDeepEnd) + 5 > $maxPacketSize) {
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