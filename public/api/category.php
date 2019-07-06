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

define('CATEGORY_FLAGS_ALLOW_CRAFTED', 1);
define('CATEGORY_FLAGS_DENY_NONCRAFTED', 2);
define('CATEGORY_FLAGS_WITH_BONUSES', 4);

define('ABSENT_TRANSMOG_SQL', '
i.id in (
    select z.id from (
        select x.id, ifnull(sum(xis.quantity), 0) itmcount
        from tblDBCItem as x
        join tblDBCSpellCrafts xsc on xsc.item = x.id
        join tblDBCSpell xs on xs.id = xsc.spell
        join tblDBCItem xai on xai.display = x.display and xai.class = x.class
        left join tblItemSummary xis on xis.item = xai.id and xis.house = %d
        where xs.skillline = %d
        and x.auctionable = 1
        and x.quality > 1
        and x.display is not null
        and x.flags & 2 = 0
        and x.class in (2, 4)
        group by x.id) z
    where z.itmcount = 0
)');

if ($canCache) {
    HouseETag($house);
} else {
    header('Cache-Control: no-cache');
}
ConcurrentRequestThrottle();
BotCheck();

$expansionLevels = [60, 70, 80, 85, 90, 100, 110, 120];
$expansions = [
    'Classic',
    'Burning Crusade',
    'Wrath of the Lich King',
    'Cataclysm',
    'Mists of Pandaria',
    'Warlords of Draenor',
    'Legion',
    'Battle for Azeroth',
];
$qualities = array('Poor', 'Common', 'Uncommon', 'Rare', 'Epic', 'Legendary', 'Artifact', 'Heirloom');

json_return($resultFunc($house));

function CategoryResult_battlepets($house)
{
    global $canCache;

    $cacheKey = 'category_bpets2';

    if ($canCache && (($tr = MCGetHouse($house, $cacheKey)) !== false)) {
        foreach ($tr as &$species) {
            PopulateLocaleCols($species, [['func' => 'GetPetNames', 'key' => 'species', 'name' => 'name']]);
        }
        unset($species);
        return ['name' => 'battlepets', 'results' => [['name' => 'BattlePetList', 'data' => $tr]]];
    }

    $db = DBConnect();

    $sql = <<<EOF
SELECT ps.species, ps.price, ps.quantity, ps.lastseen,
(select round(avg(case hours.h
    when  0 then ph.silver00 when  1 then ph.silver01 when  2 then ph.silver02 when  3 then ph.silver03
    when  4 then ph.silver04 when  5 then ph.silver05 when  6 then ph.silver06 when  7 then ph.silver07
    when  8 then ph.silver08 when  9 then ph.silver09 when 10 then ph.silver10 when 11 then ph.silver11
    when 12 then ph.silver12 when 13 then ph.silver13 when 14 then ph.silver14 when 15 then ph.silver15
    when 16 then ph.silver16 when 17 then ph.silver17 when 18 then ph.silver18 when 19 then ph.silver19
    when 20 then ph.silver20 when 21 then ph.silver21 when 22 then ph.silver22 when 23 then ph.silver23
    else null end)*100)
    from tblPetHistoryHourly ph,
    (select  0 h union select  1 h union select  2 h union select  3 h union
     select  4 h union select  5 h union select  6 h union select  7 h union
     select  8 h union select  9 h union select 10 h union select 11 h union
     select 12 h union select 13 h union select 14 h union select 15 h union
     select 16 h union select 17 h union select 18 h union select 19 h union
     select 20 h union select 21 h union select 22 h union select 23 h) hours
    where ph.house = ps.house and ph.species = ps.species) avgprice,
p.type, p.icon, p.npc, 0 regionavgprice
FROM tblPetSummary ps
JOIN tblDBCPet p on ps.species=p.id
WHERE ps.house = ?
EOF;

    $stmt = $db->stmt_init();
    if (!$stmt->prepare($sql)) {
        DebugMessage("Bad SQL: \n" . $sql, E_USER_ERROR);
    }
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    if (($result === false) && ($errMsg = $db->error)) {
        DebugMessage("No result: $errMsg\n" . $sql, E_USER_ERROR);
    }
    $tr = DBMapArray($result, array('type', 'species'));
    $stmt->close();

    $regional = CategoryBattlePetRegion(GetRegion($house));
    foreach ($tr as &$allSpecies) {
        foreach ($allSpecies as $species => &$row) {
            if (isset($regional[$species])) {
                $row['regionavgprice'] = $regional[$species]['price'];
            }
        }
        unset($row);
    }
    unset($allSpecies);

    MCSetHouse($house, $cacheKey, $tr);

    foreach ($tr as &$species) {
        PopulateLocaleCols($species, [['func' => 'GetPetNames', 'key' => 'species', 'name' => 'name']]);
    }
    unset($species);
    return ['name' => 'battlepets', 'results' => [['name' => 'BattlePetList', 'data' => $tr]]];
}

function CategoryBattlePetRegion($region)
{
    global $canCache;

    $cacheKey = 'category_bpets2_regional_'.$region;
    if ($canCache && (($tr = MCGet($cacheKey)) !== false)) {
        return $tr;
    }

    $db = DBConnect();

    $sql = <<<EOF
select i.species, round(avg(i.price)) price
from `tblPetSummary` i
join `tblRealm` r on i.house = r.house and r.region = ?
group by i.species
EOF;

    $stmt = $db->stmt_init();
    if (!$stmt->prepare($sql)) {
        DebugMessage("Bad SQL: \n" . $sql, E_USER_ERROR);
    }
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    if (($result === false) && ($errMsg = $db->error)) {
        DebugMessage("No result: $errMsg\n" . $sql, E_USER_ERROR);
    }
    $tr = DBMapArray($result, array('species'));
    $stmt->close();

    MCSet($cacheKey, $tr, 60*60*6);

    return $tr;
}

function CategoryResult_custom($house) {
    if (!isset($_POST['items'])) {
        return [];
    }

    $maxId = (1<<24) - 1;

    $items = [];
    $c = min(250, preg_match_all('/\d+/', $_POST['items'], $res));
    for ($x = 0; $x < $c; $x++) {
        $id = intval($res[0][$x]);
        if ($id > $maxId) {
            continue;
        }
        $items[$id] = true;
    }
    if (!count($items)) {
        return [];
    }
    $items = array_keys($items);
    sort($items);

    $rawResult = CategoryRegularItemList($house, 'i.id in (' . implode(',', $items) . ')');

    $classLookup = [];
    $tr = ['name' => 'custom', 'results' => []];
    while ($item = array_pop($rawResult)) {
        if (!isset($classLookup[$item['classid']])) {
            $classLookup[$item['classid']] = count($tr['results']);
            $tr['results'][$classLookup[$item['classid']]] = [
                'name' => 'ItemList',
                'data' => [
                    'name' => 'itemClasses.' . $item['classid'],
                    'dynamicItems' => 1,
                    'items' => []
                ]
            ];
        }
        $tr['results'][$classLookup[$item['classid']]]['data']['items'][] = $item;
    }

    return $tr;
}

function CategoryResult_deals($house)
{
    $deckCardsSql = <<<'EOF'
(i.name_enus like '% of %' and
i.id in (
    select dis.item
    from tblDBCItemSpell dis
    join tblDBCSpell s on dis.spell = s.id
    where s.name like '% Deck')
)
EOF;

    $tr = [
        'name'    => 'deals',
        'results' => [
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Dropped Rare and Epic Armor/Weapons',
                    'items'       => CategoryDealsItemList($house, 'i.class in (2,4) and i.quality > 2', CATEGORY_FLAGS_WITH_BONUSES),
                    'dynamicItems' => 1,
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true, 'posted' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Dropped Uncommon Armor/Weapons',
                    'items'       => CategoryDealsItemList($house, 'i.class in (2,4) and i.quality = 2', CATEGORY_FLAGS_WITH_BONUSES),
                    'dynamicItems' => 1,
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true, 'posted' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Crafted Armor/Weapons',
                    'items'       => CategoryDealsItemList($house, 'i.class in (2,4)', CATEGORY_FLAGS_ALLOW_CRAFTED | CATEGORY_FLAGS_DENY_NONCRAFTED | CATEGORY_FLAGS_WITH_BONUSES),
                    'dynamicItems' => 1,
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true, 'posted' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Armor/Weapons with Bonuses',
                    'items'       => CategoryDealsItemList($house, 'i.class in (2,4) and tis.level != i.level', CATEGORY_FLAGS_ALLOW_CRAFTED | CATEGORY_FLAGS_WITH_BONUSES),
                    'dynamicItems' => 1,
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true, 'posted' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Dropped Common/Junk Armor/Weapons',
                    'items'       => CategoryDealsItemList($house, 'i.class in (2,4) and i.quality < 2', CATEGORY_FLAGS_WITH_BONUSES),
                    'dynamicItems' => 1,
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true, 'posted' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Uncommon Recipes',
                    'items'       => CategoryDealsItemList($house, 'i.class = 9 and i.quality > 1'),
                    'dynamicItems' => 1,
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true, 'posted' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Common Recipes',
                    'items'       => CategoryDealsItemList($house, 'i.class = 9 and i.quality <= 1'),
                    'dynamicItems' => 1,
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true, 'posted' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Dropped Consumables',
                    'items'       => CategoryDealsItemList($house, 'i.class = 0'),
                    'dynamicItems' => 1,
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true, 'posted' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Trade Goods',
                    'items'       => CategoryDealsItemList($house, 'i.class = 7'),
                    'dynamicItems' => 1,
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true, 'posted' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Companion Deals',
                    'items'       => CategoryDealsItemList($house, 'i.class = 15 and i.subclass in (2,5)'),
                    'dynamicItems' => 1,
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true, 'posted' => true],
                    'sort'        => 'none'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Miscellaneous Items',
                    'items'       => CategoryDealsItemList($house, '(i.class in (12,13) or (i.class=15 and i.subclass not in (2,5))) and not ' . $deckCardsSql),
                    'dynamicItems' => 1,
                    'hiddenCols'  => ['lastseen' => true],
                    'visibleCols' => ['globalmedian' => true, 'posted' => true],
                    'sort'        => 'none'
                ]
            ],
        ]
    ];

    return $tr;
}

