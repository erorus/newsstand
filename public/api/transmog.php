<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house']) || !isset($_GET['id'])) {
    json_return(array());
}

$house = intval($_GET['house'], 10);
$page = preg_replace('/[^a-z]/', '', strtolower(trim($_GET['id'])));
$resultFunc = 'TransmogResult_' . $page;

if (!function_exists($resultFunc)) {
    json_return(array());
}

$canCache = false;
BotCheck();
if ($canCache) {
    HouseETag($house);
}

$itemTypes = array(
    16	=> 'Back',
    18	=> 'Bag',
    5	=> 'Chest',
    8	=> 'Feet',
    11	=> 'Finger',
    10	=> 'Hands',
    1	=> 'Head',
    23	=> 'Held In Off-hand',
    7	=> 'Legs',
    21	=> 'Main Hand',
    2	=> 'Neck',
    22	=> 'Off Hand',
    13	=> 'One-Hand',
    24	=> 'Projectile',
    15	=> 'Ranged',
    28	=> 'Relic',
    14	=> 'Shield',
    4	=> 'Shirt',
    3	=> 'Shoulder',
    19	=> 'Tabard',
    25	=> 'Thrown',
    12	=> 'Trinket',
    17	=> 'Two-Hand',
    6	=> 'Waist',
    9	=> 'Wrist',
    20  => 'Robe',
);

json_return($resultFunc($house));

function TransmogResult_cloth($house)
{
    global $itemTypes;
    $tr = [];
    $trId = TransmogGenericItemList($house, ['where' => 'i.class = 4 and i.subclass = 1', 'group' => ['type']]);
    foreach ($trId as $typeId => &$items) {
        $k = isset($itemTypes[$typeId]) ? $itemTypes[$typeId] : $typeId;
        if (!isset($tr[$k])) {
            $tr[$k] = [];
        }
        $tr[$k] = array_merge($tr[$k], $items);
    }
    return $tr;
}

function TransmogGenericItemList($house, $params)
{
    global $db, $canCache;

    $key = 'transmog_gi_' . md5(json_encode($params));

    if ($canCache && (($tr = MCGetHouse($house, $key)) !== false)) {
        return $tr;
    }

    DBConnect();

    if (is_array($params)) {
        $joins = isset($params['joins']) ? $params['joins'] : '';
        $where = isset($params['where']) ? (' and ' . $params['where']) : '';
        $group = isset($params['group']) ? array_merge($params['group'], [false]) : null;
    } else {
        $joins = '';
        $group = null;
        $where = ($params == '') ? '' : (' and ' . $params);
    }

    $sql = <<<EOF
select ab.id, ab.display, ab.buy, ab.class, ab.subclass, ab.type
from (
    select aa.*, if(@previd = aa.display, 0, @previd := aa.display) previd
    from (select @previd := 0) aasetup, (
        SELECT i.id, i.display, a.buy, i.class, i.subclass, i.type
        FROM `tblDBCItem` i
        join tblAuction a on a.item=i.id
        $joins
        WHERE i.auctionable=1
        and i.quality > 1
        $where
        and i.display is not null
        and a.house = ?
        and a.buy > 0
        order by i.display, a.buy) aa
    ) ab
where ab.previd > 0
EOF;

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        DebugMessage("Bad SQL: \n" . $sql, E_USER_ERROR);
    }
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, $group);
    $stmt->close();

    MCSetHouse($house, $key, $tr);

    return $tr;
}
