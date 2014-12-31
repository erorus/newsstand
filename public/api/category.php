<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house']) || !isset($_GET['id'])) {
    json_return(array());
}

$house = intval($_GET['house'], 10);
$page = preg_replace('/[^a-z]/', '', strtolower(trim($_GET['id'])));
$resultFunc = 'CategoryResult_' . $page;

if (!function_exists($resultFunc)) {
    json_return(array());
}

$canCache = true;
BotCheck();
HouseETag($house);

$expansionLevels = array(60, 70, 80, 85, 90, 100);
$expansions = array(
    'Classic',
    'Burning Crusade',
    'Wrath of the Lich King',
    'Cataclysm',
    'Mists of Pandaria',
    'Warlords of Draenor'
);
$qualities = array('Poor', 'Common', 'Uncommon', 'Rare', 'Epic', 'Legendary', 'Artifact', 'Heirloom');

json_return($resultFunc($house));

function CategoryResult_battlepets($house)
{
    global $db, $canCache;

    $key = 'category_bpets';

    if ($canCache && (($tr = MCGetHouse($house, $key)) !== false)) {
        return ['name' => 'Battle Pets', 'results' => [['name' => 'BattlePetList', 'data' => $tr]]];
    }

    DBConnect();

    $sql = <<<EOF
SELECT ps.species, ps.breed, ps.price, ps.quantity, ps.lastseen, round(avg(ph.price)) avgprice, p.name, p.type, p.icon, p.npc
FROM tblPetSummary ps
JOIN tblPet p on ps.species=p.id
LEFT JOIN tblPetHistory ph on ph.house = ps.house and ph.species = ps.species and ph.breed = ps.breed
WHERE ps.house = ?
group by ps.species, ps.breed
EOF;

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        DebugMessage("Bad SQL: \n" . $sql, E_USER_ERROR);
    }
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('type', 'species', 'breed'));
    $stmt->close();

    MCSetHouse($house, $key, $tr);

    return ['name' => 'Battle Pets', 'results' => [['name' => 'BattlePetList', 'data' => $tr]]];
}

function CategoryResult_deals($house)
{
    $tr = [
        'name'    => 'Deals',
        'results' => [
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Dropped Rare and Epic Armor/Weapons',
                    'items'       => CategoryDealsItemList($house, 'i.class in (2,4) and i.quality > 2'),
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Dropped Uncommon Armor/Weapons',
                    'items'       => CategoryDealsItemList($house, 'i.class in (2,4) and i.quality = 2'),
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Dropped Common/Junk Armor/Weapons',
                    'items'       => CategoryDealsItemList($house, 'i.class in (2,4) and i.quality < 2'),
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Uncommon Recipes',
                    'items'       => CategoryDealsItemList($house, 'i.class = 9 and i.quality > 1'),
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Common Recipes',
                    'items'       => CategoryDealsItemList($house, 'i.class = 9 and i.quality <= 1'),
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Crafted Armor/Weapons',
                    'items'       => CategoryDealsItemList($house, 'i.class in (2,4)', -1),
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Dropped Consumables',
                    'items'       => CategoryDealsItemList($house, 'i.class = 0'),
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Trade Goods',
                    'items'       => CategoryDealsItemList($house, 'i.class = 7'),
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Companion Deals',
                    'items'       => CategoryDealsItemList($house, 'i.class = 15 and i.subclass in (2,5)'),
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Miscellaneous Items',
                    'items'       => CategoryDealsItemList($house, '(i.class in (12,13) or (i.class=15 and i.subclass not in (2,5)))'),
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'none'
                ]
            ],
        ]
    ];

    $joins = <<<EOF
join (select item, bidper as bid from
(select ib.item, ib.bidper, avg(ih.price) avgprice, stddev_pop(ih.price) sdprice from
(select i.id as item, min(a.bid/a.quantity) bidper
from tblAuction a
join tblDBCItem i on i.id=a.item
left join tblDBCItemVendorCost ivc on ivc.item=i.id
where a.house=%1\$d
and i.quality > 0
and ivc.copper is null
group by i.id) ib
join tblItemHistory ih on ih.item=ib.item and ih.house=%1\$d
group by ib.item) iba
where iba.sdprice < iba.avgprice/2
and iba.bidper / iba.avgprice < 0.2
order by iba.bidper / iba.avgprice asc
limit 20) lowbids on i.id=lowbids.item
EOF;

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'        => 'Potential Low Bids',
            'items'       => CategoryGenericItemList(
                $house, [
                    'joins' => sprintf($joins, $house),
                    'where' => 'ifnull(lowbids.bid / g.median, 0) < 0.2',
                    'cols'  => 'lowbids.bid, g.median globalmedian'
                ]
            ),
            'hiddenCols'  => ['price' => true, 'lastseen' => true],
            'visibleCols' => ['bid' => true, 'globalmedian' => true],
            'sort'        => 'lowbids'
        ]
    ];

    return $tr;
}

