<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');

RunMeNTimes(1);
CatchKill();

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

AddHourlyData();
DebugMessage('Done! Started ' . TimeDiff($startTime));

function AddHourlyData()
{
    global $db, $caughtKill;

    if ($caughtKill) {
        return;
    }

    DebugMessage("Starting, getting houses");

    $house = null;
    $houses = [];
    $stmt = $db->prepare('SELECT DISTINCT house FROM tblRealm WHERE house is not null and region in (\'US\', \'EU\')');
    $stmt->execute();
    $stmt->bind_result($house);
    while ($stmt->fetch()) {
        $houses[] = $house;
    }
    $stmt->close();

    $sqlPattern = <<<'EOF'
insert into tblItemHistoryHourly (house, item, bonusset, `when`, `silver%1$s`, `quantity%1$s`)
(select house, item, bonusset, date(`snapshot`), round(price/100), quantity 
from tblItemHistory
where house = %2$d AND hour(`snapshot`) = %3$d
order by 1, 2, 3, 4
)
on duplicate key update `silver%1$s` = values(`silver%1$s`), `quantity%1$s` = values(`quantity%1$s`)
EOF;

    foreach ($houses as $house => $houseRow) {
        heartbeat();
        if ($caughtKill) {
            return;
        }

        if (!MCHouseLock($house)) {
            continue;
        }

        for ($hour = 0; $hour < 24; $hour++) {
            $hourPadded = str_pad($hour, 2, '0', STR_PAD_LEFT);

            $sql = sprintf($sqlPattern, $hourPadded, $house, $hour);
            $queryOk = $db->real_query($sql);
            if (!$queryOk) {
                DebugMessage("SQL error: " . $db->errno . ' ' . $db->error . " - " . substr(preg_replace('/[\r\n]/', ' ', $sql), 0, 500), E_USER_WARNING);
            } else {
                $rowCount = $db->affected_rows;
                DebugMessage("$rowCount item hourly rows updated for house $house for hour $hour");
            }
        }

        MCHouseUnlock($house);
    }
}

