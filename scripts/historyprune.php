<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');
require_once('../incl/subscription.incl.php');

RunMeNTimes(1);
CatchKill();

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

if (APIMaintenance()) {
    DebugMessage('API Maintenance in progress, not pruning history!', E_USER_NOTICE);
    exit;
}

CleanOldData();
DebugMessage('Done! Started ' . TimeDiff($startTime));

function CleanOldData()
{
    global $db, $caughtKill;

    if ($caughtKill) {
        return;
    }

    $stmt = $db->prepare('SELECT DISTINCT house FROM tblRealm');
    $stmt->execute();
    $result = $stmt->get_result();
    $houses = array_values(DBMapArray($result));
    $stmt->close();

    $sqlPattern = 'delete from tblItemHistory where house = %d and item between %d and %d and snapshot < \'%s\'';

    for ($hx = 0; $hx < count($houses); $hx++) {
        heartbeat();
        if ($caughtKill) {
            return;
        }

        $house = $houses[$hx];
        if (!MCHouseLock($house)) {
            continue;
        }

        $ssDate = '';
        $cutoffDate = Date('Y-m-d H:i:s', strtotime('' . HISTORY_DAYS . ' days ago'));

        $stmt = $db->prepare('SELECT min(`updated`) FROM (SELECT `updated` FROM tblSnapshot WHERE house = ? ORDER BY updated DESC LIMIT ?) aa');
        $maxSnapshots = 24 * HISTORY_DAYS;
        $stmt->bind_param('ii', $house, $maxSnapshots);
        $stmt->execute();
        $stmt->bind_result($ssDate);
        $gotDate = $stmt->fetch() === true;
        $stmt->close();

        if (!$gotDate || is_null($ssDate)) {
            DebugMessage("$house has no snapshots, skipping!");
            MCHouseUnlock($house);
            continue;
        }

        if (strtotime($ssDate) < strtotime($cutoffDate)) {
            $cutoffDate = $ssDate;
        }

        heartbeat();
        if ($caughtKill) {
            MCHouseUnlock($house);
            return;
        }

        $stmt = $db->prepare('SELECT DISTINCT item FROM tblItemSummary WHERE house = ? AND lastseen > timestampadd(DAY, -1, ?)');
        $stmt->bind_param('is', $house, $cutoffDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = array_values(DBMapArray($result));
        $stmt->close();

        $rowCount = 0;

        if (count($items) > 0) {
            $db->real_query(sprintf(str_ireplace('item between %d and %d', 'item < %d', $sqlPattern), $house, $items[0], $cutoffDate));
            $rowCount += $db->affected_rows;
            $db->real_query(sprintf(str_ireplace('item between %d and %d', 'item > %d', $sqlPattern), $house, $items[count($items) - 1], $cutoffDate));
            $rowCount += $db->affected_rows;

            $itemChunks = array_chunk($items, 100);

            for ($x = 0; $x < count($itemChunks); $x++) {
                heartbeat();
                $minItem = array_shift($itemChunks[$x]);
                $maxItem = count($itemChunks[$x]) > 0 ? array_pop($itemChunks[$x]) : $minItem;
                $db->real_query(sprintf($sqlPattern, $house, $minItem, $maxItem, $cutoffDate));
                $rowCount += $db->affected_rows;
            }

            $db->real_query(sprintf(str_ireplace(' and item between %d and %d', '', $sqlPattern), $house, $cutoffDate));
            $rowCount += $db->affected_rows;
        }

        DebugMessage("$rowCount item history rows deleted from house $house since $cutoffDate");
        MCHouseUnlock($house);
    }

    if ($caughtKill) {
        return;
    }

    $sqlPattern = 'delete from tblPetHistory where house = %d and species between %d and %d and snapshot < \'%s\'';

    for ($hx = 0; $hx < count($houses); $hx++) {
        heartbeat();
        if ($caughtKill) {
            return;
        }

        $house = $houses[$hx];
        if (!MCHouseLock($house)) {
            continue;
        }
        $ssDate = '';
        $cutoffDate = Date('Y-m-d H:i:s', strtotime('' . HISTORY_DAYS . ' days ago'));

        $stmt = $db->prepare('SELECT min(`updated`) FROM (SELECT `updated` FROM tblSnapshot WHERE house = ? ORDER BY updated DESC LIMIT ?) aa');
        $maxSnapshots = 24 * HISTORY_DAYS;
        $stmt->bind_param('ii', $house, $maxSnapshots);
        $stmt->execute();
        $stmt->bind_result($ssDate);
        $gotDate = $stmt->fetch() === true;
        $stmt->close();

        if (!$gotDate || is_null($ssDate)) {
            DebugMessage("$house has no snapshots, skipping!");
            MCHouseUnlock($house);
            continue;
        }

        if (strtotime($ssDate) < strtotime($cutoffDate)) {
            $cutoffDate = $ssDate;
        }

        heartbeat();
        if ($caughtKill) {
            MCHouseUnlock($house);
            return;
        }

        $stmt = $db->prepare('select distinct species from tblPetSummary where house = ? and lastseen > timestampadd(day, -1, ?)');
        $stmt->bind_param('is', $house, $cutoffDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = array_values(DBMapArray($result));
        $stmt->close();

        $rowCount = 0;

        if (count($items) > 0) {
            $db->real_query(sprintf(str_ireplace('species between %d and %d', 'species < %d', $sqlPattern), $house, $items[0], $cutoffDate));
            $rowCount += $db->affected_rows;
            $db->real_query(sprintf(str_ireplace('species between %d and %d', 'species > %d', $sqlPattern), $house, $items[count($items) - 1], $cutoffDate));
            $rowCount += $db->affected_rows;

            $itemChunks = array_chunk($items, 100);

            for ($x = 0; $x < count($itemChunks); $x++) {
                heartbeat();
                $minItem = array_shift($itemChunks[$x]);
                $maxItem = count($itemChunks[$x]) > 0 ? array_pop($itemChunks[$x]) : $minItem;
                $db->real_query(sprintf($sqlPattern, $house, $minItem, $maxItem, $cutoffDate));
                $rowCount += $db->affected_rows;
            }

            $db->real_query(sprintf(str_ireplace(' and species between %d and %d', '', $sqlPattern), $house, $cutoffDate));
            $rowCount += $db->affected_rows;
        }

        DebugMessage("$rowCount pet history rows deleted from house $house since $cutoffDate");
        MCHouseUnlock($house);
    }

    if ($caughtKill) {
        return;
    }

    for ($hx = 0; $hx < count($houses); $hx++) {
        heartbeat();
        if ($caughtKill) {
            return;
        }

        $house = $houses[$hx];
        if (!MCHouseLock($house)) {
            continue;
        }
        $cutoffDate = Date('Y-m-d H:i:s', strtotime('' . ((HISTORY_DAYS * 2) + 3) . ' days ago'));

        $sql = sprintf('delete from tblItemExpired where house = %d and `when` < \'%s\'', $house, $cutoffDate);
        $db->query($sql);

        DebugMessage(sprintf('%d expired item rows removed from house %d since %s', $db->affected_rows, $house, $cutoffDate));
        MCHouseUnlock($house);
    }

    if ($caughtKill) {
        return;
    }

    $rowCount = 0;
    DebugMessage('Clearing out old seller history');
    $sql = 'delete from tblSellerHistory where snapshot < timestampadd(day, -' . HISTORY_DAYS . ', now()) limit 5000';
    while (!$caughtKill) {
        heartbeat();
        $ok = $db->real_query($sql);
        if (!$ok || $db->affected_rows == 0) {
            break;
        }
        $rowCount += $db->affected_rows;
    }
    DebugMessage("$rowCount seller history rows deleted in total");

    $rowCount = 0;
    DebugMessage('Clearing out old seller item history');
    $sql = 'delete from tblSellerItemHistory where snapshot < timestampadd(day, -' . HISTORY_DAYS . ', now()) limit 5000';
    while (!$caughtKill) {
        heartbeat();
        $ok = $db->real_query($sql);
        if (!$ok || $db->affected_rows == 0) {
            break;
        }
        $rowCount += $db->affected_rows;
    }
    DebugMessage("$rowCount seller item history rows deleted in total");

    $rowCount = 0;
    $old = Date('Y-m-d H:i:s', time() - SUBSCRIPTION_SESSION_LENGTH - 172800); // 2 days older than oldest cookie age
    DebugMessage('Clearing out user sessions older than ' . $old);
    $sql = 'delete from tblUserSession where lastseen < ' . $old . ' limit 500';
    while (!$caughtKill) {
        heartbeat();
        $ok = $db->real_query($sql);
        if (!$ok || $db->affected_rows == 0) {
            break;
        }
        $rowCount += $db->affected_rows;
    }
    DebugMessage("$rowCount user session rows deleted in total");

    for ($hx = 0; $hx < count($houses); $hx++) {
        heartbeat();
        if ($caughtKill) {
            return;
        }

        $house = $houses[$hx];
        if (!MCHouseLock($house)) {
            continue;
        }
        $cutoffDate = Date('Y-m-d H:i:s', strtotime('' . (HISTORY_DAYS + 3) . ' days ago'));

        $sql = sprintf('DELETE FROM tblSnapshot WHERE house = %d AND updated < \'%s\'', $house, $cutoffDate);
        $db->query($sql);

        DebugMessage(sprintf('%d snapshot rows removed from house %d since %s', $db->affected_rows, $house, $cutoffDate));
        MCHouseUnlock($house);
    }
}