function CategoryResult_unusuals($house)
{
    return [
        'name'    => 'Unusual Items',
        'results' => [
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Dropped Rare and Epic Armor/Weapons',
                    'items'       => CategoryUnusualItemList($house, 'i.class in (2,4) and i.quality > 2'),
                    'hiddenCols'  => ['avgprice' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'lowprice'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Dropped Uncommon Armor/Weapons',
                    'items'       => CategoryUnusualItemList($house, 'i.class in (2,4) and i.quality = 2'),
                    'hiddenCols'  => ['avgprice' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'lowprice'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Dropped Common/Junk Armor/Weapons',
                    'items'       => CategoryUnusualItemList($house, 'i.class in (2,4) and i.quality < 2'),
                    'hiddenCols'  => ['avgprice' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'lowprice'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Uncommon Recipes',
                    'items'       => CategoryUnusualItemList($house, 'i.class = 9 and i.quality > 1'),
                    'hiddenCols'  => ['avgprice' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'lowprice'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Common Recipes',
                    'items'       => CategoryUnusualItemList($house, 'i.class = 9 and i.quality <= 1'),
                    'hiddenCols'  => ['avgprice' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'lowprice'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Crafted Armor/Weapons',
                    'items'       => CategoryUnusualItemList($house, 'i.class in (2,4)', -1),
                    'hiddenCols'  => ['avgprice' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'lowprice'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Dropped Consumables',
                    'items'       => CategoryUnusualItemList($house, 'i.class = 0'),
                    'hiddenCols'  => ['avgprice' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'lowprice'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Trade Goods',
                    'items'       => CategoryUnusualItemList($house, 'i.class = 7'),
                    'hiddenCols'  => ['avgprice' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'lowprice'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Companion Deals',
                    'items'       => CategoryUnusualItemList($house, 'i.class = 15 and i.subclass in (2,5)'),
                    'hiddenCols'  => ['avgprice' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'lowprice'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Miscellaneous Items',
                    'items'       => CategoryUnusualItemList($house, '(i.class in (12,13) or (i.class=15 and i.subclass not in (2,5)))'),
                    'hiddenCols'  => ['avgprice' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'lowprice'
                ]
            ],
        ]
    ];
}

function CategoryResult_mining($house)
{
    return [
        'name'    => 'Mining',
        'results' => [
            [
                'name' => 'ItemList',
                'data' => ['name'  => 'Draenor Ore',
                           'items' => CategoryGenericItemList($house, 'i.id in (109119,109118)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Pandarian Ore',
                    'items' => CategoryGenericItemList($house, 'i.id in (72092,72093,72103,72094)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => ['name'  => 'Pandarian Bar',
                           'items' => CategoryGenericItemList($house, 'i.id in (72096,72095)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Cataclysm Ore',
                    'items' => CategoryGenericItemList($house, 'i.id in (52183,52185,53038)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Cataclysm Bar',
                    'items' => CategoryGenericItemList($house, 'i.id in (51950,53039,52186,54849)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Northrend Ore',
                    'items' => CategoryGenericItemList($house, 'i.id in (36912,36909,36910)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Northrend Bar',
                    'items' => CategoryGenericItemList($house, 'i.id in (36913,37663,41163,36916)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Outland Ore',
                    'items' => CategoryGenericItemList($house, 'i.id in (23424,23425,23426,23427)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Outland Bar',
                    'items' => CategoryGenericItemList($house, 'i.id in (23447,23449,35128,23446,23573,23445,23448)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Classic Ore',
                    'items' => CategoryGenericItemList($house, 'i.id in (7911,3858,10620,2772,2776,2771,2775,2770)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Classic Bar',
                    'items' => CategoryGenericItemList($house, 'i.id in (17771,12655,11371,12359,6037,3860,3859,3575,3577,2841,3576,2840,2842)')
                ]
            ],
        ]
    ];
}

function CategoryResult_skinning($house)
{
    global $expansions, $expansionLevels;

    $tr = ['name' => 'Skinning', 'results' => []];

    for ($x = count($expansions); $x--; $x >= 0) {
        $lsql = (($x > 0) ? (' i.level >' . (($x <= 2) ? '=' : '') . ' ' . $expansionLevels[$x - 1] . ' and ') : '') . ' i.level <' . (($x >= 3) ? '=' : '') . ' ' . $expansionLevels[$x];
        if ($x == 0) {
            $lsql .= ' or i.id in (17012,15414,15410,20381)';
        }
        if ($x == 1) {
            $lsql .= ' and i.id not in (17012,15414,15410,20381) or i.id = 25707';
        }
        if ($x == 2) {
            $lsql .= ' and i.id not in (25707,52977) or i.id = 38425';
        }
        if ($x == 3) {
            $lsql .= ' and i.id != 38425 or i.id = 52977';
        }
        $lsql = 'i.class=7 and i.subclass=6 and i.quality > 0 and (' . $lsql . ')';
        $tr['results'][] = [
            'name' => 'ItemList',
            'data' => [
                'name'  => $expansions[$x] . ' Leather',
                'items' => CategoryGenericItemList($house, $lsql)
            ]
        ];
    }

    return $tr;
}

function CategoryResult_herbalism($house)
{
    global $expansions, $expansionLevels;

    $tr = ['name' => 'Herbalism', 'results' => []];

    for ($x = count($expansions); $x--; $x >= 0) {
        $lsql = (($x > 0) ? (' i.level >' . (($x == 1) ? '=' : '') . ' ' . $expansionLevels[$x - 1] . ' and ') : '') . ' i.level <' . (($x > 0) ? '=' : '') . ' ' . $expansionLevels[$x];
        $lsql2 = '';
        $lsql3 = ' and i.id < 108318';
        if ($x == 0) {
            $lsql .= ' or i.id=13468';
        }
        if ($x == 1) {
            $lsql .= ' and i.id != 13468';
        }
        if ($x == 3) {
            $lsql .= ' and i.id < 70000';
        }
        if ($x == 4) {
            $lsql .= ' or i.id in (72234,72237)';
            $lsql2 = ' or i.id in (89639)';
        }
        if ($x == 5) {
            $lsql2 = ' or i.id in (109130)';
            $lsql3 = ' and i.id not in (109629, 109628, 109627, 109626, 109625, 109624)';
        }
        $lsql = '((i.class=7 and i.subclass=9 and i.quality in (1,2) and (' . $lsql . '))' . $lsql2 . ')' . $lsql3;
        $tr['results'][] = [
            'name' => 'ItemList',
            'data' => [
                'name'  => $expansions[$x] . ' Herbs',
                'items' => CategoryGenericItemList($house, $lsql)
            ]
        ];
    }

    return $tr;
}

function CategoryResult_alchemy($house)
{
    global $expansions, $expansionLevels;

    $tr = ['name' => 'Alchemy', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[count($expansions) - 1] . ' Flasks',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (SELECT distinct xic.id FROM tblDBCSpell xs JOIN tblDBCItem xic on xs.crafteditem=xic.id WHERE xs.skillline=171 and xic.level > ' . $expansionLevels[count($expansionLevels) - 2] . ' and xic.class=0 and xic.subclass=3) xyz on xyz.id = i.id'])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[count($expansions) - 1] . ' Restorative Potions',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (SELECT xx.id from (select xic.id, group_concat(se.description) dd FROM tblDBCSpell xs JOIN tblDBCItem xic on xs.crafteditem=xic.id LEFT JOIN tblDBCItemSpell dis on dis.item=xic.id LEFT JOIN tblDBCSpell se on se.id=dis.spell WHERE xs.skillline=171 and xic.level > ' . $expansionLevels[count($expansionLevels) - 2] . ' and xic.class=0 and xic.subclass=1 group by xic.id) xx where xx.dd like \'%restor%\') xyz on xyz.id = i.id'])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[count($expansions) - 1] . ' Buff Potions',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (SELECT xx.id from (select xic.id, group_concat(se.description) dd FROM tblDBCSpell xs JOIN tblDBCItem xic on xs.crafteditem=xic.id LEFT JOIN tblDBCItemSpell dis on dis.item=xic.id LEFT JOIN tblDBCSpell se on se.id=dis.spell WHERE xs.skillline=171 and xic.level > ' . $expansionLevels[count($expansionLevels) - 2] . ' and xic.class=0 and xic.subclass=1 group by xic.id) xx where xx.dd like \'%increas%\') xyz on xyz.id = i.id'])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[count($expansions) - 1] . ' Elixirs',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (SELECT distinct xic.id FROM tblDBCSpell xs JOIN tblDBCItem xic on xs.crafteditem=xic.id WHERE xs.skillline=171 and xic.level > ' . $expansionLevels[count($expansionLevels) - 2] . ' and xic.class=0 and xic.subclass=2) xyz on xyz.id = i.id'])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[count($expansions) - 1] . ' Transmutes',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (SELECT distinct xic.id FROM tblDBCSpell xs JOIN tblDBCItem xic on xs.crafteditem=xic.id WHERE xs.skillline=171 and xic.level > ' . $expansionLevels[count($expansionLevels) - 2] . ' and xic.class in (3,7)) xyz on xyz.id = i.id'])
        ]
    ];

    $sql = <<<EOF
join (
select xx.id from (
SELECT xic.id, group_concat(se.description) dd
FROM tblDBCSpell xs
JOIN tblDBCItem xic on xs.crafteditem=xic.id
LEFT JOIN tblDBCItemSpell dis on dis.item=xic.id
LEFT JOIN tblDBCSpell se on se.id=dis.spell
WHERE xs.skillline=171 and xic.class=0 and xic.subclass in (1,2)
and xic.name not like '%protection%'
group by xic.id) xx
where (xx.dd not like '%increas%' or (xx.dd like '%speed%' and xx.dd not like '%haste%'))
and xx.dd not like '%restor%'
and xx.dd not like '%heal%'
and xx.dd not like '%regenerate%'
) xyz on xyz.id = i.id
EOF;

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'General Purpose Elixirs and Potions',
            'items' => CategoryGenericItemList($house, ['joins' => $sql])
        ]
    ];

    return $tr;
}

function CategoryResult_leatherworking($house)
{
    global $expansions, $expansionLevels, $db;

    $tr = ['name' => 'Leatherworking', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Bags',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.class=1 and xs.skillline=165) xyz on xyz.id = i.id'])
        ]
    ];

    for ($x = (count($expansions) - 1); $x >= 0; $x--) {
        $tr['results'][] = [
            'name' => 'ItemList',
            'data' => [
                'name'  => $expansions[$x] . ' Armor Kits',
                'items' => CategoryGenericItemList($house, ['joins' => 'join (select x.id from tblDBCItem x, tblDBCSpell xs where xs.expansion=' . $x . ' and xs.crafteditem=x.id and x.class=0 and x.subclass=6 and xs.skillline=165) xyz on xyz.id = i.id'])
            ]
        ];
    }

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Cloaks',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.requiredlevel = ' . $expansionLevels[count($expansionLevels) - 1] . ' and x.class=4 and x.subclass=1 and x.type=16 and xs.skillline=165) xyz on xyz.id = i.id'])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Epic Leather',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.requiredlevel = ' . $expansionLevels[count($expansionLevels) - 1] . ' and x.quality=4 and x.class=4 and x.subclass=2 and xs.skillline=165) xyz on xyz.id = i.id'])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Epic Mail',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.requiredlevel = ' . $expansionLevels[count($expansionLevels) - 1] . ' and x.quality=4 and x.class=4 and x.subclass=3 and xs.skillline=165) xyz on xyz.id = i.id'])
        ]
    ];

    if (($pvpLevels = MCGet('category_leatherworking_pvplevels_' . $expansionLevels[count($expansionLevels) - 1])) === false) {
        DBConnect();
        $sql = <<<EOF
SELECT distinct i.level
FROM tblDBCItem i
JOIN tblDBCSpell s on s.crafteditem=i.id
WHERE i.quality=3 and i.class=4 and s.skillline=165 and i.requiredlevel=? and i.pvp > 0
order by 1 desc
EOF;

        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $expansionLevels[count($expansionLevels) - 1]);
        $stmt->execute();
        $result = $stmt->get_result();
        $pvpLevels = DBMapArray($result, null);
        $stmt->close();

        MCSet('category_leatherworking_pvplevels_' . $expansionLevels[count($expansionLevels) - 1], $pvpLevels, 24 * 60 * 60);
    }

    foreach ($pvpLevels as $itemLevel) {
        $tr['results'][] = [
            'name' => 'ItemList',
            'data' => [
                'name'  => 'PVP Rare ' . $itemLevel . ' Leather',
                'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.requiredlevel = ' . $expansionLevels[count($expansionLevels) - 1] . ' and x.level=' . $itemLevel . ' and x.quality=3 and x.class=4 and x.subclass=2 and xs.skillline=165 and x.pvp > 0) xyz on xyz.id = i.id'])
            ]
        ];
        $tr['results'][] = [
            'name' => 'ItemList',
            'data' => [
                'name'  => 'PVP Rare ' . $itemLevel . ' Mail',
                'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.requiredlevel = ' . $expansionLevels[count($expansionLevels) - 1] . ' and x.level=' . $itemLevel . ' and x.quality=3 and x.class=4 and x.subclass=3 and xs.skillline=165 and x.pvp > 0) xyz on xyz.id = i.id'])
            ]
        ];
    }

    return $tr;
}