function CategoryResult_lowbids($house)
{

    $tr = [
        'name'    => 'potentialLowBids',
        'results' => [],
    ];

    $joins = <<<EOF
join (
select item, bidper as bid
from (
    select ib.item, ib.bidper, avg(case hours.h
        when  0 then ihh.silver00 when  1 then ihh.silver01 when  2 then ihh.silver02 when  3 then ihh.silver03
        when  4 then ihh.silver04 when  5 then ihh.silver05 when  6 then ihh.silver06 when  7 then ihh.silver07
        when  8 then ihh.silver08 when  9 then ihh.silver09 when 10 then ihh.silver10 when 11 then ihh.silver11
        when 12 then ihh.silver12 when 13 then ihh.silver13 when 14 then ihh.silver14 when 15 then ihh.silver15
        when 16 then ihh.silver16 when 17 then ihh.silver17 when 18 then ihh.silver18 when 19 then ihh.silver19
        when 20 then ihh.silver20 when 21 then ihh.silver21 when 22 then ihh.silver22 when 23 then ihh.silver23
        else null end) * 100 avgprice,
        stddev_pop(
        case hours.h
        when  0 then ihh.silver00 when  1 then ihh.silver01 when  2 then ihh.silver02 when  3 then ihh.silver03
        when  4 then ihh.silver04 when  5 then ihh.silver05 when  6 then ihh.silver06 when  7 then ihh.silver07
        when  8 then ihh.silver08 when  9 then ihh.silver09 when 10 then ihh.silver10 when 11 then ihh.silver11
        when 12 then ihh.silver12 when 13 then ihh.silver13 when 14 then ihh.silver14 when 15 then ihh.silver15
        when 16 then ihh.silver16 when 17 then ihh.silver17 when 18 then ihh.silver18 when 19 then ihh.silver19
        when 20 then ihh.silver20 when 21 then ihh.silver21 when 22 then ihh.silver22 when 23 then ihh.silver23
        else null end) * 100 sdprice
    from (
        select i.id as item, ifnull(ae.level, if(i.class in (2,4), i.level, 0)) level, min(a.bid/a.quantity) bidper
        from tblAuction a use index (item)
        left join tblAuctionExtra ae on ae.house = a.house and ae.id = a.id
        join tblDBCItem i on i.id=a.item
        left join tblDBCItemVendorCost ivc on ivc.item=i.id
        where a.house=%1\$d
        and i.`class` %2\$s in (2,4)
        and i.quality > 0
        and ivc.copper is null
        group by i.id) ib
    join tblItemHistoryHourly ihh on ihh.house=%1\$d and ihh.item = ib.item and ihh.level = ib.level
    join (select 0 h union select  1 h union select  2 h union select  3 h union
         select  4 h union select  5 h union select  6 h union select  7 h union
         select  8 h union select  9 h union select 10 h union select 11 h union
         select 12 h union select 13 h union select 14 h union select 15 h union
         select 16 h union select 17 h union select 18 h union select 19 h union
         select 20 h union select 21 h union select 22 h union select 23 h) hours
    group by ib.item
    ) iba
where iba.sdprice < iba.avgprice/2
and iba.bidper / iba.avgprice < 0.2
order by iba.bidper / iba.avgprice asc
limit 20) lowbids on i.id=lowbids.item
EOF;

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'        => 'Potential Low Bids - Gear',
            'items'       => CategoryBonusItemList(
                $house, [
                    'joins' => sprintf($joins, $house, ''),
                    'where' => 'ifnull(lowbids.bid / g.median, 0) < 0.2',
                    'cols' => 'lowbids.bid, g.median globalmedian',
                    'outside' => 'r2.bid, r2.globalmedian',
                ]
            ),
            'dynamicItems' => 1,
            'hiddenCols'  => ['price' => true, 'lastseen' => true],
            'visibleCols' => ['bid' => true, 'globalmedian' => true],
            'sort'        => 'lowbids'
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'        => 'Potential Low Bids - Misc',
            'items'       => CategoryRegularItemList(
                $house, [
                    'joins' => sprintf($joins, $house, 'not'),
                    'where' => 'ifnull(lowbids.bid / g.median, 0) < 0.2',
                    'cols' => 'lowbids.bid, g.median globalmedian',
                ]
            ),
            'dynamicItems' => 1,
            'hiddenCols'  => ['price' => true, 'lastseen' => true],
            'visibleCols' => ['bid' => true, 'globalmedian' => true],
            'sort'        => 'lowbids'
        ]
    ];

    return $tr;
}

function CategoryResult_minorstats($house) {
    $result = [
        'name'    => 'Minor Stats',
        'results' => [],
    ];
    $stats = [
        'Speed' => BONUS_STAT_SET_SPEED,
        'Leech' => BONUS_STAT_SET_LEECH,
        'Avoidance' => BONUS_STAT_SET_AVOIDANCE,
        'Indestructible' => BONUS_STAT_SET_INDESTRUCTIBLE,
    ];
    $armorClasses = [
        1 => 'Cloth',
        2 => 'Leather',
        3 => 'Mail',
        4 => 'Plate',
    ];

    foreach ($stats as $statName => $statMask) {
        foreach ($armorClasses as $subclassId => $className) {
            $result['results'][] = [
                'name' => 'ItemList',
                'data' => [
                    'name'       => sprintf('%s %s', $statName, $className),
                    'items'      => CategoryBonusAuctionList($house, [
                        'joins' => 'join tblDBCItemBonus ib on ib.id in (ae.bonus1, ae.bonus2, ae.bonus3, ae.bonus4, ae.bonus5, ae.bonus6)',
                        'where' => sprintf('ib.statmask & %d and i.class = 4 and i.subclass = %d and i.type != 16 ', $statMask, $subclassId),
                    ]),
                    'dynamicItems' => 1,
                    'hiddenCols' => ['lastseen' => true, 'quantity' => true],
                    'sort'       => 'lowprice'
                ]
            ];
        }
        $result['results'][] = [
            'name' => 'ItemList',
            'data' => [
                'name'       => sprintf('%s Other', $statName),
                'items'      => CategoryBonusAuctionList($house, [
                    'joins' => 'join tblDBCItemBonus ib on ib.id in (ae.bonus1, ae.bonus2, ae.bonus3, ae.bonus4, ae.bonus5, ae.bonus6)',
                    'where' => sprintf('ib.statmask & %d and not (i.class = 4 and i.subclass in (%s) and i.type != 16)', $statMask, implode(',', array_keys($armorClasses))),
                ]),
                'dynamicItems' => 1,
                'hiddenCols' => ['lastseen' => true, 'quantity' => true],
                'sort'       => 'lowprice'
            ]
        ];
    }

    return $result;
}

function CategoryResult_unusuals($house)
{
    return [
        'name'    => 'unusualItems',
        'results' => [
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Dropped Rare and Epic Armor/Weapons',
                    'items'       => CategoryUnusualItemList($house, 'i.class in (2,4) and i.quality > 2'),
                    'dynamicItems' => 1,
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
                    'dynamicItems' => 1,
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
                    'dynamicItems' => 1,
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
                    'dynamicItems' => 1,
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
                    'dynamicItems' => 1,
                    'hiddenCols'  => ['avgprice' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'lowprice'
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'        => 'Crafted Armor/Weapons',
                    'items'       => CategoryUnusualItemList($house, 'i.class in (2,4)', CATEGORY_FLAGS_ALLOW_CRAFTED | CATEGORY_FLAGS_DENY_NONCRAFTED),
                    'dynamicItems' => 1,
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
                    'dynamicItems' => 1,
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
                    'dynamicItems' => 1,
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
                    'dynamicItems' => 1,
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
                    'dynamicItems' => 1,
                    'hiddenCols'  => ['avgprice' => true],
                    'visibleCols' => ['globalmedian' => true],
                    'sort'        => 'lowprice'
                ]
            ],
        ]
    ];
}

function CategoryResult_auction($house)
{
    return [
        'name'    => 'Ancient Trading Mechanism',
        'results' => [
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Modules',
                    'items' => CategoryRegularItemList($house, 'i.id in (118375,118376,118377,118378,118379)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Arcane Crystal Module',
                    'items' => CategoryRegularItemList($house, 'i.id in (118344,118345,118346,118347)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Auction Control Module',
                    'items' => CategoryRegularItemList($house, 'i.id in (118197,118331,118332)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Universal Language Module',
                    'items' => CategoryRegularItemList($house, 'i.id in (118333,118334,118335)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Super Cooling Module',
                    'items' => CategoryRegularItemList($house, 'i.id in (118336,118337,118338,118339)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Cyclical Power Module',
                    'items' => CategoryRegularItemList($house, 'i.id in (118340,118341,118342,118343)')
                ]
            ],
        ]
    ];
}

function CategoryResult_archaeology($house)
{
    return [
        'name'    => 'archaeology',
        'results' => [
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Keystones',
                    'items' => CategoryRegularItemList($house, 'i.id in (154990, 154989, 130903, 130904, 130905, 108439, 109584, 109585, 79868, 79869, 95373, 64397, 64395, 64396, 64392, 64394, 52843, 63127, 63128)')
                ]
            ],
        ]
    ];
}

function CategoryResult_turnin($house)
{
    $blood_amounts = [
        123918 => 10,
        123919 => 5,
        124101 => 10,
        124102 => 10,
        124103 => 10,
        124104 => 10,
        124105 => 3,
        124107 => 10,
        124108 => 10,
        124109 => 10,
        124110 => 10,
        124111 => 10,
        124112 => 10,
        124113 => 10,
        124115 => 10,
        124117 => 10,
        124118 => 10,
        124119 => 10,
        124120 => 10,
        124121 => 10,
        124437 => 10,
        124438 => 20,
        124439 => 20,
        124440 => 10,
        124441 => 3,
    ];

    $sargerite_amounts = [
        152296 => 1,
        151564 => 10,
        151565 => 10,
        151566 => 10,
        151567 => 10,
        151579 => 0.1,
        151718 => 0.1,
        151719 => 0.1,
        151720 => 0.1,
        151721 => 0.1,
        151722 => 0.1,
    ];

    $sargerite_items = CategoryRegularItemList($house, 'i.id in (124125,' . implode(',', array_keys($sargerite_amounts)) . ')');
    $obliterum_item = false;
    $primal_index = false;
    for ($x = 0; $x < count($sargerite_items); $x++) {
        if ($sargerite_items[$x]['id'] == 124125) {
            $obliterum_item = $sargerite_items[$x];
            array_splice($sargerite_items, $x--, 1);
            continue;
        }
        if ($sargerite_items[$x]['id'] == 152296) {
            $primal_index = $x;
        }
    }
    if ($obliterum_item !== false && $primal_index !== false) {
        $sargerite_items[$primal_index]['price'] -= ($obliterum_item['price'] ?? $obliterum_item['avgprice']);
        $sargerite_items[$primal_index]['avgprice'] -= $obliterum_item['avgprice'];
    }

    $tr = [
        'name'    => 'category_turnin',
        'results' => [
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'bloodofsargeras',
                    'items' => CategoryRegularItemList($house, 'i.id in (' . implode(',', array_keys($blood_amounts)) . ')'),
                    'amounts' => $blood_amounts,
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'primalsargerite',
                    'items' => $sargerite_items,
                    'amounts' => $sargerite_amounts,
                ]
            ],
        ]
    ];

    $contribution_amounts = [
        152494 => 20,
        152495 => 20,
        152509 => 60,
        152512 => 60,
        152541 => 60,
        152547 => 60,
        152557 => 2,
        152576 => 60,
        152812 => 2,
        152813 => 2,
        153438 => 3,
        153441 => 3,
        153710 => 15,
        153715 => 15,
        154166 => 2,
        154167 => 2,
        154706 => 1,
        154707 => 1,
        154891 => 30,
        154898 => 60,
        158201 => 3,
        158202 => 3,
        158203 => 2,
        158204 => 3,
        158212 => 6,
        158377 => 3,
        159789 => 3,
    ];

    array_unshift($tr['results'], [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'warfronts',
            'items' => CategoryRegularItemList($house, 'i.id in (' . implode(',', array_keys($contribution_amounts)) . ')'),
            'amounts' => $contribution_amounts,
            'sort' => 'lowprice',
        ]
    ]);

    return $tr;
}

