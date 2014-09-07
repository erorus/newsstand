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

$canCache = false; //TODO
//HouseETag($house);
BotCheck();

$expansionLevels = array(60,70,80,85,90);
$expansions = array('Classic', 'Burning Crusade', 'Wrath of the Lich King', 'Cataclysm', 'Mists of Pandaria');
$qualities = array('Poor', 'Common', 'Uncommon', 'Rare', 'Epic', 'Legendary', 'Artifact', 'Heirloom');

json_return($resultFunc($house));

function CategoryResult_mining($house)
{
    return [
        'name' => 'Mining',
        'results' => [
            ['name' => 'ItemList', 'data' => ['name' => 'Pandarian Ore', 'items' => CategoryGenericItemList($house, 'i.id in (72092,72093,72103,72094)')]],
            ['name' => 'ItemList', 'data' => ['name' => 'Pandarian Bar', 'items' => CategoryGenericItemList($house, 'i.id in (72096,72095)')]],
            ['name' => 'ItemList', 'data' => ['name' => 'Cataclysm Ore', 'items' => CategoryGenericItemList($house, 'i.id in (52183,52185,53038)')]],
            ['name' => 'ItemList', 'data' => ['name' => 'Cataclysm Bar', 'items' => CategoryGenericItemList($house, 'i.id in (51950,53039,52186,54849)')]],
            ['name' => 'ItemList', 'data' => ['name' => 'Northrend Ore', 'items' => CategoryGenericItemList($house, 'i.id in (36912,36909,36910)')]],
            ['name' => 'ItemList', 'data' => ['name' => 'Northrend Bar', 'items' => CategoryGenericItemList($house, 'i.id in (36913,37663,41163,36916)')]],
            ['name' => 'ItemList', 'data' => ['name' => 'Outland Ore', 'items' => CategoryGenericItemList($house, 'i.id in (23424,23425,23426,23427)')]],
            ['name' => 'ItemList', 'data' => ['name' => 'Outland Bar', 'items' => CategoryGenericItemList($house, 'i.id in (23447,23449,35128,23446,23573,23445,23448)')]],
            ['name' => 'ItemList', 'data' => ['name' => 'Classic Ore', 'items' => CategoryGenericItemList($house, 'i.id in (7911,3858,10620,2772,2776,2771,2775,2770)')]],
            ['name' => 'ItemList', 'data' => ['name' => 'Classic Bar', 'items' => CategoryGenericItemList($house, 'i.id in (17771,12655,11371,12359,6037,3860,3859,3575,3577,2841,3576,2840,2842)')]],
        ]
    ];
}

function CategoryResult_skinning($house)
{
    global $expansions, $expansionLevels;

    $tr = ['name' => 'Skinning', 'results' => []];

    for ($x = count($expansions); $x--; $x >= 0) {
        $lsql = (($x > 0)?(' i.level >'.(($x <= 2)?'=':'').' '.$expansionLevels[$x-1].' and '):'').' i.level <'.(($x >= 3)?'=':'').' '.$expansionLevels[$x];
        if ($x == 0) $lsql .= ' or i.id in (17012,15414,15410,20381)';
        if ($x == 1) $lsql .= ' and i.id not in (17012,15414,15410,20381) or i.id = 25707';
        if ($x == 2) $lsql .= ' and i.id not in (25707,52977) or i.id = 38425';
        if ($x == 3) $lsql .= ' and i.id != 38425 or i.id = 52977';
        $lsql = 'i.class=7 and i.subclass=6 and i.quality > 0 and ('.$lsql.')';
        $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => $expansions[$x].' Leather', 'items' => CategoryGenericItemList($house, $lsql)]];
    }

    return $tr;
}

function CategoryResult_herbalism($house)
{
    global $expansions, $expansionLevels;

    $tr = ['name' => 'Herbalism', 'results' => []];

    for ($x = count($expansions); $x--; $x >= 0) {
        $lsql = (($x > 0)?(' i.level >'.(($x == 1)?'=':'').' '.$expansionLevels[$x-1].' and '):'').' i.level <'.(($x > 0)?'=':'').' '.$expansionLevels[$x];
        $lsql2 = '';
        if ($x == 0) $lsql .= ' or i.id=13468';
        if ($x == 1) $lsql .= ' and i.id != 13468';
        if ($x == 3) $lsql .= ' and i.id < 70000';
        if ($x == 4) {
            $lsql .= ' or i.id in (72234,72237)';
            $lsql2 = ' or i.id in (89639)';
        }
        $lsql = '((i.class=7 and i.subclass=9 and i.quality in (1,2) and ('.$lsql.'))'.$lsql2.')';
        $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => $expansions[$x].' Herbs', 'items' => CategoryGenericItemList($house, $lsql)]];
    }

    return $tr;
}