function CategoryResult_blacksmithing($house)
{
    global $expansions, $expansionLevels, $db, $qualities;

    $tr = ['name' => 'Blacksmithing', 'results' => []];
    $sortIndex = 0;

    for ($x = 1; $x <= 3; $x++) {
        $idx = count($expansions) - $x;
        $nm = ($x == 3) ? 'Other' : $expansions[$idx];
        $tr['results'][] = [
            'name' => 'ItemList',
            'sort' => ['main' => $sortIndex++],
            'data' => [
                'name'  => $nm . ' Consumables',
                'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and xs.expansion' . ($x == 3 ? '<=' : '=') . $idx . ' and x.level>40 and x.class=0 and xs.skillline=164) xyz on xyz.id = i.id'])
            ]
        ];
    }

    $key = 'category_blacksmithing_levels_' . (count($expansionLevels) - 1);
    if (($armorLevels = MCGet($key)) === false) {
        DBConnect();

        $sql = <<<EOF
SELECT x.class, x.level, x.quality, sum( if(x.pvp, 1, 0) ) haspvp, sum( if(x.pvp, 0, 1) ) haspve
FROM tblDBCItem x, tblDBCSpell s
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
        $exp = count($expansionLevels) - 1;
        $stmt->bind_param('i', $exp);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = DBMapArray($result, null);
        $stmt->close();

        $armorLevels = ['PvE Armor' => [], 'PvP Armor' => [], 'Weapon' => []];
        foreach ($armorLevels as $k => $v) {
            for ($x = 4; $x >= 2; $x--) {
                $armorLevels[$k][$x] = array();
            }
        }

        foreach ($rows as $row) {
            if ($row['class'] == 2) {
                if (!in_array($row['level'], $armorLevels['Weapon'][$row['quality']])) {
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

        MCSet($key, $armorLevels, 24 * 60 * 60);
    }

    foreach ($armorLevels as $armorName => $armorQualities) {
        $classId = ($armorName == 'Weapon') ? 2 : 4;
        foreach ($armorQualities as $q => $levels) {
            foreach ($levels as $level) {
                $tr['results'][] = [
                    'name' => 'ItemList',
                    'sort' => [
                        'main'    => $sortIndex,
                        'level'   => $level,
                        'quality' => $q,
                        'name'    => $armorName
                    ],
                    'data' => [
                        'name'  => $qualities[$q] . ' ' . $level . ' ' . $armorName,
                        'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCItemReagents xir where xir.item=x.id and ifnull(x.pvp,0) ' . (($armorName == 'PvP Armor') ? '>' : '=') . '0 and x.level=' . $level . ' and x.class=' . $classId . ' and xir.skillline=164 and x.quality=' . $q . ') xyz on xyz.id = i.id'])
                    ]
                ];
            }
        }
    }
    $sortIndex++;

    usort(
        $tr['results'], function ($a, $b) {
            if ($a['sort']['main'] != $b['sort']['main']) {
                return $a['sort']['main'] - $b['sort']['main'];
            }
            if ($a['sort']['level'] != $b['sort']['level']) {
                return $b['sort']['level'] - $a['sort']['level'];
            }
            if ($a['sort']['quality'] != $b['sort']['quality']) {
                return $b['sort']['quality'] - $a['sort']['quality'];
            }
            return strcmp($a['sort']['name'], $b['sort']['name']);
        }
    );

    return $tr;
}