function CategoryResult_mining($house)
{
    return [
        'name'    => 'mining',
        'results' => [
            [
                'name' => 'ItemList',
                'data' => ['name'  => 'Battle for Azeroth Ore',
                           'items' => CategoryRegularItemList($house, 'i.id in (152512,152579,152513,168185)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => ['name'  => 'Broken Isles Ore',
                           'items' => CategoryRegularItemList($house, 'i.id in (123918,123919,124444,151564)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => ['name'  => 'Draenor Ore',
                           'items' => CategoryRegularItemList($house, 'i.id in (109119,109118)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Pandarian Ore',
                    'items' => CategoryRegularItemList($house, 'i.id in (72092,72093,72103,72094)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => ['name'  => 'Pandarian Bar',
                           'items' => CategoryRegularItemList($house, 'i.id in (72096,72095)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Cataclysm Ore',
                    'items' => CategoryRegularItemList($house, 'i.id in (52183,52185,53038)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Cataclysm Bar',
                    'items' => CategoryRegularItemList($house, 'i.id in (51950,53039,52186,54849)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Northrend Ore',
                    'items' => CategoryRegularItemList($house, 'i.id in (36912,36909,36910)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Northrend Bar',
                    'items' => CategoryRegularItemList($house, 'i.id in (36913,37663,41163,36916)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Outland Ore',
                    'items' => CategoryRegularItemList($house, 'i.id in (23424,23425,23426,23427)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Outland Bar',
                    'items' => CategoryRegularItemList($house, 'i.id in (23447,23449,35128,23446,23573,23445,23448)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Classic Ore',
                    'items' => CategoryRegularItemList($house, 'i.id in (7911,3858,10620,2772,2776,2771,2775,2770)')
                ]
            ],
            [
                'name' => 'ItemList',
                'data' => [
                    'name'  => 'Classic Bar',
                    'items' => CategoryRegularItemList($house, 'i.id in (17771,12655,11371,12359,6037,3860,3859,3575,3577,2841,3576,2840,2842)')
                ]
            ],
        ]
    ];
}

function CategoryResult_skinning($house)
{
    global $expansions, $expansionLevels;

    $tr = ['name' => 'skinning', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Battle for Azeroth Trade Goods',
            'items' => CategoryRegularItemList($house, 'i.id in (152541,154722,153050,153051,154164,154165,168649,168650)'),
        ],
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Legion Trade Goods',
            'items' => CategoryRegularItemList($house, 'i.id in (124113,124115,124116,124439,124438)'),
        ],
    ];

    for ($x = count($expansions) - 1; $x >= 0; $x--) {
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
        if ($x == 4) {
            $lsql .= ' and i.id != 110610';
        }
        if ($x == 5) {
            $lsql .= ' and i.id in (110609, 110610)';
        }
        if ($x == 6) {
            $lsql .= ' or i.id in (124113,124115)';
        }
        $lsql = 'i.class=7 and i.subclass=6 and i.quality > 0 and (' . $lsql . ')';
        $tr['results'][] = [
            'name' => 'ItemList',
            'data' => [
                'name'  => $expansions[$x] . ' Leather',
                'items' => CategoryRegularItemList($house, $lsql)
            ]
        ];
    }

    return $tr;
}

function CategoryResult_herbalism($house)
{
    global $expansions, $expansionLevels;

    $tr = ['name' => 'herbalism', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Battle for Azeroth Herbs',
            'items' => CategoryRegularItemList($house, 'i.id between 152505 and 152511'),
        ],
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Legion Herbs',
            'items' => CategoryRegularItemList($house, 'i.id in (128304,124106,124105,124104,124103,124102,124101,151565)'),
        ],
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Legion Seeds',
            'items' => CategoryRegularItemList($house, 'i.id in (129284,129285,129286,129287,129288,129289)'),
        ],
    ];

    for ($x = 5; $x >= 0; $x--) {
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
            $lsql3 = ' and i.id not in (109130, 109629, 109628, 109627, 109626, 109625, 109624)';
        }
        $lsql = '((i.class=7 and i.subclass=9 and i.quality in (1,2) and (' . $lsql . '))' . $lsql2 . ')' . $lsql3;
        $tr['results'][] = [
            'name' => 'ItemList',
            'data' => [
                'name'  => $expansions[$x] . ' Herbs',
                'items' => CategoryRegularItemList($house, $lsql)
            ]
        ];
    }

    return $tr;
}

function CategoryResult_alchemy($house)
{
    global $expansions;

    $tr = ['name' => 'alchemy', 'results' => []];

    $current = count($expansions) - 1;

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current] . ' Flasks',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (SELECT distinct xic.id FROM tblDBCSpell xs JOIN tblDBCSpellCrafts xsc on xsc.spell = xs.id JOIN tblDBCItem xic on xsc.item = xic.id WHERE xs.skillline=171 and xs.expansion=' . $current . ' and xic.class=0 and xic.subclass=3) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current] . ' Flasks',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (SELECT distinct xic.id FROM tblDBCSpell xs join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xic on xsc.item = xic.id WHERE xs.skillline=171 and xs.expansion=' . $current . ' and xic.class=0 and xic.subclass=2) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current] . ' Potions',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (SELECT distinct xic.id FROM tblDBCSpell xs join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xic on xsc.item = xic.id WHERE xs.skillline=171 and xs.expansion=' . $current . ' and xic.class=0 and xic.subclass=1) xyz on xyz.id = i.id'])
        ]
    ];

    $sql = <<<EOF
join (
select distinct xic.id
from tblDBCSpell xs
join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xic on xsc.item = xic.id
where xs.skillline=171
and xs.expansion = $current
and xic.class=0
and xic.subclass in (8)
) xyz on xyz.id = i.id
EOF;

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current] . ' General Purpose',
            'items' => CategoryRegularItemList($house, ['joins' => $sql])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current] . ' Trinkets',
            'items' => CategoryBonusItemList($house, 'i.id in (152634, 152636)')
        ]
    ];


    $current--;
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current] . ' Flasks',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (SELECT distinct xic.id FROM tblDBCSpell xs join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xic on xsc.item = xic.id WHERE xs.skillline=171 and xs.expansion=' . $current . ' and xic.class=0 and xic.subclass=3) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current] . ' Elixirs',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (SELECT distinct xic.id FROM tblDBCSpell xs join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xic on xsc.item = xic.id WHERE xs.skillline=171 and xs.expansion=' . $current . ' and xic.class=0 and xic.subclass=2) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current] . ' Potions',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (SELECT distinct xic.id FROM tblDBCSpell xs join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xic on xsc.item = xic.id WHERE xs.skillline=171 and xs.expansion=' . $current . ' and xic.class=0 and xic.subclass=1) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'RecipeList',
        'data' => [
            'name'  => 'Recipes',
            'map'   => CategoryRecipeMap(171),
            'recipes' => CategoryRegularItemList($house, ['key' => 'id',                     'joins' => 'join (select distinct xi.id  from tblDBCItemSpell xis join tblDBCSpell xs on xs.id = xis.spell join tblDBCItem xi on xi.id = xis.item join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xii on xsc.item = xii.id where xs.skillline=171 and xi.auctionable=1 and xii.auctionable=1) xyz on xyz.id = i.id']),
            'crafted' => CategoryRegularItemList($house, ['locales' => false, 'key' => 'id', 'joins' => 'join (select distinct xii.id from tblDBCItemSpell xis join tblDBCSpell xs on xs.id = xis.spell join tblDBCItem xi on xi.id = xis.item join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xii on xsc.item = xii.id where xs.skillline=171 and xi.auctionable=1 and xii.auctionable=1) xyz on xyz.id = i.id']),
        ]
    ];

    return $tr;
}

function CategoryResult_leatherworking($house)
{
    global $expansions;

    $tr = ['name' => 'leatherworking', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Alliance Coarse Leather Armor',
            'items' => CategoryBonusItemList($house, 'i.id between 161938 and 161945')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Alliance Shimmerscale Armor',
            'items' => CategoryBonusItemList($house, 'i.id between 161953 and 161960')
        ]
    ];

    foreach (['Leather', 'Mail'] as $armorType) {
        foreach (['Notorious', 'Sinister', 'Honorable'] as $version) {
            $tr['results'][] = [
                'name' => 'ItemList',
                'data' => [
                    'name'  => "Alliance $version Combatant $armorType Armor",
                    'items' => CategoryBonusItemList($house, "i.name_enus like '$version Combatant''s $armorType%' and i.requiredside='Alliance'")
                ]
            ];
        }
    }

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Alliance Weapons',
            'items' => CategoryBonusItemList($house, 'i.id in (161970, 161969, 161972, 161971, 161975, 161968, 164711, 167935)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Horde Coarse Leather Armor',
            'items' => CategoryBonusItemList($house, 'i.id between 154138 and 154145')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Horde Shimmerscale Armor',
            'items' => CategoryBonusItemList($house, 'i.id between 154146 and 154153')
        ]
    ];

    foreach (['Leather', 'Mail'] as $armorType) {
        foreach (['Notorious', 'Sinister', 'Honorable'] as $version) {
            $tr['results'][] = [
                'name' => 'ItemList',
                'data' => [
                    'name'  => "Horde $version Combatant $armorType Armor",
                    'items' => CategoryBonusItemList($house, "i.name_enus like '$version Combatant''s $armorType%' and i.requiredside='Horde'")
                ]
            ];
        }
    }

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Horde Weapons',
            'items' => CategoryBonusItemList($house, 'i.id in (154161, 154160, 154163, 154162, 159896, 154159, 164670, 167934)')
        ]
    ];

    $current = count($expansions) - 1;
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current] . ' Trade Goods',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x join tblDBCSpellCrafts xsc on xsc.item = x.id join tblDBCSpell xs on xsc.spell = xs.id where xs.expansion = ' . $current . ' and x.class=7 and xs.skillline=165) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current] . ' Consumables',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x join tblDBCSpellCrafts xsc on xsc.item = x.id join tblDBCSpell xs on xsc.spell = xs.id where xs.expansion = ' . $current . ' and (x.class=0 or (x.class=15 and x.subclass=6)) and xs.skillline=165) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Fiendish',
            'items' => CategoryBonusItemList($house, 'i.id in (151578, 151577)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Warhide Leather',
            'items' => CategoryBonusItemList($house, 'i.id between 128876 and 128883')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Dreadleather',
            'items' => CategoryBonusItemList($house, 'i.id between 128884 and 128891'),
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Battlebound Mail',
            'items' => CategoryBonusItemList($house, 'i.id between 128892 and 128899')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Gravenscale Mail',
            'items' => CategoryBonusItemList($house, 'i.id between 128900 and 128907'),
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Legion Other',
            'items' => CategoryRegularItemList($house, 'i.id in (129956,142406,129958,129960,129961,131746,151566)'),
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Bags',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x join tblDBCSpellCrafts xsc on xsc.item = x.id join tblDBCSpell xs on xsc.spell = xs.id where x.class=1 and xs.skillline=165) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'        => 'Absent Transmog',
            'hiddenCols'  => ['quantity' => true, 'price' => true, 'avgprice' => true],
            'visibleCols' => ['globalmedian' => true],
            'sort'        => 'globalmedian',
            'dynamicItems' => 1,
            'items'       => CategoryRegularItemList($house, [
                'where' => sprintf(ABSENT_TRANSMOG_SQL, $house, 165),
                'cols'  => 'g.median globalmedian',
            ])
        ]
    ];

    $tr['results'][] = [
        'name' => 'RecipeList',
        'data' => [
            'name'  => 'Recipes',
            'map'   => CategoryRecipeMap(165),
            'recipes' => CategoryRegularItemList($house, ['key' => 'id',                     'joins' => 'join (select distinct xi.id  from tblDBCItemSpell xis join tblDBCSpell xs on xs.id = xis.spell join tblDBCItem xi on xi.id = xis.item join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xii on xsc.item = xii.id where xs.skillline=165 and xi.auctionable=1 and xii.auctionable=1) xyz on xyz.id = i.id']),
            'crafted' => CategoryBonusItemList($house, ['locales' => false, 'key' => 'id', 'joins' => 'join (select distinct xii.id from tblDBCItemSpell xis join tblDBCSpell xs on xs.id = xis.spell join tblDBCItem xi on xi.id = xis.item join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xii on xsc.item = xii.id where xs.skillline=165 and xi.auctionable=1 and xii.auctionable=1) xyz on xyz.id = i.id']),
        ]
    ];

    return $tr;
}

