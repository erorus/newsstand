<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');
require_once('../incl/subscription.incl.php');
require_once('../incl/BonusItemLevel.incl.php');

RunMeNTimes(1);
CatchKill();

define('SNAPSHOT_PATH', '/var/newsstand/snapshots/watch/');
define('MAX_BONUSES', 6); // is a count, 1 through N
define('ARRAY_INDEX_BUYOUT', 1);
define('ARRAY_INDEX_QUANTITY', 0);
define('ARRAY_INDEX_AUCTIONS', 1);
define('ARRAY_INDEX_MARKETPRICE', 2);

ini_set('memory_limit', '512M');

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

            /*
            if (!MCHouseLock($house, 3)) {
                $lockFail = true;
                continue;
            }
            */

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
        //MCHouseUnlock($house);
        return 0;
    }

    ParseAuctionData($house, $snapshot, $json);
    //MCHouseUnlock($house);
    return 0;
}

function ParseAuctionData($house, $snapshot, &$json)
{
    global $maxPacketSize;
    global $houseRegionCache;
    global $equipBaseItemLevel;

    $snapshotString = date('Y-m-d H:i:s', $snapshot);
    $startTimer = microtime(true);

    $region = $houseRegionCache[$house]['region'];

    $lastMax = 0;
    $lastMaxUpdated = 0;

    $jsonAuctions = [];
    if (isset($json['auctions']['auctions'])) {
        $jsonAuctions =& $json['auctions']['auctions'];
    } elseif (isset($json['auctions']) && (count($json['auctions']) > 5)) {
        $jsonAuctions =& $json['auctions'];
    }

    $ourDb = DBConnect(true);
    $ourDb->query('set transaction isolation level read uncommitted, read only');
    $ourDb->begin_transaction();

    $stmt = $ourDb->prepare('SELECT ifnull(maxid,0), unix_timestamp(updated) FROM tblSnapshot s WHERE house = ? AND updated = (SELECT max(s2.updated) FROM tblSnapshot s2 WHERE s2.house = ? AND s2.updated < ?)');
    $stmt->bind_param('iis', $house, $house, $snapshotString);
    $stmt->execute();
    $stmt->bind_result($lastMax, $lastMaxUpdated);
    if ($stmt->fetch() !== true) {
        $lastMax = 0;
        $lastMaxUpdated = 0;
    }
    $stmt->close();

    $itemBuyouts = [];
    $itemBids = [];
    $petBuyouts = [];
    $petBids = [];
    $newAuctionItems = [];
    $oldAuctionItems = [];
    $emptyItemInfo = [ARRAY_INDEX_QUANTITY => 0, ARRAY_INDEX_AUCTIONS => []];

    if ($jsonAuctions) {
        $auctionCount = count($jsonAuctions);
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " parsing $auctionCount auctions");

        while ($auction = array_pop($jsonAuctions)) {
            $isNewAuction = ($auction['auc'] - $lastMax);
            $isNewAuction = ($isNewAuction > 0) || ($isNewAuction < -0x20000000);

            if (isset($auction['petBreedId'])) {
                $auction['petBreedId'] = (($auction['petBreedId'] - 3) % 10) + 3; // squash gender
            }
            $hasBuyout = ($auction['buyout'] != 0);

            if (isset($auction['petSpeciesId'])) {
                if ($hasBuyout) {
                    $aucList = &$petBuyouts;
                } else {
                    $aucList = &$petBids;
                }
                if (!isset($aucList[$auction['petSpeciesId']])) {
                    $aucList[$auction['petSpeciesId']] = $emptyItemInfo;
                }
                if ($hasBuyout) {
                    AuctionListInsert($aucList[$auction['petSpeciesId']][ARRAY_INDEX_AUCTIONS], $auction['quantity'], $auction['buyout']);
                }
                $aucList[$auction['petSpeciesId']][ARRAY_INDEX_QUANTITY] += $auction['quantity'];
            } else {
                if ($hasBuyout) {
                    $aucList = &$itemBuyouts;
                } else {
                    $aucList = &$itemBids;
                }
                $pricingItemLevel = $bonusItemLevel = $equipBaseItemLevel[$auction['item']] ?? 0;
                if ($bonusItemLevel && isset($auction['bonusLists'])) {
                    $bonuses = [];
                    for ($y = 0; $y < count($auction['bonusLists']); $y++) {
                        if (isset($auction['bonusLists'][$y]['bonusListId']) && $auction['bonusLists'][$y]['bonusListId']) {
                            $bonuses[] = intval($auction['bonusLists'][$y]['bonusListId'],10);
                        }
                    }
                    $bonuses = array_unique($bonuses, SORT_NUMERIC);
                    sort($bonuses, SORT_NUMERIC);

                    if (count($bonuses)) {
                        $auction['lootedLevel'] = null;
                        if (isset($auction['modifiers'])) {
                            foreach ($auction['modifiers'] as $modObj) {
                                if (isset($modObj['type']) && ($modObj['type'] == 9)) {
                                    $auction['lootedLevel'] = intval($modObj['value']);
                                }
                            }
                        }
                        $bonusItemLevel = \Newsstand\BonusItemLevel::GetBonusItemLevel($bonuses,
                            $equipBaseItemLevel[$auction['item']], $auction['lootedLevel']);
                        if ($bonusItemLevel >= MIN_ITEM_LEVEL_PRICING) {
                            $pricingItemLevel = $bonusItemLevel;
                        }
                    }
                }
                if (!isset($aucList[$auction['item']][$pricingItemLevel])) {
                    $aucList[$auction['item']][$pricingItemLevel] = $emptyItemInfo;
                }

                AuctionListInsert($aucList[$auction['item']][$pricingItemLevel][ARRAY_INDEX_AUCTIONS], $auction['quantity'], $hasBuyout ? $auction['buyout'] : $auction['bid']);
                $aucList[$auction['item']][$pricingItemLevel][ARRAY_INDEX_QUANTITY] += $auction['quantity'];

                if ($isNewAuction) {
                    if (!isset($oldAuctionItems[$auction['item']][$pricingItemLevel])) {
                        $newAuctionItems[$auction['item']][$pricingItemLevel] = true;
                    }
                } else {
                    $oldAuctionItems[$auction['item']][$pricingItemLevel] = true;
                    unset($newAuctionItems[$auction['item']][$pricingItemLevel]);
                }
            }
        }
    }
    unset($json, $jsonAuctions, $oldAuctionItems);

    foreach ($itemBuyouts as $itemId => &$pricingItemLevels) {
        if (count($pricingItemLevels) <= 1) {
            continue;
        }

        ksort($pricingItemLevels);
        $prevLevels = [];
        foreach ($pricingItemLevels as $itemLevel => &$info) {
            $myPrice = GetMarketPrice($info);
            foreach ($prevLevels as $prevLevel) {
                if ($pricingItemLevels[$prevLevel][ARRAY_INDEX_MARKETPRICE] > $myPrice) {
                    $pricingItemLevels[$prevLevel][ARRAY_INDEX_MARKETPRICE] = $myPrice;
                }
            }
            $prevLevels[] = $itemLevel;
        }
        unset($info);
    }
    unset($pricingItemLevels, $prevLevels);

    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " found ".count($itemBuyouts)." distinct items, ".count($petBuyouts)." species, ".count($newAuctionItems)." new items");

    $watchesSet = []; $watchesSetCount = 0;
    $watchesUnset = []; $watchesUnsetCount = 0;
    $updateObserved = [];
    $watchesCount = 0;
    $sql = <<<'EOF'
