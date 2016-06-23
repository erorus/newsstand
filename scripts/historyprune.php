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

    //// get item chunks

    DebugMessage("Getting item chunks");

    $sql = <<<'EOF'
select * 
from (
    select 
        if(row % 500 = 1, @firstitem := item, @firstitem) first,
        if(row % 500 = 0 or row = @rowcounter, item, null) last
    from (
        select (@rowcounter := @rowcounter + 1) row, items.item
        from (SELECT distinct item FROM tblItemGlobal ORDER BY 1) items, (select @rowcounter := 0) rowcounter
        ) rows
    where (row % 500 in (0, 1) or row = @rowcounter)
    ) withnulls
where last is not null
EOF;
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $itemChunks = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
    $stmt->close();

    if (count($itemChunks) == 0) {
        DebugMessage("No items in tblItemGlobal yet?");
        return;
    }

    $minItem = $itemChunks[0]['first'];
    $maxItem = $itemChunks[count($itemChunks)-1]['last'];

    //// get species chunks

    DebugMessage("Getting species chunks");

    $sql = <<<'EOF'
select * 
from (
    select 
        if(row % 50 = 1, @firstitem := item, @firstitem) first,
        if(row % 50 = 0 or row = @rowcounter, item, null) last
    from (
        select (@rowcounter := @rowcounter + 1) row, items.item
        from (SELECT distinct id as item FROM tblDBCPet ORDER BY 1) items, (select @rowcounter := 0) rowcounter
        ) rows
    where (row % 50 in (0, 1) or row = @rowcounter)
    ) withnulls