function CategoryResult_blacksmithing($house)
{
    global $expansions;

    $tr = ['name' => 'blacksmithing', 'results' => []];
    $sortIndex = 0;

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Alliance Monel-Hardened Armor',
            'items' => CategoryBonusItemList($house, 'i.id between 161880 and 161887')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Alliance Notorious Combatant Armor',
            'items' => CategoryBonusItemList($house, 'i.id in (167965, 167967, 167969, 167971, 167973)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Alliance Sinister Combatant Armor',
            'items' => CategoryBonusItemList($house, 'i.id in (164685, 164686, 164687, 164688, 164689)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Alliance Honorable Combatant Armor',
            'items' => CategoryBonusItemList($house, 'i.id between 161893 and 161897')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Alliance Monel-Hardened Weapons',
            'items' => CategoryBonusItemList($house, 'i.id in (152818, 161913, 161914, 161915, 161916, 161917)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Alliance Notorious Combatant Weapons',
            'items' => CategoryBonusItemList($house, 'i.id in (167937, 167939, 167975, 167989, 167991, 167995)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Alliance Sinister Combatant Weapons',
            'items' => CategoryBonusItemList($house, 'i.id in (164690, 164691, 164692, 164693, 164718, 164684)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Alliance Honorable Combatant Weapons',
            'items' => CategoryBonusItemList($house, 'i.id in (161892,161920,161921,161922,161923)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Horde Monel-Hardened Armor',
            'items' => CategoryBonusItemList($house, 'i.id between 152802 and 152809')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Horde Notorious Combatant Armor',
            'items' => CategoryBonusItemList($house, 'i.id in (167964, 167966, 167968, 167970, 167972)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Horde Sinister Combatant Armor',
            'items' => CategoryBonusItemList($house, 'i.id in (164653, 164654, 164655, 164656, 164657)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Horde Honorable Combatant Armor',
            'items' => CategoryBonusItemList($house, 'i.id in (159860, 159891, 159863, 159865, 159866)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Horde Monel-Hardened Weapons',
            'items' => CategoryBonusItemList($house, 'i.id in (161890,152827,152828,152831,152832,152833)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Horde Notorious Combatant Weapons',
            'items' => CategoryBonusItemList($house, 'i.id in (167936, 167938, 167974, 167988, 167990, 167994)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Horde Sinister Combatant Weapons',
            'items' => CategoryBonusItemList($house, 'i.id in (164649, 164650, 164651, 164652, 164719, 164648)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Horde Honorable Combatant Weapons',
            'items' => CategoryBonusItemList($house, 'i.id in (159851,159853,159855,159857,159858,162653)')
        ]
    ];

    $current = count($expansions) - 1;
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current] . ' Other',
            'items' => CategoryRegularItemList($house, 'i.id in (159826,152812,152813,162115,162109,162120,168417,158887)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Leystone',
            'items' => CategoryBonusItemList($house, 'i.id between 123891 and 123898')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Demonsteel',
            'items' => CategoryBonusItemList($house, 'i.id between 123910 and 123917'),
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Legion Other',
            'items' => CategoryRegularItemList($house, 'i.id in (137686,124461,123956,136708,151576,151239)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'sort' => ['main' => $sortIndex++],
        'data' => [
            'name'  => 'Other Consumables',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x join tblDBCSpellCrafts xsc on xsc.item = x.id join tblDBCSpell xs on xsc.spell = xs.id where xs.expansion < ' . ($current - 1) . ' and x.level>40 and x.class=0 and xs.skillline=164) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'        => 'Absent Transmog',
            'hiddenCols'  => ['quantity' => true, 'price' => true, 'avgprice' => true],
            'visibleCols' => ['globalmedian' => true],
            'sort'        => 'globalmedian',
            'dynamicItems' => 1,
            'items'       => CategoryRegularItemList($house, [
                'where' => sprintf(ABSENT_TRANSMOG_SQL, $house, 164),
                'cols'  => 'g.median globalmedian',
            ])
        ]
    ];

    $tr['results'][] = [
        'name' => 'RecipeList',
        'sort' => ['main' => $sortIndex++],
        'data' => [
            'name'  => 'Recipes',
            'map'   => CategoryRecipeMap(164),
            'recipes' => CategoryRegularItemList($house, ['key' => 'id',                     'joins' => 'join (select distinct xi.id  from tblDBCItemSpell xis join tblDBCSpell xs on xs.id = xis.spell join tblDBCItem xi on xi.id = xis.item join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xii on xsc.item = xii.id where xs.skillline=164 and xi.auctionable=1 and xii.auctionable=1) xyz on xyz.id = i.id']),
            'crafted' => CategoryBonusItemList($house, ['locales' => false, 'key' => 'id', 'joins' => 'join (select distinct xii.id from tblDBCItemSpell xis join tblDBCSpell xs on xs.id = xis.spell join tblDBCItem xi on xi.id = xis.item join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xii on xsc.item = xii.id where xs.skillline=164 and xi.auctionable=1 and xii.auctionable=1) xyz on xyz.id = i.id']),
        ]
    ];

    return $tr;
}

function CategoryResult_jewelcrafting($house)
{
    global $expansions, $qualities;

    $tr = ['name' => 'jewelcrafting', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $qualities[2] . ' Uncut Gems',
            'items' => CategoryRegularItemList($house, 'i.id between 153700 and 153705')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $qualities[3] . ' Uncut Gems',
            'items' => CategoryRegularItemList($house, 'i.id between 154120 and 154125')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $qualities[4] . ' Uncut Gems',
            'items' => CategoryRegularItemList($house, 'i.id = 153706')
        ]
    ];

    $tr['results'] = array_merge($tr['results'], CategoryTradeskillResults($house, 755, 7));

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Legion ' . $qualities[2] . ' Gems',
            'items' => CategoryRegularItemList($house, 'i.id between 130215 and 130218')
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Legion ' . $qualities[3] . ' Gems',
            'items' => CategoryRegularItemList($house, 'i.id between 130219 and 130222')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Legion ' . $qualities[4] . ' Gems',
            'items' => CategoryRegularItemList($house, '(i.id between 130246 and 130248 or i.id in (151584, 151583, 151585, 151580))')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Mounts',
            'items' => CategoryRegularItemList($house, 'i.id in (82453,83087,83088,83089,83090)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Companions and Toys',
            'items' => CategoryRegularItemList($house, 'i.id in (130254,130250,82774,82775,130251)')
        ]
    ];

    /*
    for ($x = 0; $x <= 10; $x++) {
        $tr['results'][] = [
            'name' => 'ItemList',
            'data' => [
                'name'  => 'itemSubClasses.3-'.$x,
                'items' => CategoryRegularItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x join tblDBCSpellCrafts xsc on xsc.item = x.id join tblDBCSpell xs on xsc.spell = xs.id where xs.expansion < 4 and x.class=3 and x.subclass='.$x.' and xs.skillline=755) xyz on xyz.id = i.id'])
            ]
        ];
    }

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'        => 'Absent Transmog',
            'hiddenCols'  => ['quantity' => true, 'price' => true, 'avgprice' => true],
            'visibleCols' => ['globalmedian' => true],
            'sort'        => 'globalmedian',
            'dynamicItems' => 1,
            'items'       => CategoryRegularItemList($house, [
                'where' => sprintf(ABSENT_TRANSMOG_SQL, $house, 755),
                'cols'  => 'g.median globalmedian',
            ])
        ]
    ];

    $tr['results'][] = [
        'name' => 'RecipeList',
        'data' => [
            'name'  => 'Recipes',
            'map'   => CategoryRecipeMap(755),
            'recipes' => CategoryRegularItemList($house, ['key' => 'id',                     'joins' => 'join (select distinct xi.id  from tblDBCItemSpell xis join tblDBCSpell xs on xs.id = xis.spell join tblDBCItem xi on xi.id = xis.item join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xii on xsc.item = xii.id where xs.skillline=755 and xi.auctionable=1 and xii.auctionable=1) xyz on xyz.id = i.id']),
            'crafted' => CategoryBonusItemList($house, ['locales' => false, 'key' => 'id', 'joins' => 'join (select distinct xii.id from tblDBCItemSpell xis join tblDBCSpell xs on xs.id = xis.spell join tblDBCItem xi on xi.id = xis.item join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xii on xsc.item = xii.id where xs.skillline=755 and xi.auctionable=1 and xii.auctionable=1) xyz on xyz.id = i.id']),
        ]
    ];
    */

    return $tr;
}

function CategoryResult_engineering($house)
{
    global $expansions;

    $tr = ['name' => 'engineering', 'results' => []];

    for ($x = 1; $x <= 2; $x++) {
        $itemSets = CategoryGetTradeItemsInExpansion(202, count($expansions) - $x);

        foreach ($itemSets as $setName => $itemCsv) {
            $tr['results'][] = [
                'name' => 'ItemList',
                'data' => [
                    'name'  => $setName,
                    'items' => CategoryBonusItemList($house, "i.id in ($itemCsv) and i.id not in (161930,161931,153506,159936)")
                ]
            ];
            if ($setName == 'Battle for Azeroth Weapons') {
                $tr['results'][] = [
                    'name' => 'ItemList',
                    'data' => [
                        'name'  => 'Alliance Guns',
                        'items' => CategoryBonusItemList($house, "i.id in (161930,161931)")
                    ]
                ];
                $tr['results'][] = [
                    'name' => 'ItemList',
                    'data' => [
                        'name'  => 'Horde Guns',
                        'items' => CategoryBonusItemList($house, "i.id in (153506,159936)")
                    ]
                ];
            }
        }
    }

    for ($x = count($expansions) - 3; $x >= 0; $x--) {
        $itemSets = CategoryGetTradeItemsInExpansion(202, $x);

        foreach ($itemSets as $setName => $itemCsv) {
            $tr['results'][] = [
                'name' => 'ItemList',
                'data' => [
                    'name'  => $setName,
                    'items' => CategoryBonusItemList($house, "i.id in ($itemCsv) and i.class not in (2,4)")
                ]
            ];
        }
    }


    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Companions',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x join tblDBCSpellCrafts xsc on xsc.item = x.id join tblDBCSpell xs on xsc.spell = xs.id where x.class=15 and x.subclass=2 and xs.skillline=202) xyz on xyz.id = i.id'])
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Mounts',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x join tblDBCSpellCrafts xsc on xsc.item = x.id join tblDBCSpell xs on xsc.spell = xs.id where x.class=15 and x.subclass=5 and xs.skillline=202) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'        => 'Absent Transmog',
            'hiddenCols'  => ['quantity' => true, 'price' => true, 'avgprice' => true],
            'visibleCols' => ['globalmedian' => true],
            'sort'        => 'globalmedian',
            'dynamicItems' => 1,
            'items'       => CategoryRegularItemList($house, [
                'where' => sprintf(ABSENT_TRANSMOG_SQL, $house, 202),
                'cols'  => 'g.median globalmedian',
            ])
        ]
    ];

    $tr['results'][] = [
        'name' => 'RecipeList',
        'data' => [
            'name'  => 'Recipes',
            'map'   => CategoryRecipeMap(202),
            'recipes' => CategoryRegularItemList($house, ['key' => 'id',                     'joins' => 'join (select distinct xi.id  from tblDBCItemSpell xis join tblDBCSpell xs on xs.id = xis.spell join tblDBCItem xi on xi.id = xis.item join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xii on xsc.item = xii.id where xs.skillline=202 and xi.auctionable=1 and xii.auctionable=1) xyz on xyz.id = i.id']),
            'crafted' => CategoryBonusItemList($house, ['locales' => false, 'key' => 'id', 'joins' => 'join (select distinct xii.id from tblDBCItemSpell xis join tblDBCSpell xs on xs.id = xis.spell join tblDBCItem xi on xi.id = xis.item join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xii on xsc.item = xii.id where xs.skillline=202 and xi.auctionable=1 and xii.auctionable=1) xyz on xyz.id = i.id']),
        ]
    ];

    return $tr;
}

function CategoryResult_tailoring($house)
{
    global $expansions;

    $current = count($expansions) - 1;

    $tr = ['name' => 'tailoring', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Cloth',
            'items' => CategoryRegularItemList($house, 'i.id in (2589,2592,4306,4338,14047,21877,33470,53010,72988,111557,124437,151567,152576,152577)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Alliance Tidespray Linen Armor',
            'items' => CategoryBonusItemList($house, 'i.id between 161977 and 161984'),
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Alliance Cloaks',
            'items' => CategoryBonusItemList($house, 'i.id in (161987, 161988, 161991, 161990, 161998)'),
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Alliance Honorable Combatant',
            'items' => CategoryBonusItemList($house, 'i.id between 161993 and 161997'),
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Horde Tidespray Linen Armor',
            'items' => CategoryBonusItemList($house, 'i.id between 154685 and 154692'),
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Horde Cloaks',
            'items' => CategoryBonusItemList($house, 'i.id in (154697, 154698, 154701, 154700, 159917)'),
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Horde Honorable Combatant',
            'items' => CategoryBonusItemList($house, 'i.id between 159912 and 159916'),
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Bags',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x join tblDBCSpellCrafts xsc on xsc.item = x.id join tblDBCSpell xs on xsc.spell = xs.id where x.level>40 and x.class=1 and x.subclass=0 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Profession Bags',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x join tblDBCSpellCrafts xsc on xsc.item = x.id join tblDBCSpell xs on xsc.spell = xs.id where x.level>40 and x.class=1 and x.subclass!=0 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current] . ' Trade Goods',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x join tblDBCSpellCrafts xsc on xsc.item = x.id join tblDBCSpell xs on xsc.spell = xs.id where xs.expansion = ' . $current . ' and x.class=7 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current] . ' Consumables',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x join tblDBCSpellCrafts xsc on xsc.item = x.id join tblDBCSpell xs on xsc.spell = xs.id where xs.expansion = ' . $current . ' and x.class=0 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Imbued Silkweave Cloth',
            'items' => CategoryBonusItemList($house, 'i.id between 126995 and 127002'),
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Silkweave Cloth',
            'items' => CategoryBonusItemList($house, 'i.id between 126987 and 126994')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Imbued Silkweave Cape',
            'items' => CategoryBonusItemList($house, 'i.id in (127019,127020,127033,127034)'),
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Silkweave Cape',
            'items' => CategoryBonusItemList($house, 'i.id in (127016,127017,127031,127032)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Legion Other',
            'items' => CategoryBonusItemList($house, 'i.id in (137556,137557,137558,139503,151571)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current - 1] . ' Trade Goods',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x join tblDBCSpellCrafts xsc on xsc.item = x.id join tblDBCSpell xs on xsc.spell = xs.id where xs.expansion = ' . ($current - 1) . ' and x.class=7 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[$current - 1] . ' Consumables',
            'items' => CategoryRegularItemList($house, ['joins' => 'join (select distinct x.id from tblDBCItem x join tblDBCSpellCrafts xsc on xsc.item = x.id join tblDBCSpell xs on xsc.spell = xs.id where xs.expansion = ' . ($current - 1) . ' and x.class=0 and xs.skillline=197) xyz on xyz.id = i.id'])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'        => 'Absent Transmog',
            'hiddenCols'  => ['quantity' => true, 'price' => true, 'avgprice' => true],
            'visibleCols' => ['globalmedian' => true],
            'sort'        => 'globalmedian',
            'dynamicItems' => 1,
            'items'       => CategoryRegularItemList($house, [
                'where' => sprintf(ABSENT_TRANSMOG_SQL, $house, 197),
                'cols'  => 'g.median globalmedian',
            ])
        ]
    ];

    $tr['results'][] = [
        'name' => 'RecipeList',
        'data' => [
            'name'  => 'Recipes',
            'map'   => CategoryRecipeMap(197),
            'recipes' => CategoryRegularItemList($house, ['key' => 'id',                     'joins' => 'join (select distinct xi.id  from tblDBCItemSpell xis join tblDBCSpell xs on xs.id = xis.spell join tblDBCItem xi on xi.id = xis.item join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xii on xsc.item = xii.id where xs.skillline=197 and xi.auctionable=1 and xii.auctionable=1) xyz on xyz.id = i.id']),
            'crafted' => CategoryBonusItemList($house, ['locales' => false, 'key' => 'id', 'joins' => 'join (select distinct xii.id from tblDBCItemSpell xis join tblDBCSpell xs on xs.id = xis.spell join tblDBCItem xi on xi.id = xis.item join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xii on xsc.item = xii.id where xs.skillline=197 and xi.auctionable=1 and xii.auctionable=1) xyz on xyz.id = i.id']),
        ]
    ];

    return $tr;
}

function CategoryResult_enchanting($house)
{
    global $expansions;

    $mats = [
        [10938,10939,10940,14343,14344,16202,16203,16204,156930],
        [22445,22446,22447,22448,22449,22450],
        [34052,34054,34055,34056,34057,34053],
        [52555,52718,52719,52721,52722,52720],
        [74247,74248,74249,74250,74252,105718],
        [109693,111245,113588,115504,115502],
        [124440,124441,124442],
        [152875,152876,152877],
    ];

    $tr = ['name' => 'enchanting', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[count($mats) - 1] . ' Reagents',
            'items' => CategoryRegularItemList($house, 'i.id in (' . implode(',', array_pop($mats)) . ')')
        ]
    ];

    $tr['results'] = array_merge($tr['results'], CategoryTradeskillResults($house, 333, 7));

    for ($x = count($mats) - 1; $x >= 0; $x--) {
        $tr['results'][] = [
            'name' => 'ItemList',
            'data' => [
                'name'  => $expansions[$x] . ' Reagents',
                'items' => CategoryRegularItemList($house, 'i.id in (' . implode(',', $mats[$x]) . ')')
            ]
        ];
    }

    return $tr;
}

function CategoryResult_inscription($house)
{
    $tr = ['name' => 'inscription', 'results' => []];

    $tr['results'] = CategoryTradeskillResults($house, 773, 7, [1026, 775, 1130]);

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Decks',
            'items' => CategoryBonusItemList($house, 'i.id in (159128,159127,159126,159125)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Toys',
            'items' => CategoryRegularItemList($house, 'i.id in (129211,128980)')
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Glyphs',
            'items' => CategoryRegularItemList(
                $house, [
                    'joins' => 'join tblDBCSpellCrafts xsc on xsc.item = i.id join tblDBCSpell xs on xs.id = xsc.spell',
                    'where' => 'xs.skillline = 773 and i.class = 16'
                ]
            )
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Legion Vantus Runes',
            'items' => CategoryRegularItemList($house, [
                'joins' => 'join tblDBCSpellCrafts xsc on xsc.item = i.id join tblDBCSpell xs on xs.id = xsc.spell',
                'where' => 'xs.skillline = 773 and xs.expansion = 6 and i.name_enus like \'Vantus Rune%\''
            ])
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Common Ink',
            'items' => CategoryRegularItemList(
                $house, [
                    'joins' => 'join tblDBCSpellCrafts xsc on xsc.item = i.id join tblDBCSpell xs on xs.id = xsc.spell',
                    'where' => 'xs.skillline = 773 and i.class=7 and i.subclass=16 and i.quality=1 and i.id not in (153661)'
                ]
            )
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Uncommon Ink',
            'items' => CategoryRegularItemList(
                $house, [
                    'joins' => 'join tblDBCSpellCrafts xsc on xsc.item = i.id join tblDBCSpell xs on xs.id = xsc.spell',
                    'where' => 'xs.skillline = 773 and i.class=7 and i.subclass=16 and i.quality>1 and i.id not in (113289)'
                ]
            )
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Common Pigments',
            'items' => CategoryRegularItemList($house, "i.id in (39151,39334,39338,39339,39340,39341,39342,39343,61979,79251,153635,153636)")
        ]
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Uncommon Pigments',
            'items' => CategoryRegularItemList($house, "i.id in (43103,43104,43105,43106,43107,43108,43109,61980,79253,153669)")
        ]
    ];

    return $tr;
}

