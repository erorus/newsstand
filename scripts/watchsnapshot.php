<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');

RunMeNTimes(2);
CatchKill();

define('SNAPSHOT_PATH', '/var/newsstand/snapshots/watch/');
define('MAX_BONUSES', 6); // is a count, 1 through N

ini_set('memory_limit', '384M');

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

$stmt = $db->prepare('SELECT id FROM tblDBCItem WHERE `class` in (2,4) AND `auctionable` = 1');
$stmt->execute();
$result = $stmt->get_result();
$auctionExtraItemsCache = DBMapArray($result);
$stmt->close();

$stmt = $db->prepare('SELECT id FROM tblDBCItemBonus WHERE `flags` & 1');
$stmt->execute();
$result = $stmt->get_result();
$bonusSetMemberCache = array_keys(DBMapArray($result));
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
    //unlink(SNAPSHOT_PATH . $fileName); // TODO: remove comment after testing

    if (json_last_error() != JSON_ERROR_NONE) {
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " $snapshot data file corrupted! " . json_last_error_msg(), E_USER_WARNING);
        //MCHouseUnlock($house);
        return 0;
    }

    ParseAuctionData($house, $snapshot, $json);
    exit; // TODO: remove after testing
    //MCHouseUnlock($house);
    return 0;
}

function ParseAuctionData($house, $snapshot, &$json)
{
    global $maxPacketSize;
    global $houseRegionCache;
    global $auctionExtraItemsCache;
    global $TIMELEFT_ENUM;

    $snapshotString = Date('Y-m-d H:i:s', $snapshot);
    $startTimer = microtime(true);

    $ourDb = DBConnect();

    $region = $houseRegionCache[$house]['region'];

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

    // note: $lastMax is the max auction ID the last time we stored snapshot history
    $stmt = $ourDb->prepare('SELECT ifnull(maxid,0), unix_timestamp(updated) FROM tblSnapshot s WHERE house = ? AND updated = (SELECT max(s2.updated) FROM tblSnapshot s2 WHERE s2.house = s.house AND s2.updated < ? AND s2.flags & 1 = 0)');
    $stmt->bind_param('is', $house, $snapshotString);
    $stmt->execute();
    $stmt->bind_result($lastMax, $lastMaxUpdated);
    if ($stmt->fetch() !== true) {
        $lastMax = 0;
        $lastMaxUpdated = 0;
    }
    $stmt->close();

    $totalAuctions = 0;
    $itemInfo = array();
    $petInfo = array();
    $expiredItemInfo = array();

    if ($jsonAuctions) {
        $auctionCount = count($jsonAuctions);
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " parsing $auctionCount auctions");
        $ourDb->begin_transaction();

        for ($x = 0; $x < $auctionCount; $x++) {
            $auction =& $jsonAuctions[$x];

            if (isset($auction['petBreedId'])) {
                $auction['petBreedId'] = (($auction['petBreedId'] - 3) % 10) + 3; // squash gender
            }
            $auction['timeLeft'] = isset($TIMELEFT_ENUM[$auction['timeLeft']]) ? $TIMELEFT_ENUM[$auction['timeLeft']] : 0;

            $totalAuctions++;
            $itemInfoKey = false;
            if ($auction['buyout'] != 0) {
                if (isset($auction['petSpeciesId'])) {
                    if (!isset($petInfo[$auction['petSpeciesId']][$auction['petBreedId']])) {
                        $petInfo[$auction['petSpeciesId']][$auction['petBreedId']] = [0, []];
                    }

                    AuctionListInsert($petInfo[$auction['petSpeciesId']][$auction['petBreedId']][1], $auction['quantity'], $auction['buyout']);
                    $petInfo[$auction['petSpeciesId']][$auction['petBreedId']][0] += $auction['quantity'];
                } else {
                    $bonusSet = 0;
                    if (isset($auctionExtraItemsCache[$auction['item']]) && isset($auction['bonusLists'])) {
                        $bonusSet = GetBonusSet($auction['bonusLists']);
                    }
                    $itemInfoKey = $auction['item'] . ":$bonusSet";
                    if (!isset($itemInfo[$itemInfoKey])) {
                        $itemInfo[$itemInfoKey] = [0, []];
                    }

                    AuctionListInsert($itemInfo[$itemInfoKey][1], $auction['quantity'], $auction['buyout']);
                    $itemInfo[$itemInfoKey][0] += $auction['quantity'];
                }
            }
        }

        $ourDb->commit();
    }

    $ourDb->close();

    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " finished with $totalAuctions auctions in " . round(microtime(true) - $startTimer, 2) . " sec");
}

function AuctionListInsert(&$list, $qty, $buyout) {
    $pricePer = $buyout;
    if ($qty != 1) {
        $pricePer =0| $buyout/$qty;
        $auction = [$qty, $buyout];
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
            $midQty = $midPrice[0];
            $midPrice = 0| $midPrice[1]/$midPrice[0];
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

function GetBonusSet($bonusList)
{
    global $bonusSetMemberCache, $db;
    static $bonusSetCache = [];

    $bonuses = [];
    for ($y = 0; $y < count($bonusList); $y++) {
        if (isset($bonusList[$y]['bonusListId'])) {
            $bonuses[] = intval($bonusList[$y]['bonusListId'],10);
        }
    }
    $bonuses = array_intersect(array_unique($bonuses, SORT_NUMERIC), $bonusSetMemberCache);
    sort($bonuses, SORT_NUMERIC);

    if (count($bonuses) == 0) {
        return 0;
    }

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