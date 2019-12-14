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
    global $db;

    if (CatchKill()) {
        return;
    }

    DebugMessage("Starting, getting houses");

    $house = null;
    $houses = [];
    $stmt = $db->prepare('SELECT DISTINCT house FROM tblRealm WHERE house is not null');
    $stmt->execute();
    $stmt->bind_result($house);
    while ($stmt->fetch()) {
        $houses[] = $house;
    }
    $stmt->close();

    // clean tblItemHistoryHourly, tblPetHistoryHourly, tblItemHistoryDaily

    $sqlPatternHourly = 'delete from tbl%sHistoryHourly where house = %d and `when` < \'%s\'';
    $sqlPatternDaily = 'delete from tblItemHistoryDaily where house = %d and `when` < \'%s\'';

    for ($hx = 0; $hx < count($houses); $hx++) {
        heartbeat();
        if (CatchKill()) {
            return;
        }

        $house = $houses[$hx];
        if (!MCHouseLock($house)) {
            continue;
        }

        $ssDate = '';
        $cutoffDateHourly = date('Y-m-d', strtotime('' . HISTORY_DAYS . ' days ago'));
        $cutOffDateDaily = date('Y-m-d', strtotime('' . HISTORY_DAYS_DEEP . ' days ago'));

        $stmt = $db->prepare('SELECT date(min(`updated`)) FROM (SELECT `updated` FROM tblSnapshot WHERE house = ? AND `flags` & 1 = 0 ORDER BY updated DESC LIMIT ?) aa');
        $maxSnapshots = 24 * HISTORY_DAYS;
        $stmt->bind_param('ii', $house, $maxSnapshots);
        $stmt->execute();
        $stmt->bind_result($ssDate);
        $gotDate = $stmt->fetch() === true;
        $stmt->close();

        if (!$gotDate || is_null($ssDate)) {
            DebugMessage("$house has no snapshots, skipping item history!");
            MCHouseUnlock($house);
            continue;
        }

        if (strtotime($ssDate) < strtotime($cutoffDateHourly)) {
            $cutoffDateHourly = $ssDate;
        }

        if (!CatchKill()) {
            $rowCount = DeleteLimitLoop($db, sprintf($sqlPatternHourly, 'Item', $house, $cutoffDateHourly));
            DebugMessage("$rowCount item hourly history rows deleted from house $house since $cutoffDateHourly");
        }

        if (!CatchKill()) {
            $rowCount = DeleteLimitLoop($db, sprintf($sqlPatternHourly, 'Pet', $house, $cutoffDateHourly));
            DebugMessage("$rowCount pet hourly history rows deleted from house $house since $cutoffDateHourly");
        }

        if (!CatchKill()) {
            $rowCount = DeleteLimitLoop($db, sprintf($sqlPatternDaily, $house, $cutOffDateDaily));
            DebugMessage("$rowCount item history daily rows deleted from house $house since $cutOffDateDaily");
        }

        MCHouseUnlock($house);
    }

    if (CatchKill()) {
        return;
    }

    $rowCount = 0;
    $old = date('Y-m-d H:i:s', time() - SUBSCRIPTION_SESSION_LENGTH - 172800); // 2 days older than oldest cookie age
    DebugMessage('Clearing out user sessions older than ' . $old);
    $sql = "delete from tblUserSession where lastseen < '$old'";
    $rowCount += DeleteLimitLoop($db, $sql, 500);
    DebugMessage("$rowCount user session rows deleted in total");

    for ($hx = 0; $hx < count($houses); $hx++) {
        heartbeat();
        if (CatchKill()) {
            return;
        }

        $house = $houses[$hx];
        if (!MCHouseLock($house)) {
            continue;
        }
        $cutoffDate = date('Y-m-d H:i:s', strtotime('' . (HISTORY_DAYS + 3) . ' days ago'));

        $sql = sprintf('DELETE FROM tblSnapshot WHERE house = %d AND updated < \'%s\'', $house, $cutoffDate);
        $db->query($sql);

        DebugMessage(sprintf('%d snapshot rows removed from house %d since %s', $db->affected_rows, $house, $cutoffDate));
        MCHouseUnlock($house);
    }
}

function DeleteLimitLoop($db, $query, $limit = 5000) {
    $rowCount = 0;
    if (CatchKill()) {
        return $rowCount;
    }

    $query .= " LIMIT $limit";
    do {
        heartbeat();
        $ok = $db->real_query($query);
        $rowCount += $affectedRows = $db->affected_rows;
    } while (!CatchKill() && $ok && ($affectedRows >= $limit));

    return $rowCount;
}