function CategoryResult_cooking($house)
{
    global $expansions;

    $tr = ['name' => 'cooking', 'results' => []];

    /*
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

    $fishIds = [];
    foreach ($fish as $f) {
        $fishIds = array_merge($fishIds, $f);
    }
    sort($fishIds);
    $fishPricesList = CategoryRegularItemList($house, 'i.id in (' . implode(',', $fishIds) . ')');
    $fishPrices = [];
    foreach ($fishPricesList as $p) {
        $fishPrices[$p['id']] = $p;
    }

    $tr['results'][] = [
        'name' => 'FishTable',
        'data' => ['name' => 'Draenor Fish', 'fish' => $fish, 'prices' => $fishPrices]
    ];
    */

    $current = count($expansions) - 1;

    $foods = array_merge([
        $expansions[$current] . ' Meat' => '154899, 154898, 154897, 152631',
        $expansions[$current] . ' Fish' => '152549, 152548, 152547, 152546, 152545, 152544, 152543, 162515, 160711',
        ],
        CategoryGetTradeItemsInExpansion(185, $current),
        [$expansions[$current - 1] . ' Fish' => '133607, 124107, 124109, 124108, 124110, 124111, 124112'],
        CategoryGetTradeItemsInExpansion(185, $current - 1));

    // 'Legion Food' => '142334, 133681, 133579, 133578, 133577, 133576, 133575, 133574, 133573, 133572, 133571, 133570, 133569, 133568, 133567, 133566, 133565, 133564, 133563, 133562, 133561, 133557, 152564',

    foreach ($foods as $name => $sql) {
        $tr['results'][] = [
            'name' => 'ItemList',
            'data' => [
                'name'  => $name,
                'items' => CategoryRegularItemList($house, "i.id in ($sql)"),
            ]
        ];
    }

    $tr['results'][] = [
        'name' => 'RecipeList',
        'data' => [
            'name'  => 'Recipes',
            'map'   => CategoryRecipeMap(185),
            'recipes' => CategoryRegularItemList($house, ['key' => 'id',                     'joins' => 'join (select distinct xi.id  from tblDBCItemSpell xis join tblDBCSpell xs on xs.id = xis.spell join tblDBCItem xi on xi.id = xis.item join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xii on xsc.item = xii.id where xs.skillline=185 and xi.auctionable=1 and xii.auctionable=1) xyz on xyz.id = i.id']),
            'crafted' => CategoryRegularItemList($house, ['locales' => false, 'key' => 'id', 'joins' => 'join (select distinct xii.id from tblDBCItemSpell xis join tblDBCSpell xs on xs.id = xis.spell join tblDBCItem xi on xi.id = xis.item join tblDBCSpellCrafts xsc on xsc.spell = xs.id join tblDBCItem xii on xsc.item = xii.id where xs.skillline=185 and xi.auctionable=1 and xii.auctionable=1) xyz on xyz.id = i.id']),
        ]
    ];

    return $tr;
}

