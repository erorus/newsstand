<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house']) || !isset($_GET['item']))
    json_return(array());

$house = intval($_GET['house'], 10);
$item = intval($_GET['item'], 10);

if (!$item)
    json_return(array());

if ($json = MCGetHouse($house, 'item_'.$item))
    json_return($json);

DBConnect();

$json = array(
    'stats' => ItemStats($house, $item),
    'history' => ItemHistory($house, $item),
);

$ak = array_keys($json);
foreach ($ak as $k)
    if (count($json[$k]) == 0)
        unset($json[$k]);

$json = json_encode($json, JSON_NUMERIC_CHECK);

//MCSetHouse($house, 'search_'.$search, $json);

json_return($json);

function ItemStats($house, $item)
{
    global $db;

    $sql = <<<EOF
select i.id, i.name, i.quality, i.icon, i.class as classid, i.subclass, i.quality, i.level, i.stacksize, i.binds, i.buyfromvendor, i.selltovendor, i.auctionable,
s.price, s.quantity, s.lastseen
from tblItem i
left join tblItemSummary s on s.house = ? and s.item = i.id
where i.id = ?
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    if (count($tr))
        $tr = $tr[0];
    $stmt->close();
    return $tr;
}

function ItemHistory($house, $item)
{
    global $db;

    $sql = <<<EOF
select unix_timestamp(snapshot) snapshot, price, quantity
from tblItemHistory
where house = ? and item = ?
order by snapshot asc
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();
    return $tr;
}
