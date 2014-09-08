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
        'items' => CategoryGenericItemList($house, 'i.id in (SELECT distinct xic.id FROM tblDBCSpell xs JOIN tblItem xic on xs.crafteditem=xic.id WHERE xs.skillline=171 and xic.class=0 and xic.subclass in (1,2) and (xic.json not like \'%increas%\' or (xic.json like \'%speed%\' and xic.json not like \'%haste%\')) and xic.json not like \'%restor%\' and xic.json not like \'%heal%\' and xic.json not like \'%regenerate%\' and xic.name not like \'%protection%\')')
    ]];

    return $tr;
}

function CategoryResult_leatherworking($house)
{
    global $expansions, $expansionLevels, $db;

    $tr = ['name' => 'Leatherworking', 'results' => []];

    $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'Bags', 'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.class=1 and xs.skillline=165)')]];

    for ($x = (count($expansions)-1); $x >= 0 ; $x--) {
        $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => $expansions[$x].' Armor Kits', 'items' => CategoryGenericItemList($house, 'i.id in (select x.id from tblItem x, tblDBCSpell xs where xs.expansion='.$x.' and xs.crafteditem=x.id and x.class=0 and x.subclass=6 and xs.skillline=165)')]];
    }

    $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'Cloaks',       'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.requiredlevel = '.$expansionLevels[count($expansionLevels)-1].' and x.class=4 and x.subclass=1 and x.type=16 and xs.skillline=165)')]];
    $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'Epic Leather', 'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.requiredlevel = '.$expansionLevels[count($expansionLevels)-1].' and x.quality=4 and x.class=4 and x.subclass=2 and xs.skillline=165)')]];
    $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'Epic Mail',    'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.requiredlevel = '.$expansionLevels[count($expansionLevels)-1].' and x.quality=4 and x.class=4 and x.subclass=3 and xs.skillline=165)')]];

    if (($pvpLevels = MCGet('category_leatherworking_pvplevels_'.$expansionLevels[count($expansionLevels)-1])) === false)
    {
        DBConnect();
        $sql = <<<EOF
SELECT distinct i.level
FROM tblItem i
JOIN tblDBCSpell s on s.crafteditem=i.id
WHERE i.quality=3 and i.class=4 and s.skillline=165 and i.requiredlevel=? and i.json like '%pvp%'
order by 1 desc
EOF;

        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $expansionLevels[count($expansionLevels)-1]);
        $stmt->execute();
        $result = $stmt->get_result();
        $pvpLevels = DBMapArray($result, null);
        $stmt->close();

        MCSet('category_leatherworking_pvplevels_'.$expansionLevels[count($expansionLevels)-1], $pvpLevels, 24*60*60);
    }

    foreach ($pvpLevels as $itemLevel)
    {
        $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'PVP Rare '.$itemLevel.' Leather', 'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.requiredlevel = '.$expansionLevels[count($expansionLevels)-1].' and x.level='.$itemLevel.' and x.quality=3 and x.class=4 and x.subclass=2 and xs.skillline=165 and x.json like \'%pvp%\')')]];
        $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'PVP Rare '.$itemLevel.' Mail',    'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.requiredlevel = '.$expansionLevels[count($expansionLevels)-1].' and x.level='.$itemLevel.' and x.quality=3 and x.class=4 and x.subclass=3 and xs.skillline=165 and x.json like \'%pvp%\')')]];
    }

    return $tr;
}