select uw.`user`, uw.seq, uw.item, uw.level, uw.species, uw.direction, uw.quantity, uw.price,
    if(uw.observed is null, 0, 1) isset, if(uw.reported > uw.observed, 1, 0) wasreported
from tblUserWatch uw
join tblUser u on uw.user = u.id
where uw.deleted is null
and ((uw.region = ? and uw.observed is null) or uw.house = ?)
and (uw.observed is null or uw.observed < ?)
and (u.paiduntil > now() or u.lastseen > timestampadd(day, ?, now()))
order by uw.item, uw.species
EOF;

    $stmt = $ourDb->prepare($sql);
    $freeDays = -1 * SUBSCRIPTION_WATCH_FREE_LAST_LOGIN_DAYS;
    $stmt->bind_param('sisi', $region, $house, $snapshotString, $freeDays);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $watchSatisfied = false;
        $watchesCount++;
        if ($row['item']) {
            $bidQtyAtAboveLevel = 0;
            $partialItemInfo = $emptyItemInfo;
            if (isset($itemBuyouts[$row['item']])) {
                $multipleLevelsForSale = count($itemBuyouts[$row['item']]) > 1;
                foreach ($itemBuyouts[$row['item']] as $level => $info) {
                    if ($level < $row['level']) {
                        continue;
                    }
                    $partialItemInfo[ARRAY_INDEX_QUANTITY] += $info[ARRAY_INDEX_QUANTITY];
                    $partialItemInfo[ARRAY_INDEX_AUCTIONS] = array_merge($partialItemInfo[ARRAY_INDEX_AUCTIONS], $info[ARRAY_INDEX_AUCTIONS]);
                    if ($multipleLevelsForSale) {
                        if (!isset($partialItemInfo[ARRAY_INDEX_MARKETPRICE])) {
                            if (!isset($info[ARRAY_INDEX_MARKETPRICE])) {
                                DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " expected to find marketprice for item {$row['item']} level $level");
                                DebugMessage(print_r($itemBuyouts[$row['item']], true));
                            } else {
                                $partialItemInfo[ARRAY_INDEX_MARKETPRICE] = $info[ARRAY_INDEX_MARKETPRICE];
                            }
                        }
                    }
                }
                if ($multipleLevelsForSale) {
                    usort($partialItemInfo[ARRAY_INDEX_AUCTIONS], 'AuctionListComparitor');
                }
            }
            if (isset($itemBids[$row['item']])) {
                foreach ($itemBids[$row['item']] as $level => $info) {
                    if ($level >= $row['level']) {
                        $bidQtyAtAboveLevel += $info[ARRAY_INDEX_QUANTITY];
                    }
                }
            }
            if ($partialItemInfo[ARRAY_INDEX_QUANTITY] <= 0) {
                // none of this item for sale
                if (is_null($row['quantity'])) {
                    // market price notification, without quantity it does not change
                    continue;
                }
                if (is_null($row['price']) && $row['direction'] == 'Over' && $row['quantity'] == 0 && $bidQtyAtAboveLevel) {
                    // when no quantity avail for buyout, and we're looking for any quantity over X, check bids too
                    $watchSatisfied = $bidQtyAtAboveLevel;
                } else {
                    $watchSatisfied = WatchSatisfied($emptyItemInfo, $row['direction'], $row['quantity'], $row['price']);
                }
            } else {
                $watchSatisfied = WatchSatisfied($partialItemInfo, $row['direction'], $row['quantity'], $row['price']);
            }
        } else if ($row['species']) {
            if (!isset($petBuyouts[$row['species']])) {
                // none of this pet for sale
                if (is_null($row['quantity'])) {
                    // market price notification, without quantity it does not change
                    continue;
                }
                if (is_null($row['price']) && $row['direction'] == 'Over' && $row['quantity'] == 0 && isset($petBids[$row['species']])) {
                    // when no quantity avail for buyout, and we're looking for any quantity over X, check bids too
                    $watchSatisfied = $petBids[$row['species']][ARRAY_INDEX_QUANTITY];
                } else {
                    $watchSatisfied = WatchSatisfied($emptyItemInfo, $row['direction'], $row['quantity'], $row['price']);
                }
            } else {
                $watchSatisfied = WatchSatisfied($petBuyouts[$row['species']], $row['direction'], $row['quantity'], $row['price']);
            }
        }
        if ($watchSatisfied === false) {
            if ($row['isset']) {
                $watchesUnset[$row['user']][] = $row['seq'];
                $watchesUnsetCount++;
            }
        } elseif (!$row['wasreported']) {
            $watchesSet[$row['user']][$row['seq']] = $watchSatisfied;
            $watchesSetCount++;
            if (!$row['isset']) {
                $updateObserved[$row['user']] = true;
            }
        }
    }
    $result->close();
    $stmt->close();

    $ourDb->commit(); // end read-uncommitted transaction

    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " reviewed $watchesCount watches, $watchesSetCount set, $watchesUnsetCount unset");

    $queryCount = 0;
    $sql = 'update tblUserWatch set house = if(region is null, house, null), currently = null, observed = null where user = %d and seq in (%s)';
    foreach ($watchesUnset as $user => $allSeqs) {
        $chunks = array_chunk($allSeqs, 200);
        foreach ($chunks as $seqs) {
            DBQueryWithError($ourDb, sprintf($sql, $user, implode(',', $seqs)));
            $queryCount++;
        }
    }

    $sql = 'update tblUserWatch set house = ?, currently = ?, observed = ifnull(observed, ?) where user = ? and seq = ?';
    if (count($watchesSet)) {
        $stmt = $ourDb->prepare($sql);
        $boundCurrently = $boundUser = $boundSeq = null;
        $stmt->bind_param('issii', $house, $boundCurrently, $snapshotString, $boundUser, $boundSeq);
        foreach ($watchesSet as $user => $allSeqs) {
            foreach ($allSeqs as $seq => $currently) {
                $boundCurrently = $currently;
                $boundUser = $user;
                $boundSeq = $seq;
                $stmt->execute();
                $queryCount++;
            }
        }
        $stmt->close();
    }

    // check unusual items
    $ok = DBQueryWithError($ourDb, 'create temporary table ttblRareStage like ttblRareStageTemplate');
    if ($ok) {
        $sqlStart = 'insert into ttblRareStage (item, level, price) values ';
        $sql = '';
        foreach ($newAuctionItems as $itemId => $pricingItemLevels) {
            foreach ($pricingItemLevels as $pricingItemLevel => $zz) {
                $thisSql = sprintf('(%d,%d,%s)', $itemId, $pricingItemLevel,
                    isset($itemBuyouts[$itemId][$pricingItemLevel]) ?
                        GetMarketPrice($itemBuyouts[$itemId][$pricingItemLevel]) :
                        GetMarketPrice($itemBids[$itemId][$pricingItemLevel], 1)
                );

                if (strlen($sql) + 5 + strlen($thisSql) > $maxPacketSize) {
                    $ok &= DBQueryWithError($ourDb, $sql);
                    $queryCount++;
                    $sql = '';
                }
                $sql .= ($sql == '' ? $sqlStart : ',') . $thisSql;
            }
        }
        if ($sql != '') {
            $ok &= DBQueryWithError($ourDb, $sql);
            $queryCount++;
        }

        DBQueryWithError($ourDb, 'set transaction isolation level repeatable read');
        $ourDb->begin_transaction();

        $sqls = [];
        $sqls[] = <<<'EOF'
select rs.item, rs.level, unix_timestamp(s.lastseen)
from ttblRareStage rs
join tblItemSummary s on rs.item = s.item and rs.level = s.level
where s.house = ?
EOF;
        $sqls[] = <<<'EOF'
select rs.item, rs.level, max(unix_timestamp(ar.prevseen))
from ttblRareStage rs
join tblAuction a on a.item = rs.item + 0
join tblAuctionRare ar on ar.house = a.house and ar.id = a.id
left join tblAuctionExtra ae on ae.house = a.house and ae.id = a.id
where ifnull(ae.level, rs.level) = rs.level
and a.house = ?
group by rs.item, rs.level
EOF;

        $dated = [];
        $summaryLate = $summaryRows = $addedRows = $updatedRows = 0;
        for($x = 0; $x < count($sqls); $x++) {
            $stmt = $ourDb->prepare($sqls[$x]);
            $stmt->bind_param('i', $house);
            $stmt->execute();
            $item = $level = $lastSeen = null;
            $stmt->bind_result($item, $level, $lastSeen);
            while ($stmt->fetch()) {
                $k = "$item:$level";
                if (!isset($dated[$k])) {
                    if ($x == 0) {
                        $summaryRows++;
                        if ($lastSeen == $snapshot) {
                            $summaryLate++;
                        }
                    } else {
                        $addedRows++;
                    }
                    $dated[$k] = $lastSeen;
                } elseif ($dated[$k] == $snapshot) {
                    $updatedRows++;
                    $dated[$k] = $lastSeen;
                } elseif ($dated[$k] != $lastSeen) {
                    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " rares: $k was ".date('Y-m-d H:i:s', $dated[$k]).", then ".date('Y-m-d H:i:s', $lastSeen).", snapshot $snapshotString");
                }
            }
            $stmt->close();
        }
        unset($sqls);

        $ourDb->commit(); // end read txn

        $item = $level = $lastSeen = null;
        $stmt = $ourDb->prepare('update ttblRareStage set lastseen = from_unixtime(?) where item = ? and level = ?');
        $stmt->bind_param('iii', $lastSeen, $item, $level);
        foreach ($dated as $k => $lastSeenVal) {
            list($item, $level) = explode(':', $k);
            $lastSeen = $lastSeenVal; // in case of byref weirdness
            $ok &= $stmt->execute();
            if ($ok) {
                $queryCount++;
            } else {
                break;
            }
        }
        $stmt->close();
        unset($dated);

        $stmt = $ourDb->prepare('delete from ttblRareStage where lastseen = ?');
        $stmt->bind_param('s', $snapshotString);
        $ok &= $stmt->execute();
        $stmt->close();

        $removed = $rowsWithoutDates = 0;
        $stmt = $ourDb->prepare('select count(*), sum(if(lastseen is null, 1, 0)) from ttblRareStage');
        $stmt->execute();
        $stmt->bind_result($removed, $rowsWithoutDates);
        $stmt->fetch();
        $stmt->close();

        $totalRows = count($newAuctionItems);
        $removed = $totalRows - $removed;

        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " rares: $summaryRows ($summaryLate late, $removed removed) tblItemSummary, added $addedRows & updated $updatedRows tblAuctionRare, $rowsWithoutDates without dates, $totalRows total");
        if (!$ok) {
            DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " failed while populating ttblRareStage");
        }
    }
    if ($ok) {
        $sql = <<<'EOF'
replace into tblUserRareReport (user, house, item, level, prevseen, price, snapshot) (
    SELECT ur.user, ur.house, rs.item, rs.level, rs.lastseen, rs.price, ?
    FROM ttblRareStage rs
    join tblUserRare ur
    join tblDBCItem i on i.id = rs.item and i.class = ur.itemclass and i.quality >= ur.minquality and i.level between ifnull(ur.minlevel, i.level) and ifnull(ur.maxlevel, i.level)
    left join tblDBCItemVendorCost ivc on ivc.item = rs.item
    where (ur.flags & 2 > 0 or ivc.item is null)
    and (ur.flags & 1 > 0 or (select count(*) from tblDBCSpell sc where sc.crafteditem = rs.item) = 0)
    and (datediff(?, rs.lastseen) >= ur.days or rs.lastseen is null)
    and ur.house = ?
    group by rs.item, rs.level
)
EOF;
        $stmt = $ourDb->prepare($sql);
        $stmt->bind_param('ssi', $snapshotString, $snapshotString, $house);
        $stmt->execute();
        $stmt->close();

        $stmt = $ourDb->prepare('select distinct user from tblUserRareReport where house = ? and snapshot = ?');
        $stmt->bind_param('is', $house, $snapshotString);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $updateObserved[$row['user']] = true;
        }
        $result->close();
        $stmt->close();
    }
    DBQueryWithError($ourDb, 'drop temporary table if exists ttblRareStage');

    $observedUsers = array_chunk(array_keys($updateObserved), 200);
    $sql = 'update tblUser set watchesobserved = \'%1$s\' where id in (%2$s) and ifnull(watchesobserved, \'2000-01-01\') < \'%1$s\'';
    foreach ($observedUsers as $users) {
        DBQueryWithError($ourDb, sprintf($sql, $snapshotString, implode(',', $users)));
        $queryCount++;
    }

    $ourDb->close();

    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " finished with $queryCount update queries in " . round(microtime(true) - $startTimer, 2) . " sec");
}

