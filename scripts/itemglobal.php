<?php

chdir(__DIR__);

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
union all
SELECT price
FROM tblItemSummary ih, tblRealm r
WHERE item = ?
and r.canonical is not null
and ih.house = cast(r.house as signed) * -1
END;

DebugMessage('Updating items..');
$itemsCount = count($items);
for ($z = 0; $z < $itemsCount; $z++) {
    $item = $items[$z];

    if ($caughtKill)
        exit;

    if (heartbeat())
        DebugMessage("Processing item $z/$itemsCount (".round($z / $itemsCount * 100).'%)');

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $item, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $prices = DBMapArray($result, null);
    $stmt->close();

    sort($prices, SORT_NUMERIC);
    $cnt = count($prices);

    if ($cnt == 0)
        continue;

    $mean = 0;
    for ($x = 0; $x < count($prices); $x++) {
        $mean += $prices[$x] / $cnt;
    }
    $mean = round($mean);

    $stdDev = 0;
    for ($x = 0; $x < count($prices); $x++) {
        $stdDev += pow($prices[$x] - $mean, 2) / $cnt;
    }
    $stdDev = round(sqrt($stdDev));

    if ($cnt % 2 == 1) {
        $median = $prices[floor($cnt / 2)];
    } else {
        $median = round(($prices[floor($cnt / 2) - 1] + $prices[floor($cnt / 2)]) / 2);
    }

    $stmt = $db->prepare('replace into tblItemGlobal (item, `median`, `mean`, `stddev`) values (?, ?, ?, ?)');
    $stmt->bind_param('iiii', $item, $median, $mean, $stdDev);
    $stmt->execute();
    $stmt->close();
}

DebugMessage('Done!');
