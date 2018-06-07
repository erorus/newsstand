<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');
require_once('../incl/BonusItemLevel.incl.php');

RunMeNTimes(2);
CatchKill();

define('SNAPSHOT_PATH', '/var/newsstand/snapshots/parse/');
define('MAX_BONUSES', 6); // is a count, 1 through N
define('OBSERVED_WITHOUT_BONUSES_LIMIT', 500); // if we see this many auctions of an item without any having bonuses, assume the item doesn't get bonuses

define('EXISTING_SQL', '
SELECT a.id, a.bid, a.buy, a.timeleft+0 timeleft, a.item,
    if(ae.level is null,
        if(i.class in (2,4), i.level, 0),
        if(ae.level >= ' . MIN_ITEM_LEVEL_PRICING . ', ae.level, i.level)
    ) level
FROM tblAuction a
LEFT JOIN tblAuctionExtra ae on a.house=ae.house and a.id=ae.id
LEFT JOIN tblDBCItem i on a.item = i.id
WHERE a.house = ?
');
define('EXISTING_COL_BID', 0);
define('EXISTING_COL_BUY', 1);
define('EXISTING_COL_TIMELEFT', 2);
define('EXISTING_COL_ITEM', 3);
define('EXISTING_COL_LEVEL', 4);

define('DB_LOCK_SEEN_BONUSES', 'update_seen_bonuses');

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
$stmt = $db->prepare('SELECT item FROM `tblItemBonusesSeen` WHERE observed > 100000 group by item');
$stmt->execute();
$id = null;
$stmt->bind_result($id);
while ($stmt->fetch()) {
    $enoughBonusesSeenCache[$id] = $id;
}
$stmt->close();

$observedWithoutBonusesCache = [];
/*
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
*/

\Newsstand\BonusItemLevel::init($db);

$maxPacketSize = 0;
$stmt = $db->prepare('show variables like \'max_allowed_packet\'');
$stmt->execute();
$stmt->bind_result($nonsense, $maxPacketSize);
$stmt->fetch();
$stmt->close();
unset($nonsense);

$loopStart = time();
$toSleep = 0;
while ((!CatchKill()) && (time() < ($loopStart + 60 * 30))) {
    heartbeat();
    sleep(min($toSleep, 10));
    if (CatchKill() || APIMaintenance()) {
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
    $json = json_decode(stream_get_contents($handle), true);

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
    $id = $bid = $buy = $timeLeft = $item = $level = null;
    $stmt->bind_result($id, $bid, $buy, $timeLeft, $item, $level);
    while ($stmt->fetch()) {
        $existingIds[$id] = [$bid, $buy, $timeLeft, $item, $level];
    }
    $stmt->close();

    $stmt = $ourDb->prepare('SELECT id, species FROM tblAuctionPet WHERE house = ?');
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

    $sqlStart = 'REPLACE INTO tblAuction (house, id, item, quantity, bid, buy, seller, timeleft) VALUES ';
    $sqlStartPet = 'REPLACE INTO tblAuctionPet (house, id, species, breed, `level`, quality) VALUES ';
    $sqlStartBadBonus = 'INSERT INTO tblAuctionBadBonus (house, id, firstseen, lastseen';
    $sqlEndBadBonus = ' ON DUPLICATE KEY UPDATE lastseen = values(lastseen)';
    $sqlStartExtra = 'REPLACE INTO tblAuctionExtra (house, id, `rand`, `seed`, `context`, `lootedlevel`, `level`';
    for ($x = 1; $x <= MAX_BONUSES; $x++) {
        $sqlStartExtra .= ", bonus$x";
        $sqlStartBadBonus .= ", bonus$x";
        $sqlEndBadBonus .= ", bonus$x = values(bonus$x)";
    }
    $sqlStartExtra .= ') VALUES ';
    $sqlStartBadBonus .= ') VALUES ';
    $sqlStartBonusesSeen = 'INSERT INTO tblItemBonusesSeen (item, bonus1, bonus2, bonus3, bonus4, observed) VALUES ';
    $sqlEndBonusesSeen = ' ON DUPLICATE KEY UPDATE observed = observed + 1';

    $totalAuctions = 0;
    $itemInfo = array();
    $petInfo = array();
    $sellerInfo = array();

    if ($jsonAuctions) {
        $auctionCount = count($jsonAuctions);
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " prepping $auctionCount auctions");

        if ($region != 'EU') {
            $sellerCount = 0;
            for ($x = 0; $x < $auctionCount; $x++) {
                $auction =& $jsonAuctions[$x];
                if ($auction['owner'] == '???') {
                    continue;
                }
                if ( ! isset($sellerInfo[$auction['ownerRealm']])) {
                    $sellerInfo[$auction['ownerRealm']] = array();
                }
                if ( ! isset($sellerInfo[$auction['ownerRealm']][$auction['owner']])) {
                    $sellerCount++;
                    $sellerInfo[$auction['ownerRealm']][$auction['owner']] = array(
                        'new'   => 0,
                        'total' => 0,
                        'id'    => 0,
                        'items' => [],
                    );
                }
                $sellerInfo[$auction['ownerRealm']][$auction['owner']]['total']++;
                if (( ! $hasRollOver || $auction['auc'] < 0x20000000) && ($auction['auc'] > $lastMax)) {
                    $sellerInfo[$auction['ownerRealm']][$auction['owner']]['new']++;
                    $itemId = intval($auction['item'], 10);
                    if ( ! isset($sellerInfo[$auction['ownerRealm']][$auction['owner']]['items'][$itemId])) {
                        $sellerInfo[$auction['ownerRealm']][$auction['owner']]['items'][$itemId] = [0, 0];
                    }
                    $sellerInfo[$auction['ownerRealm']][$auction['owner']]['items'][$itemId][0]++;
                    $sellerInfo[$auction['ownerRealm']][$auction['owner']]['items'][$itemId][1] += $auction['quantity'];
                }
            }

            DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " getting $sellerCount seller IDs");
            GetSellerIds($region, $sellerInfo, $snapshot);
        }

        $sql = $sqlPet = $sqlExtra = $sqlBadBonus = $sqlBonusesSeen = '';
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
            $bonuses = [];
            $pricingItemLevel = $bonusItemLevel = $equipBaseItemLevel[$auction['item']] ?? 0;
            if (!isset($auction['petSpeciesId']) && $bonusItemLevel) {
                if (!isset($auction['bonusLists'])) {
                    $auction['bonusLists'] = [];
                }
                for ($y = 0; $y < count($auction['bonusLists']); $y++) {
                    if (isset($auction['bonusLists'][$y]['bonusListId']) && $auction['bonusLists'][$y]['bonusListId']) {
                        $bonuses[] = intval($auction['bonusLists'][$y]['bonusListId'],10);
                    }
                }
                $bonuses = array_unique($bonuses, SORT_NUMERIC);
                sort($bonuses, SORT_NUMERIC);

                if (count($bonuses) > MAX_BONUSES) {
                    array_splice($bonuses, MAX_BONUSES);
                }

                $usefulBonuses = [];
                foreach ($bonuses as $bonus) {
                    if (isset($usefulBonusesCache[$bonus])) {
                        $usefulBonuses[$bonus] = $bonus;
                    }
                }

                if ($usefulBonuses && isset($observedWithoutBonusesCache[$auction['item']])) {
                    if ($observedWithoutBonusesCache[$auction['item']] >= OBSERVED_WITHOUT_BONUSES_LIMIT) {
                        for ($y = count($bonuses); $y < MAX_BONUSES; $y++) {
                            $bonuses[] = 'null';
                        }
                        $thisSql = sprintf('(%u,%u,\'%s\',\'%s\',%s)', $house, $auction['auc'], $snapshotString, $snapshotString, implode(',',$bonuses));

                        if (strlen($sqlBadBonus) + 5 + strlen($thisSql) + strlen($sqlEndBadBonus) > $maxPacketSize) {
                            $delayedAuctionSql[] = $sqlBadBonus . $sqlEndBadBonus; // delayed since tblAuction row must be inserted first for foreign key
                            $sqlBadBonus = '';
                        }
                        $sqlBadBonus .= ($sqlBadBonus == '' ? $sqlStartBadBonus : ',') . $thisSql;

                        $bonuses = []; // remove bonuses attached to auction, they probably don't belong
                    }
                    if (!isset($existingIds[$auction['auc']])) { // new auction
                        DebugMessage(sprintf(
                            'House %s new item %d has useful bonuses %s, first auction with them after %d observations, %s bonuses!',
                            str_pad($house, 5, ' ', STR_PAD_LEFT), $auction['item'], implode(':', $usefulBonuses),
                            $observedWithoutBonusesCache[$auction['item']], $bonuses ? 'using' : 'ignoring'));
                    }
                }

                $bonusItemLevel = \Newsstand\BonusItemLevel::GetBonusItemLevel($bonuses, $equipBaseItemLevel[$auction['item']], $auction['lootedLevel']);
                if ($bonusItemLevel >= MIN_ITEM_LEVEL_PRICING) {
                    $pricingItemLevel = $bonusItemLevel;
                }
            }
            if ($auction['buyout'] != 0) {
                if (isset($auction['petSpeciesId'])) {
                    if (!isset($petInfo[$auction['petSpeciesId']])) {
                        $petInfo[$auction['petSpeciesId']] = array('a' => array(), 'tq' => 0);
                    }

                    $petInfo[$auction['petSpeciesId']]['a'][] = array(
                        'q' => $auction['quantity'],
                        'p' => $auction['buyout']
                    );
                    $petInfo[$auction['petSpeciesId']]['tq'] += $auction['quantity'];
                } else {
                    if (!isset($itemInfo[$auction['item']][$pricingItemLevel])) {
                        $itemInfo[$auction['item']][$pricingItemLevel] = array('a' => array(), 'tq' => 0);
                    }

                    $itemInfo[$auction['item']][$pricingItemLevel]['a'][] = array(
                        'q'   => $auction['quantity'],
                        'p'   => $auction['buyout'],
                    );
                    $itemInfo[$auction['item']][$pricingItemLevel]['tq'] += $auction['quantity'];
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
                if (isset($equipBaseItemLevel[$auction['item']])) {
                    if (!isset($enoughBonusesSeenCache[$auction['item']])) {
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
                            '(%u,%u,%u,%u,%u,1)',
                            $auction['item'],
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
                $auction['owner'] == '???' ? 0 :
                    (isset($sellerInfo[$auction['ownerRealm']][$auction['owner']]) ?
                        $sellerInfo[$auction['ownerRealm']][$auction['owner']]['id'] : 0),
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
                for ($y = count($bonuses); $y < MAX_BONUSES; $y++) {
                    $bonuses[] = 'null';
                }
                $bonuses = implode(',',$bonuses);
                $thisSql = sprintf('(%u,%u,%d,%d,%u,%s,%u,%s)',
                    $house,
                    $auction['auc'],
                    $auction['rand'],
                    $auction['seed'],
                    $auction['context'],
                    isset($auction['lootedLevel']) ? $auction['lootedLevel'] : 'null',
                    $bonusItemLevel,
                    $bonuses
                );

                if (strlen($sqlExtra) + 5 + strlen($thisSql) > $maxPacketSize) {
                    $delayedAuctionSql[] = $sqlExtra; // delayed since tblAuction row must be inserted first for foreign key
                    $sqlExtra = '';
                }
                $sqlExtra .= ($sqlExtra == '' ? $sqlStartExtra : ',') . $thisSql;
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

        if ($sqlBadBonus != '') {
            $delayedAuctionSql[] = $sqlBadBonus . $sqlEndBadBonus;
        }

        if (count($delayedAuctionSql)) {
            DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating tblAuctionExtra, tblAuctionPet");
        }
        while (count($delayedAuctionSql)) {
            DBQueryWithError($ourDb, array_pop($delayedAuctionSql));
        }
        unset($sqlBonusesSeen, $sqlPet, $sqlExtra, $sqlBadBonus, $delayedAuctionSql);

        $sql = <<<EOF
insert ignore into tblAuctionRare (house, id, prevseen) (
select a.house, a.id, tis.lastseen
from tblAuction a
left join tblAuctionExtra ae on ae.house=a.house and ae.id=a.id
left join tblItemSummary tis on tis.house=a.house and tis.item=a.item and tis.level=ifnull(ae.level,0)
where a.house = %d
and a.id > %d
and a.item != %d
%s
and ifnull(tis.lastseen, '2000-01-01') < timestampadd(day,-14,'%s'))
EOF;
        $sql = sprintf($sql, $house, $lastMax, BATTLE_PET_CAGE_ITEM, $hasRollOver ? ' and a.id < 0x20000000 ' : '', $snapshotString);
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating tblAuctionRare");
        DBQueryWithError($ourDb, $sql);
    }

    $rareDeletes = [];

    $preDeleted = count($itemInfo);
    foreach ($existingIds as $existingId => &$oldRow) {
        if ((!isset($existingPetIds[$existingId])) && (!isset($itemInfo[$oldRow[EXISTING_COL_ITEM]][$oldRow[EXISTING_COL_LEVEL]]))) {
            $rareDeletes[$level][] = $oldRow[EXISTING_COL_ITEM];
            $itemInfo[$oldRow[EXISTING_COL_ITEM]][$oldRow[EXISTING_COL_LEVEL]] = array('tq' => 0, 'a' => array());
        }
    }
    unset($oldRow);
    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating " . count($itemInfo) . " item info (including " . (count($itemInfo) - $preDeleted) . " no longer available)");
    UpdateItemInfo($house, $itemInfo, $snapshot, $prevSnapshot);
    unset($itemInfo);

    $sql = 'delete from tblUserRareReport where house = %d and level = %d and item in (%s)';
    foreach ($rareDeletes as $level => $itemIds) {
        $chunked = array_chunk($itemIds, 200);
        foreach ($chunked as $chunk) {
            DBQueryWithError($ourDb, sprintf($sql, $house, $level, implode(',', $chunk)));
        }
    }

    $preDeleted = count($petInfo);
    foreach ($existingPetIds as &$oldRow) {
        if (!isset($petInfo[$oldRow['species']])) {
            $petInfo[$oldRow['species']] = array('tq' => 0, 'a' => array());
        }
    }
    unset($oldRow);
    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating " . count($petInfo) . " pet info (including " . (count($petInfo) - $preDeleted) . " no longer available)");
    UpdatePetInfo($house, $petInfo, $snapshot);

    if ($sellerInfo) {
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating seller history");
        UpdateSellerInfo($sellerInfo, $house, $snapshot);
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

        $sqlStart = "SELECT name, if(lastseen is null, null, id) id FROM tblSeller WHERE realm = $realmId AND name IN (";
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
                        $foundNames++;
                        if (is_null($someIds[$n]['id'])) {
                            unset($sellerInfo[$realmName][$someIds[$n]['name']]);
                            continue;
                        }
                        $sellerInfo[$realmName][$someIds[$n]['name']]['id'] = $someIds[$n]['id'];
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
                    $foundNames++;
                    if (is_null($someIds[$n]['id'])) {
                        unset($sellerInfo[$realmName][$someIds[$n]['name']]);
                        continue;
                    }
                    $sellerInfo[$realmName][$someIds[$n]['name']]['id'] = $someIds[$n]['id'];
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

function UpdateItemInfo($house, $itemInfo, $snapshot, $prevSnapshot)
{
    global $db, $maxPacketSize;

    $month = (intval(date('Y', $snapshot), 10) - 2014) * 12 + intval(date('m', $snapshot), 10);
    $day = date('d', $snapshot);
    $hour = date('H', $snapshot);
    $dateString = date('Y-m-d', $snapshot);

    $snapshotString = date('Y-m-d H:i:s', $snapshot);
    $sqlStart = 'INSERT INTO tblItemSummary (house, item, level, price, quantity, lastseen) VALUES ';
    $sqlEnd = ' on duplicate key update quantity=values(quantity), price=if(quantity=0,price,values(price)), lastseen=if(quantity=0,lastseen,values(lastseen))';
    $sql = '';

    ksort($itemInfo, SORT_NUMERIC); // improves insert performance(?)

    foreach ($itemInfo as $item => $levels) {
        ksort($levels);

        foreach ($levels as $level => $info) {
            $price = GetMarketPrice($info);

            $sqlBit = sprintf('(%d,%u,%u,%u,%u,\'%s\')', $house, $item, $level, $price, $info['tq'], $snapshotString);
            if (strlen($sql) + strlen($sqlBit) + strlen($sqlEnd) + 5 > $maxPacketSize) {
                DBQueryWithError($db, $sql . $sqlEnd);
                $sql = '';
            }
            $sql .= ($sql == '' ? $sqlStart : ',') . $sqlBit;
        }
    }

    if ($sql != '') {
        DBQueryWithError($db, $sql . $sqlEnd);
    }

    // ugly hack to clean up rows whose auctions we didn't notice went missing
    $stmt = $db->prepare('update tblItemSummary set quantity = 0 where quantity > 0 and house = ? and lastseen < ?');
    $stmt->bind_param('is', $house, $snapshotString);
    $stmt->execute();
    $howMany = $stmt->affected_rows;
    $stmt->close();
    if ($howMany > 0) {
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " fixed quantity for $howMany item summary rows which we didn't notice went missing");
    }

    DBQueryWithError($db, 'create temporary table ttblPriceAdjustment like ttblItemSummaryTemplate');

    // "when my qty > 0, everyone with lower ilevel is capped at my price"
    DBQueryWithError($db, 'truncate ttblPriceAdjustment');

    $sql = <<<'EOF'
insert into ttblPriceAdjustment
(select s1.item, s1.level, min(s2.price)
from tblItemSummary s1
join tblItemSummary s2 on
	s2.house = s1.house and
    s2.item = s1.item and
    s2.level > s1.level
where s1.house = ? and s2.price < s1.price and s2.quantity > 0
group by s1.item, s1.level)
EOF;
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $stmt->close();

    $updateSql = <<<'EOF'
update tblItemSummary, ttblPriceAdjustment
set tblItemSummary.price = ttblPriceAdjustment.price
where tblItemSummary.house = ?
and tblItemSummary.item = ttblPriceAdjustment.item
and tblItemSummary.level = ttblPriceAdjustment.level
EOF;
    $stmt = $db->prepare($updateSql);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $stmt->close();

    // "when my qty > 0, everyone with higher ilvl and qty=0 gets at least my price"
    DBQueryWithError($db, 'truncate ttblPriceAdjustment');

    $sql = <<<'EOF'
insert into ttblPriceAdjustment
(select s1.item, s1.level, max(s2.price)
from tblItemSummary s1
join tblItemSummary s2 on
	s2.house = s1.house and
    s2.item = s1.item and
    s2.level < s1.level
where s1.house = ? and s2.price > s1.price and s2.quantity > 0 and s1.quantity = 0
group by s1.item, s1.level)
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare($updateSql);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $stmt->close();

    DBQueryWithError($db, 'drop temporary table ttblPriceAdjustment');

    // update history tables from summary data

    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating item history hourly");

    $prevSnapshotString = date('Y-m-d H:i:s', $prevSnapshot);

    $sql = <<<'EOF'
insert into tblItemHistoryHourly (house, item, level, `when`)
select s.house, s.item, s.level, ?
from tblItemSummary s
join tblItemSummary s2 on s2.item = s.item
left join tblItemHistoryHourly h on h.house = s.house and h.item = s.item and h.level = s.level and h.`when` = ?
WHERE s.house = ? and s2.house = ? and s2.lastseen >= ? and h.house is null
group by s.house, s.item, s.level
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ssiis', $dateString, $dateString, $house, $house, $prevSnapshotString);
    $stmt->execute();
    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . sprintf(" inserted %d hourly history rows", $stmt->affected_rows));
    $stmt->close();

    $sql = <<<'EOF'
update tblItemHistoryHourly h
join tblItemSummary s on h.item = s.item and h.level = s.level
join tblItemSummary s2 on s2.item = s.item
set h.silver%1$s = round(s.price/100), h.quantity%1$s = s.quantity
where h.house = ? and s.house = ? and s2.house = ?
and s2.lastseen >= ?
and h.`when` = ?
and ifnull(h.quantity%1$s, 0) <= s.quantity
EOF;

    $stmt = $db->prepare(sprintf($sql, $hour));
    $stmt->bind_param('iiiss', $house, $house, $house, $prevSnapshotString, $dateString);
    $stmt->execute();
    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . sprintf(" updated %d hourly history rows", $stmt->affected_rows));
    $stmt->close();
}

function UpdatePetInfo($house, &$petInfo, $snapshot)
{
    global $db, $maxPacketSize;

    $hour = date('H', $snapshot);
    $dateString = date('Y-m-d', $snapshot);

    $snapshotString = date('Y-m-d H:i:s', $snapshot);
    $sqlStart = 'INSERT INTO tblPetSummary (house, species, price, quantity, lastseen) VALUES ';
    $sqlEnd = ' on duplicate key update quantity=values(quantity), price=if(quantity=0,price,values(price)), lastseen=if(quantity=0,lastseen,values(lastseen))';
    $sql = '';

    $sqlHistoryStart = sprintf('INSERT INTO tblPetHistoryHourly (house, species, `when`, `silver%1$s`, `quantity%1$s`) VALUES ', $hour);
    $sqlHistoryEnd = sprintf(' on duplicate key update `silver%1$s`=if(values(`quantity%1$s`) > ifnull(`quantity%1$s`,0), values(`silver%1$s`), `silver%1$s`), `quantity%1$s`=if(values(`quantity%1$s`) > ifnull(`quantity%1$s`,0), values(`quantity%1$s`), `quantity%1$s`)', $hour);
    $sqlHistory = '';

    foreach ($petInfo as $species => &$info) {
        $price = GetMarketPrice($info);
        $sqlBit = sprintf('(%d,%u,%u,%u,\'%s\')', $house, $species, $price, $info['tq'], $snapshotString);
        if (strlen($sql) + strlen($sqlBit) + strlen($sqlEnd) + 5 > $maxPacketSize) {
            DBQueryWithError($db, $sql . $sqlEnd);
            $sql = '';
        }
        $sql .= ($sql == '' ? $sqlStart : ',') . $sqlBit;

        if ($info['tq'] > 0) {
            $sqlHistoryBit = sprintf('(%d,%u,\'%s\',%u,%u)', $house, $species, $dateString, round($price / 100), $info['tq']);
            if (strlen($sqlHistory) + strlen($sqlHistoryBit) + strlen($sqlHistoryEnd) + 5 > $maxPacketSize) {
                DBQueryWithError($db, $sqlHistory . $sqlHistoryEnd);
                $sqlHistory = '';
            }
            $sqlHistory .= ($sqlHistory == '' ? $sqlHistoryStart : ',') . $sqlHistoryBit;
        }
    }
    unset($info);

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