function WatchSatisfied(&$itemAuctions, $direction, $quantity, $price) {
    if (is_null($price)) {
        // qty notification
        if (   (($direction == 'Over') && ($itemAuctions[ARRAY_INDEX_QUANTITY] > $quantity))
            || (($direction != 'Over') && ($itemAuctions[ARRAY_INDEX_QUANTITY] < $quantity))) {
            return $itemAuctions[ARRAY_INDEX_QUANTITY];
        }
    } else {
        $curPrice = GetMarketPrice($itemAuctions, $quantity);
        if (is_null($curPrice)) {
            // fewer than $quantity are available
            return false;
        }
        if (   (($direction == 'Over') && ($curPrice > $price))
            || (($direction != 'Over') && ($curPrice < $price))) {
            return $curPrice;
        }
    }
    return false;
}

function AuctionListInsert(&$list, $qty, $buyout) {
    $pricePer = $buyout;
    if ($qty != 1) {
        $pricePer =0| $buyout/$qty;
        $auction = [ARRAY_INDEX_QUANTITY => $qty, ARRAY_INDEX_BUYOUT => $buyout];
    } else {
        $auction = $pricePer;
    }

    $iMax = count($list) - 1;
    if ($iMax == -1) {
        $list[] = $auction;
        return;
    }
    $iMin = 0;
    $iMid = 0;

    while ($iMin <= $iMax) {
        $iMid = (0|(($iMax - $iMin) / 2)) + $iMin;
        $midPrice = $list[$iMid];
        $midQty = 1;
        if (is_array($midPrice)) {
            $midQty = $midPrice[ARRAY_INDEX_QUANTITY];
            $midPrice = 0| $midPrice[ARRAY_INDEX_BUYOUT]/$midPrice[ARRAY_INDEX_QUANTITY];
        }
        if ($midPrice == $pricePer) {
            if ($midQty == $qty) {
                break;
            }
            if ($midQty < $qty) {
                $iMin = ++$iMid;
            } else {
                $iMax = $iMid - 1;
            }
        }
        if ($midPrice < $pricePer) {
            $iMin = ++$iMid;
        } else {
            $iMax = $iMid - 1;
        }
    }

    array_splice($list, $iMid, 0, [$auction]);
}