function CategoryResult_blacksmithing($house)
{
    global $expansions, $expansionLevels, $db, $qualities;

    $tr = ['name' => 'Blacksmithing', 'results' => []];
    $sortIndex = 0;

    for ($x = 1; $x <= 3; $x++)
    {
        $idx = count($expansions) - $x;
        $nm = ($x == 3) ? 'Other' : $expansions[$idx];
        $tr['results'][] = ['name' => 'ItemList', 'sort' => ['main' => $sortIndex++], 'data' => ['name' => $nm.' Consumables', 'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and xs.expansion'.($x == 3 ? '<=' : '=').$idx.' and x.level>40 and x.class=0 and xs.skillline=164)')]];
    }

    $key = 'category_blacksmithing_levels_'.(count($expansionLevels)-1);
    if (($armorLevels = MCGet($key)) === false)
    {
        DBConnect();

        $sql = <<<EOF
SELECT x.class, x.level, x.quality, sum( if( x.json LIKE '%pvp%', 1, 0 ) ) haspvp, sum( if( x.json NOT LIKE '%pvp%', 1, 0 ) ) haspve
FROM tblItem x, tblDBCSpell s
WHERE x.quality BETWEEN 2 AND 4
AND s.expansion = ?
AND s.crafteditem = x.id
AND x.binds != 1
AND x.class IN ( 2, 4 )
AND s.skillline = 164
GROUP BY x.class, x.level, x.quality
ORDER BY x.class DESC, x.quality DESC, x.level DESC

EOF;

        $stmt = $db->prepare($sql);
        $exp = count($expansionLevels)-1;
        $stmt->bind_param('i', $exp);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = DBMapArray($result, null);
        $stmt->close();

        $armorLevels = ['PvE Armor' => [], 'PvP Armor' => [], 'Weapon' => []];
        foreach ($armorLevels as $k => $v) for ($x = 4; $x >= 2; $x--) $armorLevels[$k][$x] = array();

        foreach ($rows as $row) {
            if ($row['class'] == 2) {
                if (!in_array($row['level'],$armorLevels['Weapon'][$row['quality']])) {
                    $armorLevels['Weapon'][$row['quality']][] = $row['level'];
                }
            } else {
                if ($row['haspvp'] > 0) {
                    $armorLevels['PvP Armor'][$row['quality']][] = $row['level'];
                }
                if ($row['haspve'] > 0) {
                    $armorLevels['PvE Armor'][$row['quality']][] = $row['level'];
                }
            }
        }

        MCSet($key, $armorLevels, 24*60*60);
    }

    foreach ($armorLevels as $armorName => $armorQualities) {
        $classId = ($armorName == 'Weapon')?2:4;
        foreach ($armorQualities as $q => $levels) {
            foreach ($levels as $level) {
                $tr['results'][] = ['name' => 'ItemList', 'sort' => ['main' => $sortIndex, 'level' => $level, 'quality' => $q, 'name' => $armorName], 'data' => ['name' => $qualities[$q].' '.$level.' '.$armorName, 'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCItemReagents xir where xir.item=x.id and x.json '.(($armorName == 'PvP Armor')?'':'not').' like \'%pvp%\' and x.level='.$level.' and x.class='.$classId.' and xir.skillline=164 and x.quality='.$q.')')]];
            }
        }
    }
    $sortIndex++;

    usort($tr['results'], function($a,$b) {
        if ($a['sort']['main'] != $b['sort']['main'])
            return $a['sort']['main'] - $b['sort']['main'];
        if ($a['sort']['level'] != $b['sort']['level'])
            return $b['sort']['level'] - $a['sort']['level'];
        if ($a['sort']['quality'] != $b['sort']['quality'])
            return $b['sort']['quality'] - $a['sort']['quality'];
        return strcmp($a['sort']['name'], $b['sort']['name']);
    });

    return $tr;
}

function CategoryResult_engineering($house)
{
    global $expansions, $expansionLevels;

    $tr = ['name' => 'Engineering', 'results' => []];

    $exp = $expansions[count($expansions)-1];

    $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => $exp.' Weapons and Armor',  'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and xs.expansion='.(count($expansions)-1).' and x.level>40 and (x.class=2 or (x.class=4 and x.subclass>0)) and xs.skillline=202)')]];
    $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'Ranged Enchants (Scopes)', 'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.level>40 and (x.json like \'%bow or gun%\' or x.json like \'%ranged weapon%\') and xs.skillline=202)')]];
    $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'Companions',               'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.class=15 and x.subclass=2 and xs.skillline=202)')]];
    $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'Mounts',                   'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.class=15 and x.subclass=5 and xs.skillline=202)')]];

$sql = <<<EOF
i.id in (
    select distinct x.id
    from tblItem x, tblDBCSpell xs
    where xs.crafteditem=x.id
    and ifnull(x.requiredskill,0) != 202
    and (
        (x.class=4 and (x.subclass not in (1,2,3,4) or x.level < 10))
        or (x.class in (0,7) and x.id not in (
            select reagent
            from tblDBCItemReagents xir2
            where xir2.reagent=x.id))
        )
    and xs.skillline=202
    and x.json not like '%bow or gun%'
    and x.json not like '%ranged weapon%'
    )
