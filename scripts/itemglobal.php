<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');

RunMeNTimes(1);
CatchKill();

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

DebugMessage('Starting..');

$stmt = $db->prepare('select distinct item from tblItemSummary');
$stmt->execute();
$result = $stmt->get_result();
$items = DBMapArray($result, null);
$stmt->close();

heartbeat();
if ($caughtKill)
    exit;

$sql = <<<END
SELECT price
FROM tblItemSummary ih, tblRealm r
WHERE item = ?
and r.canonical is not null
and ih.house = r.house
END;

DebugMessage('Updating items..');
$itemsCount = count($items);
$now = Date('Y-m-d H:i:s');
for ($z = 0; $z < $itemsCount; $z++) {
    $item = $items[$z];

    if ($caughtKill)
        break;

    if (heartbeat())
        DebugMessage("Processing item $z/$itemsCount (".round($z / $itemsCount * 100).'%)');

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $prices = DBMapArray($result, null);
    $stmt->close();

    sort($prices, SORT_NUMERIC);
    $cnt = count($prices);

    if ($cnt == 0)
        continue;

    $mean = 0;
    for ($x = 0; $x < $cnt; $x++) {
        $mean += $prices[$x] / $cnt;
    }
    $mean = round($mean);

    $stdDev = 0;
    for ($x = 0; $x < $cnt; $x++) {
        $stdDev += pow($prices[$x] - $mean, 2) / $cnt;
    }
    $stdDev = round(sqrt($stdDev));

    if ($cnt % 2 == 1) {
        $median = $prices[floor($cnt / 2)];
    } else {
        $median = round(($prices[floor($cnt / 2) - 1] + $prices[floor($cnt / 2)]) / 2);
    }

    $stmt = $db->prepare('insert into tblItemGlobalWorking (`when`, item, `median`, `mean`, `stddev`) values (?, ?, ?, ?, ?)');
    $stmt->bind_param('siiii', $now, $item, $median, $mean, $stdDev);
    $stmt->execute();
    $stmt->close();
}

if (!$caughtKill) {
    heartbeat();
    DebugMessage("Deleting old working rows");
    $db->query('delete from tblItemGlobalWorking where `when` < timestampadd(day, -1, now())');
}

if (!$caughtKill) {
    heartbeat();
    DebugMessage("Updating tblItemGlobal rows");
    $db->query('replace into tblItemGlobal (select `item`, avg(`median`), avg(`mean`), avg(`stddev`) from tblItemGlobalWorking group by `item`)');
}

DebugMessage('Done! Started '.TimeDiff($startTime));