function CategoryResult_engineering($house)
{
    global $expansions, $expansionLevels;

    $tr = ['name' => 'Engineering', 'results' => []];

    $exp = $expansions[count($expansions) - 1];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $exp . ' Weapons and Armor',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and xs.expansion=' . (count($expansions) - 1) . ' and x.level>40 and (x.class=2 or (x.class=4 and x.subclass>0)) and xs.skillline=202) xyz on xyz.id = i.id'])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Ranged Enchants (Scopes)',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (SELECT xx.id from (select x.id, group_concat(se.description) dd from tblDBCItem x join tblDBCSpell xs on xs.crafteditem=x.id LEFT JOIN tblDBCItemSpell dis on dis.item=x.id LEFT JOIN tblDBCSpell se on se.id=dis.spell where x.level>40 and xs.skillline=202 group by x.id) xx where (xx.dd like \'%bow or gun%\' or xx.dd like \'%ranged weapon%\')) xyz on xyz.id = i.id'])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Companions',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.class=15 and x.subclass=2 and xs.skillline=202) xyz on xyz.id = i.id'])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Mounts',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.class=15 and x.subclass=5 and xs.skillline=202) xyz on xyz.id = i.id'])
        ]
    ];

    $sql = <<<EOF
join (SELECT xx.id from (
    select x.id, group_concat(se.description) dd
    from tblDBCItem x
    join tblDBCSpell xs on xs.crafteditem=x.id
    LEFT JOIN tblDBCItemSpell dis on dis.item=x.id
    LEFT JOIN tblDBCSpell se on se.id=dis.spell
    where
    ifnull(x.requiredskill,0) != 202
    and (
        (x.class=4 and (x.subclass not in (1,2,3,4) or x.level < 10))
        or (x.class in (0,7) and x.id not in (
            select reagent
            from tblDBCItemReagents xir2
            where xir2.reagent=x.id))
        )
    and xs.skillline=202
    group by x.id
    ) xx
    where xx.dd not like '%bow or gun%'
    and xx.dd not like '%ranged weapon%'
) xyz on xyz.id = i.id
EOF;

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Non-Engineer Items',
            'items' => CategoryGenericItemList($house, ['joins' => $sql])
        ]
    ];

    return $tr;
}