function CategoryResult_alchemy($house)
{
    global $expansions, $expansionLevels;

    $tr = ['name' => 'Alchemy', 'results' => []];

    $tr['results'][] = ['name' => 'ItemList', 'data' => [
        'name' => $expansions[count($expansions)-1].' Flasks',
        'items' => CategoryGenericItemList($house, 'i.id in (SELECT distinct xic.id FROM tblDBCSpell xs JOIN tblItem xic on xs.crafteditem=xic.id WHERE xs.skillline=171 and xic.level > '.$expansionLevels[count($expansionLevels)-2].' and xic.class=0 and xic.subclass=3)')
    ]];
    $tr['results'][] = ['name' => 'ItemList', 'data' => [
        'name' => $expansions[count($expansions)-1].' Restorative Potions',
        'items' => CategoryGenericItemList($house, 'i.id in (SELECT distinct xic.id FROM tblDBCSpell xs JOIN tblItem xic on xs.crafteditem=xic.id WHERE xs.skillline=171 and xic.level > '.$expansionLevels[count($expansionLevels)-2].' and xic.class=0 and xic.subclass=1 and xic.json like \'%restor%\')')
    ]];
    $tr['results'][] = ['name' => 'ItemList', 'data' => [
        'name' => $expansions[count($expansions)-1].' Buff Potions',
        'items' => CategoryGenericItemList($house, 'i.id in (SELECT distinct xic.id FROM tblDBCSpell xs JOIN tblItem xic on xs.crafteditem=xic.id WHERE xs.skillline=171 and xic.level > '.$expansionLevels[count($expansionLevels)-2].' and xic.class=0 and xic.subclass=1 and xic.json like \'%increas%\')')
    ]];
    $tr['results'][] = ['name' => 'ItemList', 'data' => [
        'name' => $expansions[count($expansions)-1].' Elixirs',
        'items' => CategoryGenericItemList($house, 'i.id in (SELECT distinct xic.id FROM tblDBCSpell xs JOIN tblItem xic on xs.crafteditem=xic.id WHERE xs.skillline=171 and xic.level > '.$expansionLevels[count($expansionLevels)-2].' and xic.class=0 and xic.subclass=2)')
    ]];
    $tr['results'][] = ['name' => 'ItemList', 'data' => [
        'name' => $expansions[count($expansions)-1].' Transmutes',
        'items' => CategoryGenericItemList($house, 'i.id in (SELECT distinct xic.id FROM tblDBCSpell xs JOIN tblItem xic on xs.crafteditem=xic.id WHERE xs.skillline=171 and xic.level > '.$expansionLevels[count($expansionLevels)-2].' and xic.class in (3,7))')
    ]];
    $tr['results'][] = ['name' => 'ItemList', 'data' => [
        'name' => 'General Purpose Elixirs and Potions',
        'items' => CategoryGenericItemList($house, 'i.id in (SELECT distinct xic.id FROM tblDBCSpell xs JOIN tblItem xic on xs.crafteditem=xic.id WHERE xs.skillline=171 and xic.class=0 and xic.subclass in (1,2) and xic.json not like \'%increas%\' and xic.json not like \'%restor%\' and xic.json not like \'%heal%\' and xic.json not like \'%regenerate%\' and xic.name not like \'%protection%\')')
    ]];

    return $tr;
}

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
    $params = ['where' => 'i.name like \'%cloth%\' and i.class=7'];

    return [
        'name' => 'Item List',
        'items' => CategoryGenericItemList($house, $params)
    ];
}

function CategoryGenericItemList($house, $params)
{
    global $db, $canCache;

    $key = 'category_gi_' . md5(json_encode($params));

    if ($canCache && (($tr = MCGetHouse($house, $key)) !== false))
        return $tr;

    DBConnect();

    if (is_array($params))
    {
        $joins = isset($params['joins']) ? $params['joins'] : '';
        $where = isset($params['where']) ? (' and '.$params['where']) : '';
    } else {
        $joins = '';
        $where = ($params == '') ? '' : (' and ' . $params);
    }

    $sql = <<<EOF
select i.id, i.name, i.quality, i.icon, i.class as classid, s.price, s.quantity, unix_timestamp(s.lastseen) lastseen, round(avg(h.price)) avgprice
from tblItem i
left join tblItemSummary s on s.house=? and s.item=i.id
left join tblItemHistory h on h.house=? and h.item=i.id
$joins
where ifnull(i.auctionable,1) = 1
$where
group by i.id
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    MCSetHouse($house, $key, $tr);

    return $tr;
}