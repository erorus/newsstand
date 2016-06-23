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

    $sqlPatternDaily = 'insert ignore into tblItemHistoryDaily2 (select item, house, `when`, pricemin, priceavg, pricemax, pricestart, priceend, quantitymin, quantityavg, quantitymax from tblItemHistoryDaily where house = %d and item between %d and %d and `when` > \'%s\')';
    $cutOffDateDaily = date('Y-m-d H:i:s', strtotime('' . HISTORY_DAYS_DEEP . ' days ago'));

    for ($hx = 0; $hx < count($houses); $hx++) {
        heartbeat();
        if ($caughtKill) {
            return;
        }

        $house = $houses[$hx];

        $db->real_query(sprintf(str_ireplace('item between %d and %d', 'item < %d', $sqlPatternDaily), $house, $minItem, $cutOffDateDaily));
        DebugMessage(sprintf("%d rows inserted for items < %d in house %d", $db->affected_rows, $minItem, $house));
    }

    for ($x = 0; $x < count($itemChunks); $x++) {
        heartbeat();
        if ($caughtKill) {
            return;
        }

        for ($hx = 0; $hx < count($houses); $hx++) {
            heartbeat();
            if ($caughtKill) {
                return;
            }

            $house = $houses[$hx];

            $db->real_query(sprintf($sqlPatternDaily, $house, $itemChunks[$x]['first'], $itemChunks[$x]['last'], $cutOffDateDaily));
            DebugMessage(sprintf("%d rows inserted for items between %d and %d in house %d", $db->affected_rows, $itemChunks[$x]['first'], $itemChunks[$x]['last'], $house));
        }
    }

    for ($hx = 0; $hx < count($houses); $hx++) {
        heartbeat();
        if ($caughtKill) {
            return;
        }

        $house = $houses[$hx];

        $db->real_query(sprintf(str_ireplace('item between %d and %d', 'item > %d', $sqlPatternDaily), $house, $maxItem, $cutOffDateDaily));
        DebugMessage(sprintf("%d rows inserted for items > %d in house %d", $db->affected_rows, $maxItem, $house));
    }

}