function CategoryResult_tailoring($house)
{
    global $expansions, $expansionLevels, $db;

    $tr = ['name' => 'Tailoring', 'results' => []];


    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Common Cloth',
            'items' => CategoryGenericItemList($house, 'i.id in (2589,2592,4306,4338,14047,21877,33470,53010,72988,111557)')
        ]
    ];

    $x = count($expansions);
    $x--;
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$x] . ' Bags',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.level between ' . $expansionLevels[$x - 1] . '+1 and ' . $expansionLevels[$x] . ' and x.class=1 and x.subclass=0 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];
    $x--;
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$x] . ' Bags',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.level between ' . $expansionLevels[$x - 1] . '+1 and ' . $expansionLevels[$x] . ' and x.class=1 and x.subclass=0 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];
    $x--;
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Other Bags',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.level<=' . $expansionLevels[$x] . ' and x.level>40 and x.class=1 and x.subclass=0 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];

    $x = count($expansions);
    $x--;
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$x] . ' Profession Bags',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.level between ' . $expansionLevels[$x - 1] . '+1 and ' . $expansionLevels[$x] . ' and x.class=1 and x.subclass!=0 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];
    $x--;
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$x] . ' Profession Bags',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.level between ' . $expansionLevels[$x - 1] . '+1 and ' . $expansionLevels[$x] . ' and x.class=1 and x.subclass!=0 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];
    $x--;
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Other Profession Bags',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.level<=' . $expansionLevels[$x] . ' and x.level>40 and x.class=1 and x.subclass!=0 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];

    $x = count($expansions);
    $x--;
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$x] . ' Spellthread',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and xs.expansion=' . $x . ' and x.class=0 and x.subclass=6 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];
    $x--;
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$x] . ' Spellthread',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and xs.expansion=' . $x . ' and x.class=0 and x.subclass=6 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];
    $x--;
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Other Spellthread',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and xs.expansion<=' . $x . ' and x.class=0 and x.subclass=6 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];

    if (($pvpLevels = MCGet('category_tailoring_pvplevels_' . $expansionLevels[count($expansionLevels) - 1])) === false) {
        DBConnect();
        $sql = <<<EOF
SELECT distinct i.level
FROM tblDBCItem i
JOIN tblDBCSpell s on s.crafteditem=i.id
WHERE i.quality=3 and i.class=4 and s.skillline=197 and i.requiredlevel=? and i.pvp > 0
order by 1 desc
EOF;

        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $expansionLevels[count($expansionLevels) - 1]);
        $stmt->execute();
        $result = $stmt->get_result();
        $pvpLevels = DBMapArray($result, null);
        $stmt->close();

        MCSet('category_tailoring_pvplevels_' . $expansionLevels[count($expansionLevels) - 1], $pvpLevels, 24 * 60 * 60);
    }

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Epic Armor',
            'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.requiredlevel = ' . $expansionLevels[count($expansionLevels) - 1] . ' and x.quality=4 and x.class=4 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];

    foreach ($pvpLevels as $pvpLevel) {
        $tr['results'][] = [
            'name' => 'ItemList',
            'data' => [
                'name'  => 'PVP ' . $pvpLevel . ' Rare Armor',
                'items' => CategoryGenericItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x, tblDBCSpell xs where xs.crafteditem=x.id and x.requiredlevel = ' . $expansionLevels[count($expansionLevels) - 1] . ' and x.level = ' . $pvpLevel . ' and x.quality=3 and x.class=4 and xs.skillline=197 and x.pvp > 0) xyz on xyz.id = i.id'])
            ]
        ];
    }

    return $tr;
}