where last is not null
EOF;
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $speciesChunks = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
    $stmt->close();

    if (count($speciesChunks) == 0) {
        DebugMessage("No species in tblPetSummary yet?");
        return;
    }

    $minSpecies = $speciesChunks[0]['first'];
    $maxSpecies = $speciesChunks[count($speciesChunks)-1]['last'];

    // clean tblItemHistory

    $sqlPattern = 'delete from tblItemHistory where house = %d and item between %d and %d and snapshot < \'%s\'';
    $sqlPatternDaily = 'delete from tblItemHistoryDaily where house = %d and item between %d and %d and `when` < \'%s\'';

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
        $cutoffDate = date('Y-m-d H:i:s', strtotime('' . HISTORY_DAYS . ' days ago'));
        $cutOffDateDaily = date('Y-m-d H:i:s', strtotime('' . HISTORY_DAYS_DEEP . ' days ago'));

        $stmt = $db->prepare('SELECT min(`updated`) FROM (SELECT `updated` FROM tblSnapshot WHERE house = ? AND `flags` & 1 = 0 ORDER BY updated DESC LIMIT ?) aa');
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

        if (strtotime($ssDate) < strtotime($cutoffDate)) {
            $cutoffDate = $ssDate;
        }

        heartbeat();
        if ($caughtKill) {
            MCHouseUnlock($house);
            return;
        }

        $rowCount = 0;
        $rowCountDaily = 0;

        $rowCount += DeleteLimitLoop($db, sprintf(str_ireplace('item between %d and %d', 'item < %d', $sqlPattern), $house, $minItem, $cutoffDate));
        $rowCount += DeleteLimitLoop($db, sprintf(str_ireplace('item between %d and %d', 'item > %d', $sqlPattern), $house, $maxItem, $cutoffDate));

        $rowCountDaily += DeleteLimitLoop($db, sprintf(str_ireplace('item between %d and %d', 'item < %d', $sqlPatternDaily), $house, $minItem, $cutOffDateDaily));
        $rowCountDaily += DeleteLimitLoop($db, sprintf(str_ireplace('item between %d and %d', 'item > %d', $sqlPatternDaily), $house, $maxItem, $cutOffDateDaily));

        for ($x = 0; $x < count($itemChunks); $x++) {
            heartbeat();
            $rowCount += DeleteLimitLoop($db, sprintf($sqlPattern, $house, $itemChunks[$x]['first'], $itemChunks[$x]['last'], $cutoffDate));
            $rowCountDaily += DeleteLimitLoop($db, sprintf($sqlPatternDaily, $house, $itemChunks[$x]['first'], $itemChunks[$x]['last'], $cutOffDateDaily));
        }

        $rowCount += DeleteLimitLoop($db, sprintf(str_ireplace(' and item between %d and %d', '', $sqlPattern), $house, $cutoffDate));
        $rowCountDaily += DeleteLimitLoop($db, sprintf(str_ireplace(' and item between %d and %d', '', $sqlPatternDaily), $house, $cutOffDateDaily));

        DebugMessage("$rowCount item history rows deleted from house $house since $cutoffDate");
        DebugMessage("$rowCountDaily item history daily rows deleted from house $house since $cutOffDateDaily");
        MCHouseUnlock($house);
    }

    if ($caughtKill) {
        return;
    }

    // clean tblPetHistory

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
        $cutoffDate = date('Y-m-d H:i:s', strtotime('' . HISTORY_DAYS . ' days ago'));

        $stmt = $db->prepare('SELECT min(`updated`) FROM (SELECT `updated` FROM tblSnapshot WHERE house = ? AND `flags` & 1 = 0 ORDER BY updated DESC LIMIT ?) aa');
        $maxSnapshots = 24 * HISTORY_DAYS;
        $stmt->bind_param('ii', $house, $maxSnapshots);
        $stmt->execute();
        $stmt->bind_result($ssDate);
        $gotDate = $stmt->fetch() === true;
        $stmt->close();

        if (!$gotDate || is_null($ssDate)) {
            DebugMessage("$house has no snapshots, skipping pet history!");
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

        $rowCount = 0;

        $rowCount += DeleteLimitLoop($db, sprintf(str_ireplace('species between %d and %d', 'species < %d', $sqlPattern), $house, $minSpecies, $cutoffDate));
        $rowCount += DeleteLimitLoop($db, sprintf(str_ireplace('species between %d and %d', 'species > %d', $sqlPattern), $house, $maxSpecies, $cutoffDate));

        for ($x = 0; $x < count($speciesChunks); $x++) {
            heartbeat();
            $rowCount += DeleteLimitLoop($db, sprintf($sqlPattern, $house, $speciesChunks[$x]['first'], $speciesChunks[$x]['last'], $cutoffDate));
        }

        $rowCount += DeleteLimitLoop($db, sprintf(str_ireplace(' and species between %d and %d', '', $sqlPattern), $house, $cutoffDate));

        DebugMessage("$rowCount pet history rows deleted from house $house since $cutoffDate");
        MCHouseUnlock($house);
    }

    if ($caughtKill) {
        return;
    }

    // clean tblItemExpired

    for ($hx = 0; $hx < count($houses); $hx++) {
        heartbeat();
        if ($caughtKill) {
            return;
        }

        $house = $houses[$hx];
        if (!MCHouseLock($house)) {
            continue;
        }
        $cutoffDate = date('Y-m-d H:i:s', strtotime('' . ((HISTORY_DAYS * 2) + 3) . ' days ago'));

        $sql = sprintf('delete from tblItemExpired where house = %d and `when` < \'%s\'', $house, $cutoffDate);
        $rowCount = DeleteLimitLoop($db, $sql);

        DebugMessage(sprintf('%d expired item rows removed from house %d since %s', $rowCount, $house, $cutoffDate));
        MCHouseUnlock($house);
    }

    if ($caughtKill) {
        return;
    }

    $rowCount = 0;
    DebugMessage('Clearing out old seller history');
    $sql = 'delete from tblSellerHistory where snapshot < timestampadd(day, -' . HISTORY_DAYS . ', now())';
    $rowCount += DeleteLimitLoop($db, $sql);
    DebugMessage("$rowCount seller history rows deleted in total");

    $rowCount = 0;
    DebugMessage('Clearing out old seller item history');
    $sql = 'delete from tblSellerItemHistory where snapshot < timestampadd(day, -' . HISTORY_DAYS . ', now())';
    $rowCount += DeleteLimitLoop($db, $sql);
    DebugMessage("$rowCount seller item history rows deleted in total");

    $rowCount = 0;
    $old = date('Y-m-d H:i:s', time() - SUBSCRIPTION_SESSION_LENGTH - 172800); // 2 days older than oldest cookie age
    DebugMessage('Clearing out user sessions older than ' . $old);
    $sql = "delete from tblUserSession where lastseen < '$old'";
    $rowCount += DeleteLimitLoop($db, $sql, 500);
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
        $cutoffDate = date('Y-m-d H:i:s', strtotime('' . (HISTORY_DAYS + 3) . ' days ago'));

        $sql = sprintf('DELETE FROM tblSnapshot WHERE house = %d AND updated < \'%s\'', $house, $cutoffDate);
        $db->query($sql);

        DebugMessage(sprintf('%d snapshot rows removed from house %d since %s', $db->affected_rows, $house, $cutoffDate));
        MCHouseUnlock($house);
    }
}

function DeleteLimitLoop($db, $query, $limit = 5000) {
    $query .= " LIMIT $limit";
    $rowCount = 0;
    do {
        heartbeat();
        $ok = $db->real_query($query);
        $rowCount += $affectedRows = $db->affected_rows;
    } while ($ok && ($affectedRows >= $limit));

    return $rowCount;
}