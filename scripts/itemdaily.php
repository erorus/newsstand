<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');

ini_set('memory_limit', '256M');

RunMeNTimes(1);
CatchKill();

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

if (APIMaintenance()) {
    DebugMessage('API Maintenance in progress, not updating daily items!', E_USER_NOTICE);
    exit;
}

AddDailyData();
DebugMessage('Done! Started ' . TimeDiff($startTime));

function AddDailyData()
{
    global $db;

    if (CatchKill()) {
        return;
    }

    $sql = <<<'EOF'
select distinct hc.house, date(sn.updated) dt
from tblHouseCheck hc
join tblSnapshot sn on sn.house = hc.house
where ifnull(hc.lastdaily, '2000-01-01') < date(timestampadd(day, -1, now()))
and sn.updated > timestampadd(day, 1, ifnull(hc.lastdaily, '2000-01-01'))
and sn.updated < date(now())
and sn.flags & 1 = 0
order by 1, 2
EOF;

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $houses = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    DebugMessage(count($houses) . " houses need updates");

    $sqlPattern = <<<'EOF'
replace into tblItemHistoryDaily
(SELECT `ihh`.item, `ihh`.house, `ihh`.`when`,
ifnull(nullif(least(
ifnull(silver00, 4294967295),ifnull(silver01, 4294967295),ifnull(silver02, 4294967295),ifnull(silver03, 4294967295),
ifnull(silver04, 4294967295),ifnull(silver05, 4294967295),ifnull(silver06, 4294967295),ifnull(silver07, 4294967295),
ifnull(silver08, 4294967295),ifnull(silver09, 4294967295),ifnull(silver10, 4294967295),ifnull(silver11, 4294967295),
ifnull(silver12, 4294967295),ifnull(silver13, 4294967295),ifnull(silver14, 4294967295),ifnull(silver15, 4294967295),
ifnull(silver16, 4294967295),ifnull(silver17, 4294967295),ifnull(silver18, 4294967295),ifnull(silver19, 4294967295),
ifnull(silver20, 4294967295),ifnull(silver21, 4294967295),ifnull(silver22, 4294967295),ifnull(silver23, 4294967295)),4294967295),0) pricemin,

ifnull(round((
ifnull(silver00, 0)+ifnull(silver01, 0)+ifnull(silver02, 0)+ifnull(silver03, 0)+
ifnull(silver04, 0)+ifnull(silver05, 0)+ifnull(silver06, 0)+ifnull(silver07, 0)+
ifnull(silver08, 0)+ifnull(silver09, 0)+ifnull(silver10, 0)+ifnull(silver11, 0)+
ifnull(silver12, 0)+ifnull(silver13, 0)+ifnull(silver14, 0)+ifnull(silver15, 0)+
ifnull(silver16, 0)+ifnull(silver17, 0)+ifnull(silver18, 0)+ifnull(silver19, 0)+
ifnull(silver20, 0)+ifnull(silver21, 0)+ifnull(silver22, 0)+ifnull(silver23, 0)
) / (24 -
isnull(silver00)-isnull(silver01)-isnull(silver02)-isnull(silver03)-
isnull(silver04)-isnull(silver05)-isnull(silver06)-isnull(silver07)-
isnull(silver08)-isnull(silver09)-isnull(silver10)-isnull(silver11)-
isnull(silver12)-isnull(silver13)-isnull(silver14)-isnull(silver15)-
isnull(silver16)-isnull(silver17)-isnull(silver18)-isnull(silver19)-
isnull(silver20)-isnull(silver21)-isnull(silver22)-isnull(silver23))),0) priceavg,

greatest(
ifnull(silver00, 0),ifnull(silver01, 0),ifnull(silver02, 0),ifnull(silver03, 0),
ifnull(silver04, 0),ifnull(silver05, 0),ifnull(silver06, 0),ifnull(silver07, 0),
ifnull(silver08, 0),ifnull(silver09, 0),ifnull(silver10, 0),ifnull(silver11, 0),
ifnull(silver12, 0),ifnull(silver13, 0),ifnull(silver14, 0),ifnull(silver15, 0),
ifnull(silver16, 0),ifnull(silver17, 0),ifnull(silver18, 0),ifnull(silver19, 0),
ifnull(silver20, 0),ifnull(silver21, 0),ifnull(silver22, 0),ifnull(silver23, 0)) pricemax,

ifnull(coalesce(
silver00, silver01, silver02, silver03, silver04, silver05,
silver06, silver07, silver08, silver09, silver10, silver11,
silver12, silver13, silver14, silver15, silver16, silver17,
silver18, silver19, silver20, silver21, silver22, silver23),0) pricestart,

ifnull(coalesce(
silver23, silver22, silver21, silver20, silver19, silver18,
silver17, silver16, silver15, silver14, silver13, silver12,
silver11, silver10, silver09, silver08, silver07, silver06,
silver05, silver04, silver03, silver02, silver01, silver00),0) priceend,

ifnull(nullif(least(
ifnull(quantity00, 4294967295),ifnull(quantity01, 4294967295),ifnull(quantity02, 4294967295),ifnull(quantity03, 4294967295),
ifnull(quantity04, 4294967295),ifnull(quantity05, 4294967295),ifnull(quantity06, 4294967295),ifnull(quantity07, 4294967295),
ifnull(quantity08, 4294967295),ifnull(quantity09, 4294967295),ifnull(quantity10, 4294967295),ifnull(quantity11, 4294967295),
ifnull(quantity12, 4294967295),ifnull(quantity13, 4294967295),ifnull(quantity14, 4294967295),ifnull(quantity15, 4294967295),
ifnull(quantity16, 4294967295),ifnull(quantity17, 4294967295),ifnull(quantity18, 4294967295),ifnull(quantity19, 4294967295),
ifnull(quantity20, 4294967295),ifnull(quantity21, 4294967295),ifnull(quantity22, 4294967295),ifnull(quantity23, 4294967295)),4294967295),0) quantitymin,

ifnull(round((
ifnull(quantity00, 0)+ifnull(quantity01, 0)+ifnull(quantity02, 0)+ifnull(quantity03, 0)+
ifnull(quantity04, 0)+ifnull(quantity05, 0)+ifnull(quantity06, 0)+ifnull(quantity07, 0)+
ifnull(quantity08, 0)+ifnull(quantity09, 0)+ifnull(quantity10, 0)+ifnull(quantity11, 0)+
ifnull(quantity12, 0)+ifnull(quantity13, 0)+ifnull(quantity14, 0)+ifnull(quantity15, 0)+
ifnull(quantity16, 0)+ifnull(quantity17, 0)+ifnull(quantity18, 0)+ifnull(quantity19, 0)+
ifnull(quantity20, 0)+ifnull(quantity21, 0)+ifnull(quantity22, 0)+ifnull(quantity23, 0)
) / (24 -
isnull(quantity00)-isnull(quantity01)-isnull(quantity02)-isnull(quantity03)-
isnull(quantity04)-isnull(quantity05)-isnull(quantity06)-isnull(quantity07)-
isnull(quantity08)-isnull(quantity09)-isnull(quantity10)-isnull(quantity11)-
isnull(quantity12)-isnull(quantity13)-isnull(quantity14)-isnull(quantity15)-
isnull(quantity16)-isnull(quantity17)-isnull(quantity18)-isnull(quantity19)-
isnull(quantity20)-isnull(quantity21)-isnull(quantity22)-isnull(quantity23))),0) quantityavg,

greatest(
ifnull(quantity00, 0),ifnull(quantity01, 0),ifnull(quantity02, 0),ifnull(quantity03, 0),
ifnull(quantity04, 0),ifnull(quantity05, 0),ifnull(quantity06, 0),ifnull(quantity07, 0),
ifnull(quantity08, 0),ifnull(quantity09, 0),ifnull(quantity10, 0),ifnull(quantity11, 0),
ifnull(quantity12, 0),ifnull(quantity13, 0),ifnull(quantity14, 0),ifnull(quantity15, 0),
ifnull(quantity16, 0),ifnull(quantity17, 0),ifnull(quantity18, 0),ifnull(quantity19, 0),
ifnull(quantity20, 0),ifnull(quantity21, 0),ifnull(quantity22, 0),ifnull(quantity23, 0)) quantitymax

FROM `tblItemHistoryHourly` ihh
JOIN `tblDBCItem` `i` ON `i`.`id` = `ihh`.`item`
WHERE i.stacksize > 1 and ihh.house = ? and ihh.`when` = ?)
EOF;

    foreach ($houses as $houseRow) {
        heartbeat();
        if (CatchKill()) {
            return;
        }

        if (!MCHouseLock($houseRow['house'])) {
            continue;
        }

        $stmt = $db->prepare($sqlPattern);
        $stmt->bind_param('is', $houseRow['house'], $houseRow['dt']);
        $queryOk = $stmt->execute();
        $rowCount = $db->affected_rows;
        $stmt->close();

        if (!$queryOk) {
            DebugMessage("SQL error: " . $db->errno . ' ' . $db->error . " - " . substr(preg_replace('/[\r\n]/', ' ', $sqlPattern), 0, 500), E_USER_WARNING);
            $rowCount = -1;
        } else {
            DailyDebugMessage($houseRow['house'], $houseRow['dt'], "$rowCount item daily rows updated");
        }

        if ($rowCount >= 0) {
            $rowCount = UpdateMonthlyTable($houseRow['house'], $houseRow['dt']);
            DailyDebugMessage($houseRow['house'], $houseRow['dt'], "$rowCount monthly item rows updated");
        }

        if ($rowCount >= 0) {
            $stmt = $db->prepare('INSERT INTO tblHouseCheck (house, lastdaily) VALUES (?, ?) ON DUPLICATE KEY UPDATE lastdaily = values(lastdaily)');
            $stmt->bind_param('is', $houseRow['house'], $houseRow['dt']);
            $stmt->execute();
            $stmt->close();
        }

        MCHouseUnlock($houseRow['house']);
    }
}