function CategoryResult_enchanting($house)
{
    global $expansions;

    $tr = ['name' => 'Enchanting', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Dust',
            'items' => CategoryGenericItemList($house, 'i.class=7 and i.subclass=12 and i.quality=1 and i.name like \'%Dust\'')
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Essence',
            'items' => CategoryGenericItemList($house, 'i.class=7 and i.subclass=12 and i.quality=2 and ((i.level>85 and i.name like \'%Essence\') or (i.name like \'Greater%Essence\'))')
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Shard',
            'items' => CategoryGenericItemList($house, 'i.class=7 and i.subclass=12 and i.quality=3 and i.name not like \'Small%\' and i.name like \'%Shard\'')
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Crystal',
            'items' => CategoryGenericItemList($house, 'i.class=7 and i.subclass=12 and i.quality=4 and i.name like \'%Crystal\'')
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[count($expansions) - 1] . ' Enchanted Scrolls',
            'items' => CategoryGenericItemList($house, 'i.class=0 and i.subclass=6 and i.id in (select ir.crafteditem from tblDBCSpell ir where ir.skillline=333 and ir.expansion=' . (count($expansions) - 1) . ')')
        ]
    ];

    return $tr;
    //$pagexml .= topitems('iid in (select iid from (select vic.iid from undermine.tblItemCache vic, (select distinct crafteditem as itemid from undermine.tblSpell ir where ir.skilllineid=333) iids where vic.class=0 and  vic.subclass=6 and  vic.iid=iids.itemid order by (undermine.getMarketPrice(\''.sql_esc($realmid).'\',vic.iid,null,null) - undermine.getReagentPrice(\''.sql_esc($realmid).'\',vic.iid,null)) desc limit 15) alias)','Profitable Scrolls');
}

function CategoryResult_inscription($house)
{
    global $expansions;

    $tr = ['name' => 'Inscription', 'results' => []];

    $x = count($expansions) - 1;
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$x] . ' Decks',
            'items' => CategoryGenericItemList($house, ['where' => 'i.id between 112303 and 112306'])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$x] . ' Iron Deck Cards',
            'items' => CategoryGenericItemList($house, ['where' => 'i.id between 112271 and 112278'])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$x] . ' Moon Deck Cards',
            'items' => CategoryGenericItemList($house, ['where' => 'i.id between 112295 and 112302'])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$x] . ' Visions Deck Cards',
            'items' => CategoryGenericItemList($house, ['where' => 'i.id between 112279 and 112286'])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$x] . ' War Deck Cards',
            'items' => CategoryGenericItemList($house, ['where' => 'i.id between 112287 and 112294'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Shoulder Inscription',
            'items' => CategoryGenericItemList(
                $house, [
                    'joins' => 'join tblDBCSpell xs on xs.crafteditem = i.id',
                    'where' => 'xs.skillline = 773 and xs.expansion=' . (count($expansions) - 1) . ' and i.class = 0 and i.quality > 1'
                ]
            )
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Crafted Weapons',
            'items' => CategoryGenericItemList(
                $house, [
                    'joins' => 'join tblDBCSpell xs on xs.crafteditem = i.id',
                    'where' => 'xs.skillline = 773 and xs.expansion=' . (count($expansions) - 1) . ' and i.class = 2'
                ]
            )
        ]
    ];

    for ($y = 0; $y <= 1; $y++) {
        $x = count($expansions) - 1 - $y;
        $tr['results'][] = [
            'name' => 'ItemList',
            'data' => [
                'name'  => $expansions[$x] . ' Crafted Armor',
                'items' => CategoryGenericItemList(
                    $house, [
                        'joins' => 'join tblDBCSpell xs on xs.crafteditem = i.id',
                        'where' => 'xs.skillline = 773 and xs.expansion=' . $x . ' and i.level > 40 and i.class = 4'
                    ]
                )
            ]
        ];
    }

    $x--;
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Other Crafted Armor',
            'items' => CategoryGenericItemList(
                $house, [
                    'joins' => 'join tblDBCSpell xs on xs.crafteditem = i.id',
                    'where' => 'xs.skillline = 773 and xs.expansion<=' . $x . ' and i.level > 40 and i.class = 4'
                ]
            )
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Crafted Consumable',
            'items' => CategoryGenericItemList(
                $house, [
                    'joins' => 'join tblDBCSpell xs on xs.crafteditem = i.id',
                    'where' => 'xs.skillline = 773 and ((xs.expansion=' . (count($expansions) - 1) . ' and i.class = 0 and i.quality=1) or (i.id in (60838,43850)))'
                ]
            )
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Common Ink',
            'items' => CategoryGenericItemList(
                $house, [
                    'joins' => 'join tblDBCSpell xs on xs.crafteditem = i.id',
                    'where' => 'xs.skillline = 773 and i.class=7 and i.subclass=1 and i.quality=1'
                ]
            )
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Uncommon Ink',
            'items' => CategoryGenericItemList(
                $house, [
                    'joins' => 'join tblDBCSpell xs on xs.crafteditem = i.id',
                    'where' => 'xs.skillline = 773 and i.class=7 and i.subclass=1 and i.quality>1'
                ]
            )
        ]
    ];

    return $tr;
}