function CategoryResult_companions($house)
{
    $tr = ['name' => 'companions', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Mounts',
            'items' => CategoryRegularItemList($house, "i.class=15 and i.subclass=5")
        ]
    ];
    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Companion Items',
            'items' => CategoryRegularItemList($house, "i.class=15 and i.subclass=2")
        ]
    ];

    return $tr;
}

function CategoryGenericItemList($house, $params)
{
    global $canCache;

    $cacheKey = 'category_gil_' . md5(json_encode($params));

    $skipLocales = is_array($params) && isset($params['locales']) && ($params['locales'] == false);

    if ($canCache && (($tr = MCGetHouse($house, $cacheKey)) !== false)) {
        if (!$skipLocales) {
            PopulateLocaleCols($tr, [
                ['func' => 'GetItemNames',          'key' => 'id',     'name' => 'name'],
            ]);
        }
        return $tr;
    }

    $db = DBConnect();

    if (is_array($params)) {
        $joins = isset($params['joins']) ? $params['joins'] : '';
        $where = isset($params['where']) ? (' and ' . $params['where']) : '';
        $whereUpper = isset($params['whereUpper']) ? (' and ' . $params['whereUpper']) : '';
        $whereLower = isset($params['whereLower']) ? (' where ' . $params['whereLower']) : '';
        $colsUpper = isset($params['colsUpper']) ? (', ' . $params['colsUpper']) : '';
        $colsLowerInside = isset($params['colsLowerInside']) ? (', ' . $params['colsLowerInside']) : '';
        $colsLowerOutside = isset($params['colsLowerOutside']) ? (', ' . $params['colsLowerOutside']) : '';
        $outside = isset($params['outside']) ? ($params['outside'].', ') : '';
        $rowKey = isset($params['key']) ? $params['key'] : false;
    } else {
        $joins = '';
        $where = ($params == '') ? '' : (' and ' . $params);
        $whereUpper = $whereLower = $colsUpper = $colsLowerInside = $colsLowerOutside = '';
        $outside = '';
        $rowKey = false;
    }

    $minPricingLevel = MIN_ITEM_LEVEL_PRICING;

    $sql = <<<EOF
select results.*, $outside
(select round(avg(case hours.h
        when  0 then ihh.silver00 when  1 then ihh.silver01 when  2 then ihh.silver02 when  3 then ihh.silver03
        when  4 then ihh.silver04 when  5 then ihh.silver05 when  6 then ihh.silver06 when  7 then ihh.silver07
        when  8 then ihh.silver08 when  9 then ihh.silver09 when 10 then ihh.silver10 when 11 then ihh.silver11
        when 12 then ihh.silver12 when 13 then ihh.silver13 when 14 then ihh.silver14 when 15 then ihh.silver15
        when 16 then ihh.silver16 when 17 then ihh.silver17 when 18 then ihh.silver18 when 19 then ihh.silver19
        when 20 then ihh.silver20 when 21 then ihh.silver21 when 22 then ihh.silver22 when 23 then ihh.silver23
        else null end) * 100)
        from tblItemHistoryHourly ihh,
        (select  0 h union select  1 h union select  2 h union select  3 h union
         select  4 h union select  5 h union select  6 h union select  7 h union
         select  8 h union select  9 h union select 10 h union select 11 h union
         select 12 h union select 13 h union select 14 h union select 15 h union
         select 16 h union select 17 h union select 18 h union select 19 h union
         select 20 h union select 21 h union select 22 h union select 23 h) hours
        where ihh.house = ? and ihh.item = results.id and ihh.level = results.level) avgprice,
GetCurrentCraftingPrice(?, results.id) craftingprice
from (
    select i.id, i.icon, i.class as classid, i.level baselevel,
    s.quantity, unix_timestamp(s.lastseen) lastseen,
    null cheapestaucid,
    s.price,
    ifnull(s.level,if(i.class in (2,4), i.level, 0)) level
    $colsUpper
    from tblDBCItem i
    left join tblItemSummary s on s.house=? and s.item=i.id
    join tblItemGlobal g on g.item = i.id+0 and g.level = ifnull(s.level,if(i.class in (2,4), i.level, 0)) and g.region = ?
    $joins
    where ifnull(i.auctionable,1) = 1
    and (i.class not in (2,4) or ifnull(s.quantity,0) = 0)
    $where $whereUpper
    group by i.id, ifnull(s.level,0)
) results
group by results.id, results.level
union
select results.*, $outside
(select round(avg(case hours.h
        when  0 then ihh.silver00 when  1 then ihh.silver01 when  2 then ihh.silver02 when  3 then ihh.silver03
        when  4 then ihh.silver04 when  5 then ihh.silver05 when  6 then ihh.silver06 when  7 then ihh.silver07
        when  8 then ihh.silver08 when  9 then ihh.silver09 when 10 then ihh.silver10 when 11 then ihh.silver11
        when 12 then ihh.silver12 when 13 then ihh.silver13 when 14 then ihh.silver14 when 15 then ihh.silver15
        when 16 then ihh.silver16 when 17 then ihh.silver17 when 18 then ihh.silver18 when 19 then ihh.silver19
        when 20 then ihh.silver20 when 21 then ihh.silver21 when 22 then ihh.silver22 when 23 then ihh.silver23
        else null end) * 100)
        from tblItemHistoryHourly ihh,
        (select  0 h union select  1 h union select  2 h union select  3 h union
         select  4 h union select  5 h union select  6 h union select  7 h union
         select  8 h union select  9 h union select 10 h union select 11 h union
         select 12 h union select 13 h union select 14 h union select 15 h union
         select 16 h union select 17 h union select 18 h union select 19 h union
         select 20 h union select 21 h union select 22 h union select 23 h) hours
        where ihh.house = ? and ihh.item = results.id and ihh.level = results.level) avgprice,
GetCurrentCraftingPrice(?, results.id) craftingprice
from (
    select r2.id, r2.icon, r2.classid, r2.baselevel, r2.quantity, r2.lastseen, r2.cheapestaucid,
    a.buy price,
    ifnull(ae.level, r2.level) level $colsLowerOutside
    from (
        select i.id, i.icon, i.class as classid, i.level baselevel,
        s.quantity, unix_timestamp(s.lastseen) lastseen, s.level,
        (select a.id
            from tblAuction a
            left join tblAuctionExtra ae on a.house = ae.house and a.id = ae.id
            where a.house = ?
             and a.item = i.id
             and ifnull(if(ae.level < $minPricingLevel, i.level, ae.level), 0) = s.level
             and a.buy > 0
             order by a.buy
             limit 1) cheapestaucid $colsLowerInside
        from tblDBCItem i
        join tblItemSummary s on s.house=? and s.item=i.id
        $joins
        where ifnull(i.auctionable,1) = 1
        and i.class in (2,4)
        and ifnull(s.quantity,0) > 0
        $where
        group by i.id, s.level
    ) r2
    join tblItemGlobal g on g.item = r2.id+0 and g.level = r2.level and g.region = ?
    join tblAuction a on a.house = ? and a.id = r2.cheapestaucid
    left join tblAuctionExtra ae on ae.house = ? and ae.id = r2.cheapestaucid
    $whereLower
) results
group by results.id, results.level

EOF;

    $region = GetRegion($house);

    $stmt = $db->stmt_init();
    if (!$stmt->prepare($sql)) {
        DebugMessage("Bad SQL: \n" . $sql, E_USER_ERROR);
    }
    $stmt->bind_param('iiisiiiisii', $house, $house, $house, $region, $house, $house, $house, $house, $region, $house, $house);
    $stmt->execute();

    $tr = [];
    $row = [];
    $params = [];
    $fields = $stmt->result_metadata()->fetch_fields();
    foreach ($fields as $field) {
        $params[] = &$row[$field->name];
    }
    call_user_func_array([$stmt, 'bind_result'], $params);

    while ($stmt->fetch()) {
        $unreferenced = [];
        foreach ($row as $k => $v) {
            $unreferenced[$k] = $v;
        }
        if ($rowKey) {
            $tr[$unreferenced[$rowKey]] = $unreferenced;
        } else {
            $tr[] = $unreferenced;
        }
    }

    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    if (!$skipLocales) {
        PopulateLocaleCols($tr, [
            ['func' => 'GetItemNames',          'key' => 'id',     'name' => 'name'],
        ]);
    }

    return $tr;
}

function CategoryRegularItemList($house, $params)
{
    global $canCache;

    $cacheKey = 'category_ril_c_' . md5(json_encode($params));

    $skipLocales = is_array($params) && isset($params['locales']) && ($params['locales'] == false);

    if ($canCache && (($tr = MCGetHouse($house, $cacheKey)) !== false)) {
        if (!$skipLocales) {
            PopulateLocaleCols($tr, [
                ['func' => 'GetItemNames',          'key' => 'id',     'name' => 'name'],
            ]);
        }
        return $tr;
    }

    $db = DBConnect();

    if (is_array($params)) {
        $joins = isset($params['joins']) ? $params['joins'] : '';
        $cols = isset($params['cols']) ? (', ' . $params['cols']) : '';
        $where = isset($params['where']) ? (' and ' . $params['where']) : '';
        $outside = isset($params['outside']) ? ($params['outside'].', ') : '';
        $rowKey = isset($params['key']) ? $params['key'] : false;
    } else {
        $cols = $joins = $outside = '';
        $where = ($params == '') ? '' : (' and ' . $params);
        $rowKey = false;
    }

    $sql = <<<EOF
select results.*, $outside
(select round(avg(case hours.h
        when  0 then ihh.silver00 when  1 then ihh.silver01 when  2 then ihh.silver02 when  3 then ihh.silver03
        when  4 then ihh.silver04 when  5 then ihh.silver05 when  6 then ihh.silver06 when  7 then ihh.silver07
        when  8 then ihh.silver08 when  9 then ihh.silver09 when 10 then ihh.silver10 when 11 then ihh.silver11
        when 12 then ihh.silver12 when 13 then ihh.silver13 when 14 then ihh.silver14 when 15 then ihh.silver15
        when 16 then ihh.silver16 when 17 then ihh.silver17 when 18 then ihh.silver18 when 19 then ihh.silver19
        when 20 then ihh.silver20 when 21 then ihh.silver21 when 22 then ihh.silver22 when 23 then ihh.silver23
        else null end) * 100)
        from tblItemHistoryHourly ihh,
        (select  0 h union select  1 h union select  2 h union select  3 h union
         select  4 h union select  5 h union select  6 h union select  7 h union
         select  8 h union select  9 h union select 10 h union select 11 h union
         select 12 h union select 13 h union select 14 h union select 15 h union
         select 16 h union select 17 h union select 18 h union select 19 h union
         select 20 h union select 21 h union select 22 h union select 23 h) hours
        where ihh.house = ? and ihh.item = results.id and ihh.level = results.level) avgprice,
GetCurrentCraftingPrice(?, results.id) craftingprice
from (
    select i.id, i.icon, i.class as classid, g.level,
    s.quantity, unix_timestamp(s.lastseen) lastseen, s.price $cols
    from tblDBCItem i
    join tblItemGlobal g on g.item = i.id+0 and g.level = if(i.class in (2,4), i.level, 0) and g.region = ?
    left join tblItemSummary s on s.house = ? and s.item = i.id and s.level = g.level
    $joins
    where ifnull(i.auctionable,1) = 1
    $where
    group by i.id
) results
group by results.id
EOF;

    $region = GetRegion($house);

    $stmt = $db->stmt_init();
    if (!$stmt->prepare($sql)) {
        DebugMessage("Bad SQL: \n" . $sql, E_USER_ERROR);
    }
    $stmt->bind_param('iisi', $house, $house, $region, $house);
    $stmt->execute();

    $tr = [];
    $row = [];
    $params = [];
    $fields = $stmt->result_metadata()->fetch_fields();
    foreach ($fields as $field) {
        $params[] = &$row[$field->name];
    }
    call_user_func_array([$stmt, 'bind_result'], $params);

    while ($stmt->fetch()) {
        $unreferenced = [];
        foreach ($row as $k => $v) {
            $unreferenced[$k] = $v;
        }
        if ($rowKey) {
            $tr[$unreferenced[$rowKey]] = $unreferenced;
        } else {
            $tr[] = $unreferenced;
        }
    }

    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    if (!$skipLocales) {
        PopulateLocaleCols($tr, [
            ['func' => 'GetItemNames',          'key' => 'id',     'name' => 'name'],
        ]);
    }

    return $tr;
}

