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
define('OBSERVED_WITHOUT_BONUSES_LIMIT', 500); // if we see this many auctions of an item without any having bonuses, assume the item doesn't get bonuses

define('EXISTING_SQL', '
SELECT a.id, a.bid, a.buy, a.timeleft+0 timeleft, a.item,
    if(ae.level is null,
        if(i.class in (2,4), i.level, 0),
        if(ae.level >= ' . MIN_ITEM_LEVEL_PRICING . ', ae.level, i.level)
    ) level, a.quantity
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
define('EXISTING_COL_QUANTITY', 5);

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
    $json = stream_get_contents($handle);
    fclose($handle);
    unlink(SNAPSHOT_PATH . $fileName);

    if (substr($json, 0, 2) === "\037\213") {
        $json = gzdecode($json);
    }
    $json = json_decode($json, true);

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
    global $usefulBonusesCache;
    global $TIMELEFT_ENUM;

    $snapshotString = date('Y-m-d H:i:s', $snapshot);
    $startTimer = microtime(true);

    $ourDb = DBConnect(true);

    $region = $houseRegionCache[$house]['region'];

    $existingIds = [];
    $stmt = $ourDb->prepare(EXISTING_SQL);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $id = $bid = $buy = $timeLeft = $item = $level = $quantity = null;
    $stmt->bind_result($id, $bid, $buy, $timeLeft, $item, $level, $quantity);
    while ($stmt->fetch()) {
        $existingIds[$id] = [$bid, $buy, $timeLeft, $item, $level, $quantity];
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

    $jsonAuctions =& $json['auctions'];

    if ($jsonAuctions) {
        $auctionCount = count($jsonAuctions);

        for ($x = 0; $x < $auctionCount; $x++) {
            $auctionId = $jsonAuctions[$x]['id'];

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

    $sqlStart = 'REPLACE INTO tblAuction (house, id, item, quantity, bid, buy, timeleft) VALUES ';
    $sqlStartPet = 'REPLACE INTO tblAuctionPet (house, id, species, breed, `level`, quality) VALUES ';
    $sqlStartExtra = 'REPLACE INTO tblAuctionExtra (house, id, `rand`, `seed`, `context`, `lootedlevel`, `level`) VALUES ';
    $sqlStartBonus = 'REPLACE INTO tblAuctionBonus (house, id, bonus) VALUES ';

    $totalAuctions = 0;
    $itemInfo = array();
    $petInfo = array();

    if ($jsonAuctions) {
        $auctionCount = count($jsonAuctions);
        $sql = $sqlPet = $sqlExtra = $sqlBonus = '';
        $delayedAuctionSql = [];

        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " processing $auctionCount auctions");
        while ($auction = array_pop($jsonAuctions)) {
            if (($auction['quantity'] ?? 0) == 0) {
                // Thanks, Blizz, for including random quantity=0 auctions. We'll assume they're sold/cancelled.
                DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " Skipping quantity=0: " . json_encode($auction));
                continue;
            }
            if (isset($auction['item']['pet_breed_id'])) {
                $auction['item']['pet_breed_id'] = (($auction['item']['pet_breed_id'] - 3) % 10) + 3; // squash gender
            }
            $auction['time_left'] = isset($TIMELEFT_ENUM[$auction['time_left']]) ? $TIMELEFT_ENUM[$auction['time_left']] : 0;
            if (!isset($auction['buyout'])) {
                if (isset($auction['unit_price'])) {
                    $auction['buyout'] = $auction['unit_price'] * $auction['quantity'];
                } else {
                    $auction['buyout'] = 0;
                }
            }
            if (!isset($auction['bid'])) {
                $auction['bid'] = 0;
            }

            $auction['item']['lootedLevel'] = null;
            if (isset($auction['item']['modifiers'])) {
                foreach ($auction['item']['modifiers'] as $modObj) {
                    if (isset($modObj['type']) && ($modObj['type'] == 9)) {
                        $auction['item']['lootedLevel'] = intval($modObj['value']);
                    }
                }
            }

            $totalAuctions++;
            $bonuses = [];
            $pricingItemLevel = $bonusItemLevel = $equipBaseItemLevel[$auction['item']['id']] ?? 0;
            if (!isset($auction['item']['pet_species_id']) && $bonusItemLevel) {
                if (!isset($auction['item']['bonus_lists'])) {
                    $auction['item']['bonus_lists'] = [];
                }
                $bonuses = $auction['item']['bonus_lists'];
                $bonuses = array_unique($bonuses, SORT_NUMERIC);
                sort($bonuses, SORT_NUMERIC);

                $usefulBonuses = [];
                foreach ($bonuses as $bonus) {
                    if (isset($usefulBonusesCache[$bonus])) {
                        $usefulBonuses[$bonus] = $bonus;
                    }
                }

                $bonusItemLevel = \Newsstand\BonusItemLevel::GetBonusItemLevel($bonuses, $equipBaseItemLevel[$auction['item']['id']], $auction['item']['lootedLevel']);
                if ($bonusItemLevel >= MIN_ITEM_LEVEL_PRICING) {
                    $pricingItemLevel = $bonusItemLevel;
                }
            }
            if ($auction['buyout'] != 0) {
                if (isset($auction['item']['pet_species_id'])) {
                    if (!isset($petInfo[$auction['item']['pet_species_id']])) {
                        $petInfo[$auction['item']['pet_species_id']] = array('a' => array(), 'tq' => 0);
                    }

                    $petInfo[$auction['item']['pet_species_id']]['a'][] = array(
                        'q' => $auction['quantity'],
                        'p' => $auction['buyout']
                    );
                    $petInfo[$auction['item']['pet_species_id']]['tq'] += $auction['quantity'];
                } else {
                    if (!isset($itemInfo[$auction['item']['id']][$pricingItemLevel])) {
                        $itemInfo[$auction['item']['id']][$pricingItemLevel] = array('a' => array(), 'tq' => 0);
                    }

                    $itemInfo[$auction['item']['id']][$pricingItemLevel]['a'][] = array(
                        'q'   => $auction['quantity'],
                        'p'   => $auction['buyout'],
                    );
                    $itemInfo[$auction['item']['id']][$pricingItemLevel]['tq'] += $auction['quantity'];
                }
            }

            if (isset($existingIds[$auction['id']])) {
                $needUpdate = ($auction['bid'] != $existingIds[$auction['id']][EXISTING_COL_BID]);
                $needUpdate |= ($auction['buyout'] != $existingIds[$auction['id']][EXISTING_COL_BUY]);
                $needUpdate |= ($auction['quantity'] != $existingIds[$auction['id']][EXISTING_COL_QUANTITY]);
                $needUpdate |= ($auction['time_left'] != $existingIds[$auction['id']][EXISTING_COL_TIMELEFT]);
                unset($existingIds[$auction['id']]);
                unset($existingPetIds[$auction['id']]);
                if (!$needUpdate) {
                    continue;
                }
            } else {
                // new auction
            }

            $thisSql = sprintf(
                '(%u, %u, %u, %u, %u, %u, %u)',
                $house,
                $auction['id'],
                $auction['item']['id'],
                $auction['quantity'],
                $auction['bid'],
                $auction['buyout'],
                $auction['time_left']
            );
            if (strlen($sql) + 5 + strlen($thisSql) > $maxPacketSize) {
                DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating tblAuction (" . round($totalAuctions / $auctionCount * 100) . '%)');
                DBQueryWithError($ourDb, $sql);
                $sql = '';
            }
            $sql .= ($sql == '' ? $sqlStart : ',') . $thisSql;

            if (isset($auction['item']['pet_species_id'])) {
                $thisSql = sprintf(
                    '(%u, %u, %u, %u, %u, %u)',
                    $house,
                    $auction['id'],
                    $auction['item']['pet_species_id'],
                    $auction['item']['pet_breed_id'],
                    $auction['item']['pet_level'],
                    $auction['item']['pet_quality_id']
                );

                if (strlen($sqlPet) + 5 + strlen($thisSql) > $maxPacketSize) {
                    $delayedAuctionSql[] = $sqlPet; // delayed since tblAuction row must be inserted first for foreign key
                    $sqlPet = '';
                }
                $sqlPet .= ($sqlPet == '' ? $sqlStartPet : ',') . $thisSql;
            } else if (isset($equipBaseItemLevel[$auction['item']['id']])) {
                $thisSql = sprintf('(%u,%u,%d,%d,%u,%s,%u)',
                    $house,
                    $auction['id'],
                    $auction['item']['rand'] ?? 0,
                    $auction['item']['seed'] ?? 0,
                    $auction['item']['context'] ?? 0,
                    $auction['item']['lootedLevel'] ?? 'null',
                    $bonusItemLevel
                );

                if (strlen($sqlExtra) + 5 + strlen($thisSql) > $maxPacketSize) {
                    $delayedAuctionSql[] = $sqlExtra; // delayed since tblAuction row must be inserted first for foreign key
                    $sqlExtra = '';
                }
                $sqlExtra .= ($sqlExtra == '' ? $sqlStartExtra : ',') . $thisSql;

                foreach ($bonuses as $bonus) {
                    $thisSql = sprintf('(%u,%u,%u)', $house, $auction['id'], $bonus);

                    if (strlen($sqlBonus) + 5 + strlen($thisSql) > $maxPacketSize) {
                        $delayedAuctionSql[] = $sqlBonus; // delayed since tblAuction row must be inserted first for foreign key
                        $sqlBonus = '';
                    }
                    $sqlBonus .= ($sqlBonus == '' ? $sqlStartBonus : ',') . $thisSql;
                }
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

        if ($sqlBonus != '') {
            $delayedAuctionSql[] = $sqlBonus;
        }

        if (count($delayedAuctionSql)) {
            DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " updating tblAuctionExtra, tblAuctionBonus, tblAuctionPet");
        }
        while (count($delayedAuctionSql)) {
            DBQueryWithError($ourDb, array_pop($delayedAuctionSql));
        }
        unset($sqlPet, $sqlExtra, $sqlBonus, $delayedAuctionSql);

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
insert ignore into tblItemHistoryHourly (house, item, level, `when`)
select s.house, s.item, s.level, ?
from tblItemSummary s
join tblItemSummary s2 on s2.item = s.item
WHERE s.house = ? and s2.house = ? and s2.lastseen >= ?
group by s.house, s.item, s.level
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('siis', $dateString, $house, $house, $prevSnapshotString);
    $stmt->execute();
    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . sprintf(" insert-ignored %d hourly history rows", $stmt->affected_rows));
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

/**
 * Return the price per item to buy 15% of the total available quantity. Returns 0 if no quantity is available.
 *
 * @param array $info
 * @return int
 */
function GetMarketPrice(&$info) {
    if ($info['tq'] == 0) {
        return 0;
    }

    // Sort cheapest and smallest auctions first.
    usort($info['a'], function ($a, $b) {
        $aPrice = ceil($a['p'] / $a['q']);
        $bPrice = ceil($b['p'] / $b['q']);

        return ($aPrice - $bPrice) ?: ($a['q'] - $b['q']);
    });

    // How many we want to buy.
    $targetQuantity = ceil($info['tq'] * 0.15);

    // How many we bought so far.
    $purchasedQuantity = 0;

    // How much we spent to buy what we bought so far.
    $spent = 0;

    // Which auction we're examining in the list.
    $index = 0;

    while ($purchasedQuantity < $targetQuantity) {
        $auction = $info['a'][$index++];
        $remainingQuantity = $targetQuantity - $purchasedQuantity;
        if ($remainingQuantity >= $auction['q']) {
            $purchasedQuantity += $auction['q'];
            $spent += $auction['p'];
        } else {
            // Auctions of stackable items can now be split.
            $purchasedQuantity += $remainingQuantity;
            $spent += $auction['p'] * $remainingQuantity / $auction['q'];
        }
    }

    return (int)ceil($spent / $purchasedQuantity);
}

function DBQueryWithError(&$db, $sql)
{
    $queryOk = $db->query($sql);
    if (!$queryOk) {
        DebugMessage("SQL error: " . $db->errno . ' ' . $db->error . " - " . substr(preg_replace('/[\r\n]/', ' ', $sql), 0, 500), E_USER_WARNING);
    }

    return $queryOk;
}