function CategoryResult_cooking($house)
{
    global $expansions, $db;

    $tr = ['name' => 'Cooking', 'results' => []];

    $cexp = count($expansions) - 1;
    $fish = '74856,74857,74859,74860,74861,74863,74864,74865,74866,83064';
    $farmed = '74840,74841,74842,74843,74844,74845,74846,74847,74848,74849,74850';
    $ironpaw = '74853,74661,74662';

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'From the Farm',
            'items' => CategoryGenericItemList($house, "i.id in ($farmed)")
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Raw Pandaria Fish',
            'items' => CategoryGenericItemList($house, "i.id in ($fish)")
        ]
    ];

    $sql = <<<EOF
select distinct x.id
from tblDBCItem x, tblDBCItemReagents xir, tblDBCSkillLines xsl, tblDBCSpell xs
where x.class=7
and x.id=xir.reagent
and x.id not in (select xx.item from tblDBCItemVendorCost xx where xx.item=x.id)
and xir.spell=xs.id
and xs.skillline=xsl.id
and (xsl.name like 'Way of %' or xsl.name='Cooking')
and xs.expansion=$cexp
and x.id not in ($fish,$farmed,$ironpaw)
EOF;

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Raw Meat and Miscellaneous',
            'items' => CategoryGenericItemList($house, ['joins' => "join ($sql) xyz on xyz.id = i.id"])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Ironpaw Token Ingredients',
            'items' => CategoryGenericItemList($house, "i.id in ($ironpaw)")
        ]
    ];

    $ways = MCGet('category_cooking_ways');
    if ($ways === false) {
        $stmt = $db->prepare('SELECT * FROM tblDBCSkillLines WHERE name LIKE \'Way of%\' ORDER BY name ASC');
        if (!$stmt) {
            DebugMessage("Bad SQL: \n" . $sql, E_USER_ERROR);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $ways = DBMapArray($result, null);
        $stmt->close();
        MCSet('category_cooking_ways', $ways);
    }

    foreach ($ways as $row) {
        $tr['results'][] = [
            'name' => 'ItemList',
            'data' => [
                'name'  => $row['name'] . ' Food',
                'items' => CategoryGenericItemList($house, ['joins' => "join (select x.crafteditem from tblDBCSpell x where x.skillline=" . $row['id'] . ") xyz on xyz.crafteditem=i.id"])
            ]
        ];
    }

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Other Cooked Food',
            'items' => CategoryGenericItemList($house, ['joins' => "join (select x.crafteditem from tblDBCSpell x where x.skillline=185 and expansion=$cexp) xyz on xyz.crafteditem=i.id"])
        ]
    ];

    return $tr;
}

function CategoryResult_fishing($house)
{
    // flesh, small, regular, enormous
    $fish = [
        [109143, 111659, 111664, 111671], // abyssal gulper eel
        [109144, 111662, 111663, 111670], // blackwater whiptail
        [109140, 111652, 111667, 111674], // blind lake sturgeon
        [109137, 111589, 111595, 111601], // crescent saberfish
        [109139, 111651, 111668, 111675], // fat sleeper
        [109141, 111656, 111666, 111673], // fire ammonite
        [109138, 111650, 111669, 111676], // jawless skulker
        [109142, 111658, 111665, 111672], // sea scorpion
        //[118512, 118564, 118565, 118566], // savage piranha
    ];

    $tr = ['name' => 'Fishing', 'results' => []];

    $fishIds = [];
    foreach ($fish as $f) {
        $fishIds = array_merge($fishIds, $f);
    }
    sort($fishIds);
    $fishPricesList = CategoryGenericItemList($house, 'i.id in (' . implode(',', $fishIds) . ')');
    $fishPrices = [];
    foreach ($fishPricesList as $p) {
        $fishPrices[$p['id']] = $p;
    }

    $tr['results'][] = [
        'name' => 'FishTable',
        'data' => ['name' => 'Draenor Fish', 'fish' => $fish, 'prices' => $fishPrices]
    ];

    return $tr;
}

function CategoryResult_companions($house)
{
    $tr = ['name' => 'Companions', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Mounts',
            'items' => CategoryGenericItemList($house, "i.class=15 and i.subclass=5")
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Companion Items',
            'items' => CategoryGenericItemList($house, "i.class=15 and i.subclass=2")
        ]
    ];

    return $tr;
}

