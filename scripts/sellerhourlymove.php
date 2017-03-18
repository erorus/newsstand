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
    global $db;

    if (CatchKill()) {
        return;
    }

    $sqlPattern = <<<'EOF'
insert into tblSellerHistoryHourly (seller, `when`, `new%1$s`, `total%1$s`)
(select seller, date(`snapshot`), `new`, `total` 
from tblSellerHistory
where hour(`snapshot`) = %2$d
order by `snapshot`
)
on duplicate key update `new%1$s` = values(`new%1$s`), `total%1$s` = values(`total%1$s`)
EOF;

    for ($hour = 0; $hour < 24; $hour++) {
        heartbeat();
        if (CatchKill()) {
            break;
        }

        $hourPadded = str_pad($hour, 2, '0', STR_PAD_LEFT);

        $sql = sprintf($sqlPattern, $hourPadded, $hour);
        $queryOk = $db->real_query($sql);
        if (!$queryOk) {
            DebugMessage("SQL error: " . $db->errno . ' ' . $db->error . " - " . substr(preg_replace('/[\r\n]/', ' ', $sql), 0, 500), E_USER_WARNING);
        } else {
            $rowCount = $db->affected_rows;
            DebugMessage("$rowCount seller hourly rows updated for hour $hour");
        }
    }
}