EOF;

    $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'Non-Engineer Items', 'items' => CategoryGenericItemList($house, $sql)]];

    return $tr;
}

function CategoryResult_tailoring($house)
{
    global $expansions, $expansionLevels, $db;

    $tr = ['name' => 'Tailoring', 'results' => []];


    $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'Common Cloth', 'items' => CategoryGenericItemList($house, 'i.id in (2589,2592,4306,4338,14047,21877,33470,53010,72988)')]];

    $x = count($expansions);
    $x--; $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => $expansions[$x].' Bags', 'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.level between '.$expansionLevels[$x-1].'+1 and '.$expansionLevels[$x].' and x.class=1 and x.subclass=0 and xs.skillline=197)')]];
    $x--; $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => $expansions[$x].' Bags', 'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.level between '.$expansionLevels[$x-1].'+1 and '.$expansionLevels[$x].' and x.class=1 and x.subclass=0 and xs.skillline=197)')]];
    $x--; $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'Other Bags',            'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.level<='.$expansionLevels[$x].' and x.level>40 and x.class=1 and x.subclass=0 and xs.skillline=197)')]];

    $x = count($expansions);
    $x--; $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => $expansions[$x].' Profession Bags', 'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.level between '.$expansionLevels[$x-1].'+1 and '.$expansionLevels[$x].' and x.class=1 and x.subclass!=0 and xs.skillline=197)')]];
    $x--; $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => $expansions[$x].' Profession Bags', 'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.level between '.$expansionLevels[$x-1].'+1 and '.$expansionLevels[$x].' and x.class=1 and x.subclass!=0 and xs.skillline=197)')]];
    $x--; $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'Other Profession Bags',            'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.level<='.$expansionLevels[$x].' and x.level>40 and x.class=1 and x.subclass!=0 and xs.skillline=197)')]];

    $x = count($expansions);
    $x--; $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => $expansions[$x].' Spellthread', 'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and xs.expansion='.$x.' and x.class=0 and x.subclass=6 and xs.skillline=197)')]];
    $x--; $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => $expansions[$x].' Spellthread', 'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and xs.expansion='.$x.' and x.class=0 and x.subclass=6 and xs.skillline=197)')]];
    $x--; $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'Other Spellthread',            'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and xs.expansion<='.$x.' and x.class=0 and x.subclass=6 and xs.skillline=197)')]];

    if (($pvpLevels = MCGet('category_tailoring_pvplevels_'.$expansionLevels[count($expansionLevels)-1])) === false)
    {
        DBConnect();
        $sql = <<<EOF
SELECT distinct i.level
FROM tblItem i
JOIN tblDBCSpell s on s.crafteditem=i.id
WHERE i.quality=3 and i.class=4 and s.skillline=197 and i.requiredlevel=? and i.json like '%pvp%'
order by 1 desc
EOF;

        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $expansionLevels[count($expansionLevels)-1]);
        $stmt->execute();
        $result = $stmt->get_result();
        $pvpLevels = DBMapArray($result, null);
        $stmt->close();

        MCSet('category_tailoring_pvplevels_'.$expansionLevels[count($expansionLevels)-1], $pvpLevels, 24*60*60);
    }

    $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'Epic Armor', 'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.requiredlevel = '.$expansionLevels[count($expansionLevels)-1].' and x.quality=4 and x.class=4 and xs.skillline=197)')]];

    foreach ($pvpLevels as $pvpLevel)
        $tr['results'][] = ['name' => 'ItemList', 'data' => ['name' => 'PVP '.$pvpLevel.' Rare Armor',  'items' => CategoryGenericItemList($house, 'i.id in (select distinct x.id from tblItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.requiredlevel = '.$expansionLevels[count($expansionLevels)-1].' and x.level = '.$pvpLevel.' and x.quality=3 and x.class=4 and xs.skillline=197 and x.json like \'%pvp%\')')]];

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
    if (!$stmt) {
        DebugMessage("Bad SQL: \n".$sql, E_USER_ERROR);
    }
    $stmt->bind_param('ii', $house, $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    MCSetHouse($house, $key, $tr);

    return $tr;
}