function CategoryGenericItemList($house, $params)
{
    global $db, $canCache;

    $key = 'category_gib2_' . md5(json_encode($params));

    if ($canCache && (($tr = MCGetHouse($house, $key)) !== false)) {
        return $tr;
    }

    DBConnect();

    if (is_array($params)) {
        $joins = isset($params['joins']) ? $params['joins'] : '';
        $where = isset($params['where']) ? (' and ' . $params['where']) : '';
        $cols = isset($params['cols']) ? (', ' . $params['cols']) : '';
    } else {
        $joins = '';
        $where = ($params == '') ? '' : (' and ' . $params);
        $cols = '';
    }

    $sql = <<<EOF
select results.*,
ifnull(GROUP_CONCAT(bs.`bonus` ORDER BY 1 SEPARATOR ':'), '') bonusurl,
ifnull(group_concat(distinct ib.`tag` order by ib.tagpriority separator ' '), if(results.bonusset=0,'',concat('Level ', results.level+sum(ifnull(ib.level,0))))) bonustag
from (
    select i.id, i.name, i.quality, i.icon, i.class as classid, s.price, s.quantity, unix_timestamp(s.lastseen) lastseen, round(avg(h.price)) avgprice, s.age, round(avg(h.age)) avgage,
    ifnull(s.bonusset,0) bonusset, i.level `level` $cols
    from tblDBCItem i
    left join tblItemSummary s on s.house=? and s.item=i.id
    left join tblItemHistory h on h.house=? and h.item=i.id and h.bonusset = s.bonusset
    join tblItemGlobal g on g.item=i.id+0 and g.bonusset = ifnull(s.bonusset,0)
    $joins
    where ifnull(i.auctionable,1) = 1
    $where
    group by i.id, ifnull(s.bonusset,0)
) results
left join tblBonusSet bs on results.bonusset = bs.`set`
left join tblDBCItemBonus ib on bs.bonus = ib.id
group by results.id, results.bonusset
EOF;

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        DebugMessage("Bad SQL: \n" . $sql, E_USER_ERROR);
    }
    $stmt->bind_param('ii', $house, $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function CategoryDealsItemList($house, $dealsSql, $allowCrafted = 0)
{
    /* $allowCrafted
        0 = no crafted items
        1 = crafted and drops
        -1 = crafted only
    */

    $genArray = [
        'cols' => 'g.median globalmedian', // , g.mean globalmean, g.stddev globalstddev
    ];

    global $db, $canCache;

    $key = 'category_dib_' . md5($dealsSql) . '_' . $allowCrafted;

    if ($canCache && (($tr = MCGetHouse($house, $key)) !== false)) {
        if (count($tr) == 0) {
            return [];
        }

        $sql = 'i.id in (' . implode(',', $tr) . ')';

        $iids = array_flip($tr);

        $tr = CategoryGenericItemList($house, array_merge($genArray, ['where' => $sql]));

        usort(
            $tr, function ($a, $b) use ($iids) {
                return $iids[$a['id']] - $iids[$b['id']];
            }
        );

        return $tr;
    }

    DBConnect();

    $region = GetRegion($house);

    $fullSql = <<<EOF
select aa.item, aa.bonusset
from (
    select ac.item, ac.bonusset, ac.c_total, ac.c_over, ac.price, gs.median
    from (
        select ab.item, ab.bonusset, count(*) c_total, sum(if(tis2.price > ab.price,1,0)) c_over, ab.price
        from (
            select tis.item, tis.bonusset, tis.price
            from tblItemSummary tis
            join tblDBCItem i on tis.item=i.id
            where tis.house = ?
            and tis.quantity > 0
            and 0 = (select count(*) from tblDBCItemVendorCost ivc where ivc.item=i.id)
            and i.class not in (16)
            and $dealsSql
EOF;
    switch ($allowCrafted) {
        case '0':
            $fullSql .= ' and 0 = (select count(*) from tblDBCSpell s where s.crafteditem=i.id) ';
            break;
        case '-1' :
            $fullSql .= ' and 0 < (select count(*) from tblDBCSpell s where s.crafteditem=i.id) ';
            break;
    }
    $fullSql .= <<<EOF
        ) ab
        join tblItemSummary tis2 on tis2.item = ab.item and tis2.bonusset = ab.bonusset
        join tblRealm r on r.house = tis2.house and r.canonical is not null
        where r.region = ?
        group by ab.item, ab.bonusset
    ) ac
    join tblItemGlobal gs on gs.item = ac.item and gs.bonusset = ac.bonusset
    where ((c_over/c_total) > 2/3 or c_total < 15)
) aa
where median > 1500000
and median > price
order by (cast(median as signed) - cast(price as signed))/median * (c_over/c_total) desc
limit 15
EOF;

    $stmt = $db->prepare($fullSql);
    if (!$stmt) {
        DebugMessage("Bad SQL: \n" . $fullSql, E_USER_ERROR);
    }
    $stmt->bind_param('is', $house, $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $iidList = DBMapArray($result, null);
    $stmt->close();

    MCSetHouse($house, $key, $iidList);

    if (count($iidList) == 0) {
        return array();
    }

    $sortBy = [];
    $sql = '(';
    foreach ($iidList as $row) {
        $sql .= (strlen($sql) == 1 ? '' : ' or ') . '(i.id = ' . $row['item'] . ' and s.bonusset = ' . $row['bonusset'] . ')';
        $sortBy[] = $row['item'].':'.$row['bonusset'];
    }
    $sql .= ')';

    $sortBy = array_flip($sortBy);

    $tr = CategoryGenericItemList($house, array_merge($genArray, ['where' => $sql]));

    usort(
        $tr, function ($a, $b) use ($sortBy) {
            return $sortBy[$a['id'].':'.$a['bonusset']] - $sortBy[$b['id'].':'.$b['bonusset']];
        }
    );

    return $tr;
}

function CategoryUnusualItemList($house, $unusualSql, $allowCrafted = 0)
{
    /* $allowCrafted
        0 = no crafted items
        1 = crafted and drops
        -1 = crafted only
    */

    $craftedSql = '';
    switch ($allowCrafted) {
        case '0':
            $craftedSql .= ' and 0 = (select count(*) from tblDBCSpell s where s.crafteditem=i.id) ';
            break;
        case '-1' :
            $craftedSql .= ' and 0 < (select count(*) from tblDBCSpell s where s.crafteditem=i.id) ';
            break;
    }

    $params = [
        'where' => $unusualSql . $craftedSql . ' and s.bonusset=0',
        'joins' => 'join tblAuction a on a.house=s.house and a.item=i.id join tblAuctionRare ar on ar.house=a.house and ar.id=a.id',
        'cols'  => 'g.median globalmedian, min(ar.prevseen) `lastseen`, min(ifnull(a.buy/a.quantity, a.bid/a.quantity)) `price`',
    ];

    return CategoryGenericItemList($house, $params);
}
