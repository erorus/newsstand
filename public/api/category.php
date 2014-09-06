<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house']) || !isset($_GET['id']))
    json_return(array());

$house = intval($_GET['house'], 10);
$page = preg_replace('/[^a-z]/', '', strtolower(trim($_GET['id'])));
$resultFunc = 'CategoryResult_'.$page;

if (!function_exists($resultFunc))
    json_return(array());

HouseETag($house);
BotCheck();
json_return($resultFunc($house));

function CategoryResult_demo($house)
{
    return [
        'name' => 'Demo',
        'results' => [
            ['name' => 'ItemList', 'data' => CategoryDemoItemList($house)]
        ]
    ];
}

function CategoryDemoItemList($house)
{
    global $db;

    $key = 'category_demo_itemlist2';

    if (($tr = MCGetHouse($house, $key)) !== false)
        return $tr;

    DBConnect();

    $sql = <<<EOF
select i.id, i.name, i.quality, i.icon, i.class as classid, s.price, s.quantity, unix_timestamp(s.lastseen) lastseen, round(avg(h.price)) avgprice
from tblItem i
left join tblItemSummary s on s.house=? and s.item=i.id
left join tblItemHistory h on h.house=? and h.item=i.id
where i.name like '%cloth%'
and ifnull(i.auctionable,1) = 1
group by i.id
limit 25
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = DBMapArray($result, null);
    $stmt->close();

    $tr = ['name' => 'Item List', 'items' => $items];

    MCSetHouse($house, $key, $tr);

    return $tr;
}

