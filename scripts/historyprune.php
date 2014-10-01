<?php

chdir(__DIR__);

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

    $sqlPattern = 'delete from tblItemHistory where house = %d and item between %d and %d and snapshot < \'%s\'';

    for ($hx = 0; $hx < count($houses); $hx++)
    {
        heartbeat();
        if ($caughtKill)
            return;

        $house = $houses[$hx];
        $ssDate = '';
        $cutoffDate = Date('Y-m-d H:i:s', strtotime(''.HISTORY_DAYS.' days ago'));

        $stmt = $db->prepare('select min(`updated`) from (select `updated` from tblSnapshot where house = ? order by updated desc limit ?) aa');
        $maxSnapshots = 24 * HISTORY_DAYS;
        $stmt->bind_param('ii', $house, $maxSnapshots);
        $stmt->execute();
        $stmt->bind_result($ssDate);
        $gotDate = $stmt->fetch() === true;
        $stmt->close();

        if (!$gotDate || is_null($ssDate))
        {
            DebugMessage("$house has no snapshots, skipping!");
            continue;
        }

        if (strtotime($ssDate) < strtotime($cutoffDate))
            $cutoffDate = $ssDate;

        foreach (array($house, -1 * $house) as $factionHouse)
        {
            heartbeat();
            if ($caughtKill)
                return;

            $stmt = $db->prepare('select distinct item from tblItemSummary where house = ? and lastseen > timestampadd(day, -1, ?)');
            $stmt->bind_param('is',$factionHouse,$cutoffDate);
            $stmt->execute();
            $result = $stmt->get_result();
            $items = array_values(DBMapArray($result));
            $stmt->close();

            $rowCount = 0;

            if (count($items) > 0)
            {
                $db->real_query(sprintf(str_replace('item between %d and %d', 'item < %d', $sqlPattern), $factionHouse, $items[0], $cutoffDate));
                $rowCount += $db->affected_rows;
                $db->real_query(sprintf(str_replace('item between %d and %d', 'item > %d', $sqlPattern), $factionHouse, $items[count($items) - 1], $cutoffDate));
                $rowCount += $db->affected_rows;

                $itemChunks = array_chunk($items, 100);

                for($x = 0; $x < count($itemChunks); $x++)
                {
                    heartbeat();
                    $minItem = array_shift($itemChunks[$x]);
                    $maxItem = count($itemChunks[$x]) > 0 ? array_pop($itemChunks[$x]) : $minItem;
                    $db->real_query(sprintf($sqlPattern, $factionHouse, $minItem, $maxItem, $cutoffDate));
                    $rowCount += $db->affected_rows;
                }

                $db->real_query(sprintf(str_replace(' and item between %d and %d', '', $sqlPattern), $factionHouse, $cutoffDate));
                $rowCount += $db->affected_rows;
            }

            DebugMessage("$rowCount item history rows deleted from house $factionHouse since $cutoffDate");
        }
    }

    if ($caughtKill)
        return;

    $sqlPattern = 'delete from tblPetHistory where house = %d and species between %d and %d and snapshot < \'%s\'';

    for ($hx = 0; $hx < count($houses); $hx++)
    {
        heartbeat();
        if ($caughtKill)
            return;

        $house = $houses[$hx];
        $ssDate = '';
        $cutoffDate = Date('Y-m-d H:i:s', strtotime(''.HISTORY_DAYS.' days ago'));

        $stmt = $db->prepare('select min(`updated`) from (select `updated` from tblSnapshot where house = ? order by updated desc limit ?) aa');
        $maxSnapshots = 24 * HISTORY_DAYS;
        $stmt->bind_param('ii', $house, $maxSnapshots);
        $stmt->execute();
        $stmt->bind_result($ssDate);
        $gotDate = $stmt->fetch() === true;
        $stmt->close();

        if (!$gotDate || is_null($ssDate))
        {
            DebugMessage("$house has no snapshots, skipping!");
            continue;
        }

        if (strtotime($ssDate) < strtotime($cutoffDate))
            $cutoffDate = $ssDate;

        foreach (array($house, -1 * $house) as $factionHouse)
        {
            heartbeat();
            if ($caughtKill)
                return;

            $stmt = $db->prepare('select distinct species from tblPetSummary where house = ? and lastseen > timestampadd(day, -1, ?)');
            $stmt->bind_param('is',$factionHouse,$cutoffDate);
            $stmt->execute();
            $result = $stmt->get_result();
            $items = array_values(DBMapArray($result));
            $stmt->close();

            $rowCount = 0;

            if (count($items) > 0)
            {
                $db->real_query(sprintf(str_replace('species between %d and %d', 'species < %d', $sqlPattern), $factionHouse, $items[0], $cutoffDate));
                $rowCount += $db->affected_rows;
                $db->real_query(sprintf(str_replace('species between %d and %d', 'species > %d', $sqlPattern), $factionHouse, $items[count($items) - 1], $cutoffDate));
                $rowCount += $db->affected_rows;

                $itemChunks = array_chunk($items, 100);

                for($x = 0; $x < count($itemChunks); $x++)
                {
                    heartbeat();
                    $minItem = array_shift($itemChunks[$x]);
                    $maxItem = count($itemChunks[$x]) > 0 ? array_pop($itemChunks[$x]) : $minItem;
                    $db->real_query(sprintf($sqlPattern, $factionHouse, $minItem, $maxItem, $cutoffDate));
                    $rowCount += $db->affected_rows;
                }

                $db->real_query(sprintf(str_replace(' and species between %d and %d', '', $sqlPattern), $factionHouse, $cutoffDate));
                $rowCount += $db->affected_rows;
            }

            DebugMessage("$rowCount pet history rows deleted from house $factionHouse since $cutoffDate");
        }
    }

    if ($caughtKill)
        return;

    $rowCount = 0;
    DebugMessage('Clearing out old seller history');
    $sql = 'delete from tblSellerHistory where snapshot < timestampadd(day, -'.HISTORY_DAYS.', now()) limit 5000';
    while (!$caughtKill)
    {
        heartbeat();
        $ok = $db->real_query($sql);
        if (!$ok || $db->affected_rows == 0)
            break;
        $rowCount += $db->affected_rows;
    }
    DebugMessage("$rowCount seller history rows deleted in total");

    for ($hx = 0; $hx < count($houses); $hx++)
    {
        heartbeat();
        if ($caughtKill)
            return;

        $house = $houses[$hx];
        $cutoffDate = Date('Y-m-d H:i:s', strtotime(''.(HISTORY_DAYS + 3).' days ago'));

        $sql = sprintf('delete from tblSnapshot where house = %d and updated < \'%s\'', $house, $cutoffDate);
        $db->query($sql);

        DebugMessage(sprintf('%d snapshot rows removed from house %d since %s', $db->affected_rows, $house, $cutoffDate));
    }
}