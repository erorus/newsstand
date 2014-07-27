<?php

chdir(__DIR__);

define('HISTORY_DAYS', 14);

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');

RunMeNTimes(1);
CatchKill();

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

CleanOldData();
DebugMessage('Done!');

function CleanOldData()
{
    global $db, $caughtKill;

    if ($caughtKill)
        return;

    $stmt = $db->prepare('select distinct house from tblRealm');
    $stmt->execute();
    $result = $stmt->get_result();
    $houses = array_values(DBMapArray($result));
    $stmt->close();

    $sqlPattern = 'delete from tblItemHistory where house = %d and item between %d and %d and snapshot < timestampadd(day, %d, now())';

    for ($hx = 0; $hx < count($houses); $hx++)
    {
        heartbeat();
        if ($caughtKill)
            return;

        $house = $houses[$hx];

        foreach (array($house, -1 * $house) as $factionHouse)
        {
            heartbeat();
            if ($caughtKill)
                return;

            $stmt = $db->prepare('select distinct item from tblItemSummary where house = ? and lastseen > timestampadd(day, ?, now())');
            $days = -1 * (HISTORY_DAYS + 1);
            $stmt->bind_param('ii',$factionHouse,$days);
            $stmt->execute();
            $result = $stmt->get_result();
            $items = array_values(DBMapArray($result));
            $stmt->close();

            $rowCount = 0;

            if (count($items) > 0)
            {
                $db->real_query(sprintf(str_replace('item between %d and %d', 'item < %d', $sqlPattern), $factionHouse, $items[0], -1 * HISTORY_DAYS));
                $rowCount += $db->affected_rows;
                $db->real_query(sprintf(str_replace('item between %d and %d', 'item > %d', $sqlPattern), $factionHouse, $items[count($items) - 1], -1 * HISTORY_DAYS));
                $rowCount += $db->affected_rows;

                $itemChunks = array_chunk($items, 100);

                for($x = 0; $x < count($itemChunks); $x++)
                {
                    heartbeat();
                    $minItem = array_shift($itemChunks[$x]);
                    $maxItem = count($itemChunks[$x]) > 0 ? array_pop($itemChunks[$x]) : $minItem;
                    $db->real_query(sprintf($sqlPattern, $factionHouse, $minItem, $maxItem, -1 * HISTORY_DAYS));
                    $rowCount += $db->affected_rows;
                }

                $db->real_query(sprintf(str_replace(' and item between %d and %d', '', $sqlPattern), $factionHouse, -1 * HISTORY_DAYS));
                $rowCount += $db->affected_rows;
            }

            DebugMessage("$rowCount item history rows deleted from house $factionHouse");
        }
    }

    if ($caughtKill)
        return;

    $rowCount = 0;
    DebugMessage('Clearing out old seller history');
    $sql = 'delete from tblSellerHistory where snapshot < timestampadd(day, -15, now()) limit 5000';
    while (!$caughtKill)
    {
        heartbeat();
        $ok = $db->real_query($sql);
        if (!$ok || $db->affected_rows == 0)
            break;
        $rowCount += $db->affected_rows;
    }
    DebugMessage("$rowCount seller history rows deleted in total");
}