function CategoryBonusItemList($house, $params)
{
    global $canCache;

    $cacheKey = 'category_bil_' . md5(json_encode($params));

    $skipLocales = is_array($params) && isset($params['locales']) && ($params['locales'] == false);

    if ($canCache && (($tr = MCGetHouse($house, $cacheKey)) !== false)) {
        if (!$skipLocales) {
            PopulateLocaleCols($tr, [
                ['func' => 'GetItemNames',     'key' => 'id',       'name' => 'name'],
            ]);
        }
        return $tr;
    }

    $db = DBConnect();

    if (is_array($params)) {
        $cols = isset($params['cols']) ? (', ' . $params['cols']) : '';
        $joins = isset($params['joins']) ? $params['joins'] : '';
        $where = isset($params['where']) ? (' and ' . $params['where']) : '';
        $outside = isset($params['outside']) ? ($params['outside'].', ') : '';
        $rowKey = isset($params['key']) ? $params['key'] : false;
    } else {
        $cols = $joins = $outside = '';
        $where = ($params == '') ? '' : (' and ' . $params);
        $rowKey = false;
    }

    $minPricingLevel = MIN_ITEM_LEVEL_PRICING;

    $sql = <<<EOF
select r2.id, r2.icon, r2.classid, r2.quantity, r2.lastseen, r2.level, r2.baselevel, r2.quantity, r2.lastseen,
ifnull(a.buy, r2.price) price,
(select round(avg(case hours.h
 when  0 then ihh.silver00 when  1 then ihh.silver01 when  2 then ihh.silver02 when  3 then ihh.silver03
 when  4 then ihh.silver04 when  5 then ihh.silver05 when  6 then ihh.silver06 when  7 then ihh.silver07
 when  8 then ihh.silver08 when  9 then ihh.silver09 when 10 then ihh.silver10 when 11 then ihh.silver11
 when 12 then ihh.silver12 when 13 then ihh.silver13 when 14 then ihh.silver14 when 15 then ihh.silver15
 when 16 then ihh.silver16 when 17 then ihh.silver17 when 18 then ihh.silver18 when 19 then ihh.silver19
 when 20 then ihh.silver20 when 21 then ihh.silver21 when 22 then ihh.silver22 when 23 then ihh.silver23
 else null end) * 100)
 from tblItemHistoryHourly ihh,
 (select  0 h union select  1 h union select  2 h union select  3 h union
  select  4 h union select  5 h union select  6 h union select  7 h union
  select  8 h union select  9 h union select 10 h union select 11 h union
  select 12 h union select 13 h union select 14 h union select 15 h union
  select 16 h union select 17 h union select 18 h union select 19 h union
  select 20 h union select 21 h union select 22 h union select 23 h) hours
 where ihh.house = ? and ihh.item = r2.id and ihh.level = r2.level) avgprice,
 GetCurrentCraftingPrice(?, r2.id) craftingprice, 
$outside ae.lootedlevel, ae.`rand`, ae.seed
from (
select i.id, i.icon, i.class as classid, s.price, s.quantity, unix_timestamp(s.lastseen) lastseen, s.level, i.level as baselevel,
(select a.id
    from tblAuction a
    left join tblAuctionExtra ae on a.house = ae.house and a.id = ae.id
    where a.house = ?
     and a.item = i.id
     and ifnull(if(ae.level < $minPricingLevel, null, ae.level), if(i.class in (2,4), i.level, 0)) = s.level
     and a.buy > 0
     order by a.buy
     limit 1) cheapestaucid $cols
from tblDBCItem i
join tblItemSummary s on s.item = i.id and s.house = ?
join tblItemGlobal g on g.item = i.id + 0 and g.level = s.level and g.region = ?
$joins
where ifnull(i.auctionable,1) = 1
$where
) r2
left join tblAuction a on a.house = ? and a.id = r2.cheapestaucid
left join tblAuctionExtra ae on ae.house = ? and ae.id = r2.cheapestaucid
EOF;

    $region = GetRegion($house);

    $stmt = $db->stmt_init();
    if (!$stmt->prepare($sql)) {
        DebugMessage("Bad SQL: \n" . $sql, E_USER_ERROR);
    }
    $stmt->bind_param('iiiisii', $house, $house, $house, $house, $region, $house, $house);
    $stmt->execute();

    $tr = [];
    $row = [];
    $params = [];
    $fields = $stmt->result_metadata()->fetch_fields();
    foreach ($fields as $field) {
        $params[] = &$row[$field->name];
    }
    call_user_func_array([$stmt, 'bind_result'], $params);

    while ($stmt->fetch()) {
        $unreferenced = [];
        foreach ($row as $k => $v) {
            $unreferenced[$k] = $v;
        }
        if ($rowKey) {
            $tr[$unreferenced[$rowKey]] = $unreferenced;
        } else {
            $tr[] = $unreferenced;
        }
    }

    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    if (!$skipLocales) {
        PopulateLocaleCols($tr, [
            ['func' => 'GetItemNames',     'key' => 'id',       'name' => 'name'],
        ]);
    }

    return $tr;
}

function CategoryBonusAuctionList($house, $params)
{
    global $canCache;

    $cacheKey = 'category_bal_2_' . md5(json_encode($params));

    $skipLocales = is_array($params) && isset($params['locales']) && ($params['locales'] == false);

    if ($canCache && (($tr = MCGetHouse($house, $cacheKey)) !== false)) {
        if (!$skipLocales) {
            PopulateLocaleCols($tr, [
                ['func' => 'GetItemNames',     'key' => 'id',       'name' => 'name'],
            ]);
        }
        return $tr;
    }

    $db = DBConnect();

    if (is_array($params)) {
        $cols = isset($params['cols']) ? (', ' . $params['cols']) : '';
        $joins = isset($params['joins']) ? $params['joins'] : '';
        $where = isset($params['where']) ? (' and ' . $params['where']) : '';
    } else {
        $cols = $joins = '';
        $where = ($params == '') ? '' : (' and ' . $params);
    }

    $minPricingLevel = MIN_ITEM_LEVEL_PRICING;

    $sql = <<<EOF
select i.id, i.icon, i.class as classid, a.quantity, null lastseen, s.level, i.level baselevel,
ifnull(a.buy, s.price) price,
(select round(avg(case hours.h
 when  0 then ihh.silver00 when  1 then ihh.silver01 when  2 then ihh.silver02 when  3 then ihh.silver03
 when  4 then ihh.silver04 when  5 then ihh.silver05 when  6 then ihh.silver06 when  7 then ihh.silver07
 when  8 then ihh.silver08 when  9 then ihh.silver09 when 10 then ihh.silver10 when 11 then ihh.silver11
 when 12 then ihh.silver12 when 13 then ihh.silver13 when 14 then ihh.silver14 when 15 then ihh.silver15
 when 16 then ihh.silver16 when 17 then ihh.silver17 when 18 then ihh.silver18 when 19 then ihh.silver19
 when 20 then ihh.silver20 when 21 then ihh.silver21 when 22 then ihh.silver22 when 23 then ihh.silver23
 else null end) * 100)
 from tblItemHistoryHourly ihh,
 (select  0 h union select  1 h union select  2 h union select  3 h union
  select  4 h union select  5 h union select  6 h union select  7 h union
  select  8 h union select  9 h union select 10 h union select 11 h union
  select 12 h union select 13 h union select 14 h union select 15 h union
  select 16 h union select 17 h union select 18 h union select 19 h union
  select 20 h union select 21 h union select 22 h union select 23 h) hours
 where ihh.house = ? and ihh.item = i.id and ihh.level = s.level) avgprice,
ae.lootedlevel, ae.`rand`, ae.seed,
concat_ws(':', ae.bonus1, ae.bonus2, ae.bonus3, ae.bonus4, ae.bonus5, ae.bonus6) as bonusurl
$cols
from tblDBCItem i
join tblAuction a on a.house = ? and a.item = i.id
left join tblAuctionExtra ae on a.house = ae.house and a.id = ae.id
join tblItemSummary s on s.item = i.id and s.house = a.house and
    s.level = ifnull(if(ae.level < $minPricingLevel, null, ae.level), i.level)
join tblItemGlobal g on g.item = i.id + 0 and g.level = s.level and g.region = ?
$joins
where ifnull(i.auctionable,1) = 1
$where
EOF;

    $region = GetRegion($house);

    $stmt = $db->stmt_init();
    if (!$stmt->prepare($sql)) {
        DebugMessage("Bad SQL: \n" . $sql, E_USER_ERROR);
    }
    $stmt->bind_param('iis', $house, $house, $region);
    $stmt->execute();

    $tr = [];
    $row = [];
    $params = [];
    $fields = $stmt->result_metadata()->fetch_fields();
    foreach ($fields as $field) {
        $params[] = &$row[$field->name];
    }
    call_user_func_array([$stmt, 'bind_result'], $params);

    while ($stmt->fetch()) {
        $unreferenced = [];
        foreach ($row as $k => $v) {
            $unreferenced[$k] = $v;
        }
        $tr[] = $unreferenced;
    }

    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    if (!$skipLocales) {
        PopulateLocaleCols($tr, [
            ['func' => 'GetItemNames',     'key' => 'id',       'name' => 'name'],
        ]);
    }

    return $tr;
}

function CategoryDealsItemList($house, $dealsSql, $flags = 0)
{
    global $canCache;

    $cacheKey = 'category_di_' . md5($dealsSql) . '_' . $flags;

    if ($canCache && (($iidList = MCGetHouse($house, $cacheKey)) !== false)) {
        return CategoryDealsItemListCached($house, $iidList, $flags);
    }

    $db = DBConnect();

    $region = GetRegion($house);

    $minPricingLevel = MIN_ITEM_LEVEL_PRICING;

    $fullSql = <<<EOF
select aa.item, aa.level, aa.baselevel,
    (select a.id
    from tblAuction a
    left join tblAuctionExtra ae on ae.house=a.house and ae.id = a.id
    join tblDBCItem i on a.item = i.id
    where a.buy > 0 and a.house=? and a.item=aa.item and
        ifnull(if(ae.level < $minPricingLevel, null, ae.level), if(i.class in (2,4), i.level, 0)) = aa.level
    order by a.buy/a.quantity limit 1) cheapestid
from (
    select ac.item, ac.level, ac.baselevel, ac.c_total, ac.c_over, ac.price, gs.median
    from (
        select ab.item, ab.level, ab.baselevel, count(*) c_total, sum(if(tis2.price > ab.price,1,0)) c_over, ab.price
        from (
            select tis.item+0 item, tis.level+0 level, i.level baselevel, tis.price
            from tblItemSummary tis
            join tblDBCItem i on tis.item=i.id
            where tis.house = ?
            and tis.quantity > 0
            and 0 = (select count(*) from tblDBCItemVendorCost ivc where ivc.item=i.id)
            and i.class not in (16)
            and $dealsSql
EOF;
    if (($flags & CATEGORY_FLAGS_ALLOW_CRAFTED) == 0) {
        $fullSql .= ' and not exists (select 1 from tblDBCSpellCrafts sc where sc.item = i.id) ';
    }
    if ($flags & CATEGORY_FLAGS_DENY_NONCRAFTED) {
        $fullSql .= ' and exists (select 1 from tblDBCSpellCrafts sc where sc.item = i.id) ';
    }
    $fullSql .= <<<EOF
        ) ab
        join tblItemSummary tis2 on tis2.item = ab.item and tis2.level = ab.level
        join tblRealm r on r.house = tis2.house and r.canonical is not null
        where r.region = ?
        group by ab.item, ab.level
    ) ac
    join tblItemGlobal gs on gs.item = ac.item+0 and gs.level = ac.level+0 and gs.region = ?
    where ((c_over/c_total) > 2/3 or c_total < 15)
) aa
where median > 1500000
and median > price
order by (cast(median as signed) - cast(price as signed))/greatest(5000000,price) * (c_over/c_total) desc
limit 15
EOF;

    $stmt = $db->stmt_init();
    if (!$stmt->prepare($fullSql)) {
        DebugMessage("Bad SQL: \n" . $fullSql, E_USER_ERROR);
    }
    $stmt->bind_param('iiss', $house, $house, $region, $region);
    $stmt->execute();
    $result = $stmt->get_result();
    if (($result === false) && ($errMsg = $db->error)) {
        DebugMessage("No result: $errMsg\n" . $fullSql, E_USER_ERROR);
    }
    $iidList = DBMapArray($result, null);
    $stmt->close();

    MCSetHouse($house, $cacheKey, $iidList);

    return CategoryDealsItemListCached($house, $iidList, $flags);
}

