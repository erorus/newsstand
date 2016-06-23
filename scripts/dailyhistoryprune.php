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

    // clean tblItemHistory

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
        $timeOnHouse = time();
        $quitEarly = false;

        $cutOffDateDaily = date('Y-m-d H:i:s', strtotime('' . HISTORY_DAYS_DEEP . ' days ago'));

        heartbeat();
        if ($caughtKill) {
            MCHouseUnlock($house);
            return;
        }

        $rowCountDaily = 0;

        if (!$caughtKill) $rowCountDaily += DeleteLimitLoop($db, sprintf(str_ireplace('item between %d and %d', 'item < %d', $sqlPatternDaily), $house, $minItem, $cutOffDateDaily));
        if (!$caughtKill) $rowCountDaily += DeleteLimitLoop($db, sprintf(str_ireplace('item between %d and %d', 'item > %d', $sqlPatternDaily), $house, $maxItem, $cutOffDateDaily));

        for ($x = 0; $x < count($itemChunks); $x++) {
            heartbeat();
            if ($caughtKill) {
                break;
            }
            $rowCountDaily += $rowCount = DeleteLimitLoop($db, sprintf($sqlPatternDaily, $house, $itemChunks[$x]['first'], $itemChunks[$x]['last'], $cutOffDateDaily));
            DebugMessage(sprintf("%d rows deleted for items between %d and %d (chunk %d of %d) on house %d",
                $rowCount,
                $itemChunks[$x]['first'], $itemChunks[$x]['last'],
                $x, count($itemChunks),
                $house));
            if ($timeOnHouse + 300 < time()) {
                $quitEarly = true;
            }
        }

        if (!$caughtKill && !$quitEarly) $rowCountDaily += DeleteLimitLoop($db, sprintf(str_ireplace(' and item between %d and %d', '', $sqlPatternDaily), $house, $cutOffDateDaily));

        if ($quitEarly) {
            DebugMessage(sprintf("Spent %d seconds on house %d, quitting early", time() - $timeOnHouse, $house));
        }
        DebugMessage("$rowCountDaily item history daily rows deleted from house $house since $cutOffDateDaily");
        MCHouseUnlock($house);
    }

}

function DeleteLimitLoop($db, $query, $limit = 5000) {
    global $caughtKill;

    $rowCount = 0;
    if ($caughtKill) {
        return $rowCount;
    }

    $query .= " LIMIT $limit";
    do {
        heartbeat();
        $ok = $db->real_query($query);
        $rowCount += $affectedRows = $db->affected_rows;
    } while (!$caughtKill && $ok && ($affectedRows >= $limit));

    return $rowCount;
}