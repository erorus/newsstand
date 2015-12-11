<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');

// http://www.wowhead.com/items?filter=cr=158:2;crs=824:2;crv=0:0
$indexCategories = [
    'mining' => [109118,109119],
    'herbalism' => [109124,109125,109126,109127,109128,109129],
    'cooking' => range(109131,109144),
    'enchanting' => [109693],
    'skinning' => [110609],
    'tailoring' => [111557],
];

RunMeNTimes(1);
CatchKill();

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

if (APIMaintenance()) {
    DebugMessage('API Maintenance in progress, not updating market index!', E_USER_NOTICE);
    exit;
}

DebugMessage('Starting..');

foreach (['US','EU'] as $region) {
    CatchUp($region);
    IndexRegionDay($region, strtotime('yesterday'));
    IndexRegionDay($region, time());
}

DebugMessage('Done! Started ' . TimeDiff($startTime));

function CatchUp($region) {
    global $db;
    if (!in_array($region, ['US','EU'])) {
        return;
    }

    $stmt = $db->prepare('SELECT `when` FROM `tblMarketIndex` WHERE `region` = ? AND `total` != 0');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $dates = DBMapArray($result);
    $stmt->close();

    $dt = strtotime('2015-04-01');
    while ($dt < time()) { // hack
        if (!isset($dates[Date('Y-m-d', $dt)])) {
            IndexRegionDay($region, $dt);
        }
        $dt = strtotime('tomorrow', $dt);
    }
}

function IndexRegionDay($region, $timestamp) {
    global $db, $indexCategories, $caughtKill;

    heartbeat();
    if ($caughtKill) {
        exit;
    }


    $indexValues = [];

    $total = 0;
    $when = Date('Y-m-d', $timestamp);
    $month = (intval(Date('Y', $timestamp), 10) - 2014) * 12 + intval(Date('m', $timestamp), 10);
    $day = Date('d', $timestamp);
    $dayPadded = str_pad($day, 2, '0', STR_PAD_LEFT);

    DebugMessage("Processing $region $when");

    $sql = <<<'EOF'
SELECT `mktslvr%1$s`
FROM `tblItemHistoryMonthly` `ihm`
JOIN `tblRealm` `r` ON `r`.`house` = `ihm`.`house` AND `r`.`canonical` IS NOT NULL
WHERE `r`.`region` = ?
AND `ihm`.`item` IN (%2$s)
AND `ihm`.`bonusset` = 0
AND `ihm`.`month` = ?
AND `mktslvr%1$s` IS NOT NULL
ORDER BY 1
EOF;
    foreach ($indexCategories as $catName => $catItems) {
        heartbeat();
        if ($caughtKill) {
            exit;
        }

        $stmt = $db->prepare(sprintf($sql, $dayPadded, implode(',',$catItems)));
        $stmt->bind_param('si', $region, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        $prices = DBMapArray($result, null);
        $stmt->close();

        $total += $indexValues[$catName] = MedianValue($prices);
    }

    $sql = 'REPLACE INTO `tblMarketIndex` (`region`, `when`, `total`';
    $sqlVals = ') VALUES (?, ?, ?';

    $types = 'ssi';
    $valOrdered = [null, null];
    $valOrdered[] =& $region;
    $valOrdered[] =& $when;
    $valOrdered[] =& $total;

    foreach (array_keys($indexValues) as $catName) {
        $sql .= ", `$catName`";
        $sqlVals .= ", ?";
        $valOrdered[] =& $indexValues[$catName];
        $types .= 'i';
    }
    $sql .= $sqlVals . ')';

    $stmt = $db->prepare($sql);
    $valOrdered[0] =& $stmt;
    $valOrdered[1] =& $types;
    call_user_func_array('mysqli_stmt_bind_param', $valOrdered);
    if (!$stmt->execute()) {
        DebugMessage("Error saving $region $when\n$sql\n".print_r($valOrdered, true));
    }
    $stmt->close();
}

function MedianValue($values) {
    if (count($values) == 0) {
        return 0;
    }

    sort($values, SORT_NUMERIC);
    $mid = intval(floor(count($values) / 2));
    if (count($values) % 2 == 1) {
        return $values[$mid];
    } else {
        return round(($values[$mid] + $values[$mid-1]) / 2);
    }
}