function CategoryDealsItemListCached($house, $iidList, $flags)
{
    if (count($iidList) == 0) {
        return array();
    }

    $auctionIds = [];
    $sortBy = [];
    $sql = '(';
    foreach ($iidList as $row) {
        $sql .= (strlen($sql) == 1 ? '' : ' or ') . '(i.id = ' . $row['item'] . ' and s.level = ' . $row['level'] . ')';
        $itemKey = $row['item'].':'.$row['level'];
        $sortBy[] = $itemKey;
        if (isset($row['cheapestid'])) {
            $auctionIds[$itemKey] = $row['cheapestid'];
        }
    }
    $sql .= ')';

    $sortBy = array_flip($sortBy);

    if ($flags & CATEGORY_FLAGS_WITH_BONUSES) {
        $tr = CategoryBonusItemList($house, [
            'where' => $sql,
            'cols' => 'g.median globalmedian',
            'outside' => 'r2.globalmedian',
        ]);
    } else {
        $tr = CategoryRegularItemList($house, [
            'where' => $sql,
            'cols' => 'g.median globalmedian',
        ]);
    }

    usort(
        $tr, function ($a, $b) use ($sortBy) {
            return $sortBy[$a['id'].':'.$a['level']] - $sortBy[$b['id'].':'.$b['level']];
        }
    );

    static $allRecentDates = [];
    if (isset($allRecentDates[$house])) {
        $recentDates = $allRecentDates[$house];
    } else {
        $recentDates = MCGetHouse($house, 'category_disnapshots');
        if ($recentDates === false) {
            $db = DBConnect();
            $stmt = $db->stmt_init();
            $stmt->prepare('SELECT unix_timestamp(updated) upd, maxid FROM `tblSnapshot` WHERE house=? and updated > timestampadd(hour, -60, now()) order by updated');
            $stmt->bind_param('i', $house);
            $stmt->execute();
            $result = $stmt->get_result();
            $recentDates = DBMapArray($result, null);
            $stmt->close();

            MCSetHouse($house, 'category_disnapshots', $recentDates);
        }
        $allRecentDates[$house] = $recentDates;
    }

    if (count($recentDates) < 2) {
        return $tr;
    }

    $rolloverBump = 0;
    if ($recentDates[count($recentDates) - 1]['maxid'] < $recentDates[0]['maxid']) {
        $rolloverBump = 0x80000000;
    }

    foreach ($tr as &$row) {
        $row['posted'] = null;
        $itemKey = $row['id'].':'.$row['level'];
        if (!isset($auctionIds[$itemKey])) {
            continue;
        }
        $myId = $auctionIds[$itemKey];
        if ($myId < 0x20000000) {
            $myId += $rolloverBump;
        }
        $x = count($recentDates) - 1;
        do {
            $maxId = $recentDates[$x]['maxid'];
            if ($maxId < 0x20000000) {
                $maxId += $rolloverBump;
            }
            if ($maxId < $myId) {
                break;
            }
            $row['posted'] = $recentDates[$x]['upd'];
        } while (--$x >= 0);
    }
    unset($row);

    return $tr;
}

function CategoryUnusualItemList($house, $unusualSql, $flags = 0)
{
    $craftedSql = '';

    if (($flags & CATEGORY_FLAGS_ALLOW_CRAFTED) == 0) {
        $craftedSql .= ' and not exists (select 1 from tblDBCSpellCrafts sc where sc.item = i.id) ';
    }
    if ($flags & CATEGORY_FLAGS_DENY_NONCRAFTED) {
        $craftedSql .= ' and exists (select 1 from tblDBCSpellCrafts sc where sc.item = i.id) ';
    }

    $params = [
        'where' => $unusualSql . $craftedSql . ' and s.level in (0, i.level)',
        'joins' => 'join tblAuction a on a.house=s.house and a.item=i.id join tblAuctionRare ar on ar.house=a.house and ar.id=a.id',
        'colsUpper'        => 'g.median globalmedian, min(ar.prevseen) `lastseenover`, min(if(a.buy = 0, a.bid, a.buy) / a.quantity) `priceover`',
        'colsLowerOutside' => 'g.median globalmedian, r2.lastseenover, r2.priceover',
        'colsLowerInside'  => 'min(ar.prevseen) `lastseenover`, min(if(a.buy = 0, a.bid, a.buy) / a.quantity) `priceover`',
        'outside' => 'lastseenover as lastseen, priceover as price',
    ];

    return CategoryGenericItemList($house, $params);
}

function CategoryRecipeMap($skill)
{
    $cacheKey = 'category_recipe_map_' . $skill;
    $map = MCGet($cacheKey);
    if ($map !== false) {
        return $map;
    }

    $sql = <<<'EOF'
SELECT i.id recipe, ii.id crafted
FROM `tblDBCItemSpell` dis
join tblDBCSpell s on dis.spell = s.id
join tblDBCItem i on dis.item = i.id
join tblDBCSpellCrafts xsc on xsc.spell = s.id
join tblDBCItem ii on xsc.item = ii.id
where s.skillline = ?
and i.auctionable = 1
and ii.auctionable = 1
EOF;

    $db = DBConnect();
    $stmt = $db->stmt_init();
    $stmt->prepare($sql);
    $stmt->bind_param('i', $skill);
    $stmt->execute();
    $result = $stmt->get_result();
    $map = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
    $stmt->close();

    MCSet($cacheKey, $map, 43200);

    return $map;
}

function CategoryGetTradeItemsInExpansion($skillLine, $expansionId) {
    global $canCache, $expansions;

    $expansionName = $expansions[$expansionId];

    $itemsByCategory = false;
    $categoryCacheKey = "categories-tsc-2-{$skillLine}-{$expansionId}";
    if ($canCache) {
        $itemsByCategory = MCGet($categoryCacheKey);
    }
    if ($itemsByCategory === false) {
        $itemsByCategory = [];

        $sql = <<<'SQL'
select distinct sc.item, tsc.name
from tblDBCSpell s
join tblDBCSpellCrafts sc on s.id = sc.spell
join tblDBCTradeSkillCategory tsc on s.tradeskillcategory = tsc.id
where s.skillline = ?
and s.expansion = ?
order by tsc.`order`
SQL;
        $db = DBConnect();
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ii', $skillLine, $expansionId);
        $stmt->execute();
        $id = $catName = null;
        $stmt->bind_result($id, $catName);
        while ($stmt->fetch()) {
            $itemsByCategory["$expansionName $catName"][] = $id;
        }
        $stmt->close();

        foreach ($itemsByCategory as &$ids) {
            $ids = implode(',', $ids);
        }
        unset($ids);

        MCSet($categoryCacheKey, $itemsByCategory);
    }

    return $itemsByCategory;
}

function CategoryTradeskillResults($house, $skillLine, $expansionId, $excludeCategories = []) {
    global $canCache, $qualities;

    $cacheKey = sprintf('category_tradeskill_ids-2_%d_%d_%s',
        $skillLine, $expansionId, md5(implode(',', $excludeCategories)));

    $catData = false;
    if ($canCache) {
        $catData = MCGet($cacheKey);
    }

    if (!$catData) {
        $db = DBConnect();

        $excludeSql = '';
        if ($excludeCategories) {
            $excludeSql = ' and tsc.id not in (' . implode(',', $excludeCategories) . ')';
        }

        $sql = <<<SQL
select i.id, i.quality, i.class, concat_ws(' ', 
	if(instr(i.name_enus, 'Kul Tiran'), 'Kul Tiran', null),
	if(instr(i.name_enus, 'Zandalari'), 'Zandalari', null),
	tsc.name) catname
from tblDBCSpell s
join tblDBCTradeSkillCategory tsc on s.tradeskillcategory = tsc.id
join tblDBCSpellCrafts xsc on xsc.spell = s.id join tblDBCItem i on xsc.item = i.id
where s.skillline=? and s.expansion=? and i.auctionable=1 {$excludeSql}
group by i.id
order by tsc.`order`, i.quality, i.name_enus;
SQL;

        $stmt = $db->prepare($sql);
        $stmt->bind_param('ii', $skillLine, $expansionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->close();
        $stmt->close();

        $catData = [];
        $lastCatName = '';
        $lastQuality = null;
        $usedQuality = false;
        $curCat = [];

        foreach ($rows as $row) {
            if ($row['catname'] != $lastCatName &&
                preg_replace('/(?:Kul Tiran|Zandalari) /', '', $row['catname']) != $lastCatName) {
                if ($curCat) {
                    $catData[] = $curCat;
                }
                $curCat = [
                    'name' => $row['catname'],
                    'useBonus' => false,
                    'items' => [],
                ];
                $lastCatName = $row['catname'];
                $lastQuality = $row['quality'];
                $usedQuality = false;
            } elseif ($row['quality'] != $lastQuality) {
                if ($curCat) {
                    if (!$usedQuality) {
                        $curCat['name'] = $qualities[$lastQuality] . ' ' . $curCat['name'];
                    }
                    $catData[] = $curCat;
                }
                $curCat = [
                    'name' => $qualities[$row['quality']] . ' ' . $row['catname'],
                    'useBonus' => false,
                    'items' => [],
                ];

                $usedQuality = true;
                $lastQuality = $row['quality'];
            }
            $curCat['useBonus'] |= in_array($row['class'], [2,4]);
            $curCat['items'][] = $row['id'];
        }
        if ($curCat) {
            $catData[] = $curCat;
        }

        MCSet($cacheKey, $catData);
    }

    $tr = [];

    foreach ($catData as $category) {
        $a = [
            'name' => 'ItemList',
            'data' => [
                'name' => $category['name'],
            ]
        ];
        if ($category['useBonus']) {
            $a['data']['items'] = CategoryBonusItemList($house, 'i.id in (' . implode(',', $category['items']) . ')');
        } else {
            $a['data']['items'] = CategoryRegularItemList($house, 'i.id in (' . implode(',', $category['items']) . ')');
        }
        $tr[] = $a;
    }

    return $tr;
}
