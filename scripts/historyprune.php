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

    $rowCount = 0;
    DebugMessage('Clearing out old item history');
    $sql = 'delete from tblItemHistory where snapshot < timestampadd(day, -15, now()) limit 5000';
    while (!$caughtKill)
    {
        heartbeat();
        $ok = $db->real_query($sql);
        if (!$ok || $db->affected_rows == 0)
            break;
        $rowCount += $db->affected_rows;
    }
    DebugMessage("$rowCount item history rows deleted in total");

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