function UpdateMonthlyTable($house, $dt) {
    global $db;

    $dtstamp = strtotime($dt);
    $month = (intval(date('Y', $dtstamp), 10) - 2014) * 12 + intval(date('m', $dtstamp), 10);
    $day = date('d', $dtstamp);

    $toUpdate = [];

    $sql = 'select * from tblItemHistoryHourly where house = ? and `when` = ?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $house, $dt);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bestQty = 0;
        $bestPrice = null;
        for ($h = 0; $h <= 23; $h++) {
            $hPad = sprintf('%02s', $h);
            if (!is_null($row["quantity$hPad"]) && ($row["quantity$hPad"] >= $bestQty)) {
                $bestQty = $row["quantity$hPad"];
                $bestPrice = $row["silver$hPad"];
            }
        }
        if (is_null($bestPrice)) {
            continue;
        }
        $toUpdate[] = [$row['item'], $row['level'], $bestQty, $bestPrice];
    }
    $result->close();
    $stmt->close();

    $sql = <<<'EOF'
insert ignore into tblItemHistoryMonthly (item, house, level, month)
(SELECT s.item, ?, s.level, ?
    FROM tblItemSummary s
    JOIN tblItemSummary s2 on s2.item = s.item
    LEFT JOIN tblItemHistoryMonthly ihm on ihm.item = s.item and ihm.house = ? and ihm.level = s.level and ihm.month = ?
    WHERE s.house = ? and s2.house = ? and s2.lastseen >= ?
    AND ihm.item is null)
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('iiiiiis', $house, $month, $house, $month, $house, $house, $dt);
    $stmt->execute();
    $stmt->close();

    $sql = <<<'EOF'
update tblItemHistoryMonthly
set mktslvr%1$s = ?, qty%1$s = ?
where item = ? and house = %2$d and level = ? and month = %3$d
limit 1
EOF;

    $sql = sprintf($sql, $day, $house, $month);

    $stmt = $db->prepare($sql);
    $item = $level = $silver = $qty = null;
    $stmt->bind_param('iiii', $silver, $qty, $item, $level);

    $updated = 0;
    foreach ($toUpdate as $data) {
        list($item, $level, $qty, $silver) = $data;
        $stmt->execute();
        $updated += $stmt->affected_rows;
    }
    $stmt->close();

    return $updated;
}

function DailyDebugMessage($house, $dt, $msg) {
    DebugMessage('House ' . str_pad($house, 5, ' ', STR_PAD_LEFT) . " $dt $msg");
}