function GetMarketPrice(&$info, $inBuyCount = 0)
{
    if (!$inBuyCount) {
        if (isset($info[ARRAY_INDEX_MARKETPRICE])) {
            return $info[ARRAY_INDEX_MARKETPRICE];
        }
        if ($info[ARRAY_INDEX_QUANTITY] == 0) {
            return null;
        }
    } elseif ($info[ARRAY_INDEX_QUANTITY] < $inBuyCount) {
        return null;
    }

    $gq = 0;
    $gp = 0;
    $x = 0;
    $buyCount = $inBuyCount ?: ceil($info[ARRAY_INDEX_QUANTITY] * 0.15);
    while ($gq < $buyCount) {
        $auc = $info[ARRAY_INDEX_AUCTIONS][$x++];
        if (is_array($auc)) {
            $gq += $auc[ARRAY_INDEX_QUANTITY];
            $gp += $auc[ARRAY_INDEX_BUYOUT];
        } else {
            $gq++;
            $gp += $auc;
        }
    }
    if (!$inBuyCount) {
        return $info[ARRAY_INDEX_MARKETPRICE] = ceil($gp / $gq);
    }
    return $gp; // returns full price to buy $gq
}

function AuctionListComparitor($a, $b) {
    $aPrice = is_array($a) ? floor($a[ARRAY_INDEX_BUYOUT] / $a[ARRAY_INDEX_QUANTITY] * 100) : $a;
    $bPrice = is_array($b) ? floor($b[ARRAY_INDEX_BUYOUT] / $b[ARRAY_INDEX_QUANTITY] * 100) : $b;

    if ($aPrice == $bPrice) {
        return 0;
    }
    return $aPrice < $bPrice ? -1 : 1;
}

function DBQueryWithError(&$db, $sql)
{
    $queryOk = $db->query($sql);
    if (!$queryOk) {
        DebugMessage("SQL error: " . $db->errno . ' ' . $db->error . " - " . substr(preg_replace('/[\r\n]/', ' ', $sql), 0, 500), E_USER_WARNING);
    }

    return $queryOk;
}
