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

$expansions = [
    'Classic',
    'Burning Crusade',
    'Wrath of the Lich King',
    'Cataclysm',
    'Mists of Pandaria',
    'Warlords of Draenor',
    'Legion',
    'Battle for Azeroth',
    'Shadowlands',
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
        'Corruption' => BONUS_STAT_SET_CORRUPTION,
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
                        'joins' => 'join tblAuctionBonus ab on ab.house = a.house and ab.id = a.id join tblDBCItemBonus ib on ib.id = ab.bonus',
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
                    'joins' => 'join tblAuctionBonus ab on ab.house = a.house and ab.id = a.id join tblDBCItemBonus ib on ib.id = ab.bonus',
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

function CategoryResult_corruption($house) {
    $json = <<<'JSON'
{
    "6483": {
        "id": 6483,
        "name_enus": "Avoidant 1",
        "name_dede": "Schlüpfrig 1",
        "name_eses": "Evasivo 1",
        "name_frfr": "Évitant 1",
        "name_itit": "Elusivo 1",
        "name_ptbr": "Evasivo 1",
        "name_ruru": "Избежание 1",
        "name_kokr": "회피술 1",
        "name_zhtw": "Avoidant 1"
    },
    "6484": {
        "id": 6484,
        "name_enus": "Avoidant 2",
        "name_dede": "Schlüpfrig 2",
        "name_eses": "Evasivo 2",
        "name_frfr": "Évitant 2",
        "name_itit": "Elusivo 2",
        "name_ptbr": "Evasivo 2",
        "name_ruru": "Избежание 2",
        "name_kokr": "회피술 2",
        "name_zhtw": "Avoidant 2"
    },
    "6485": {
        "id": 6485,
        "name_enus": "Avoidant 3",
        "name_dede": "Schlüpfrig 3",
        "name_eses": "Evasivo 3",
        "name_frfr": "Évitant 3",
        "name_itit": "Elusivo 3",
        "name_ptbr": "Evasivo 3",
        "name_ruru": "Избежание 3",
        "name_kokr": "회피술 3",
        "name_zhtw": "Avoidant 3"
    },
    "6556": {
        "id": 6556,
        "name_enus": "Deadly Momentum 1",
        "name_dede": "Tödlicher Schwung 1",
        "name_eses": "Inercia mortal 1",
        "name_frfr": "Élan mortel 1",
        "name_itit": "Impeto Letale 1",
        "name_ptbr": "Inércia Mortífera 1",
        "name_ruru": "Смертоносный импульс 1",
        "name_kokr": "죽음의 기운 1",
        "name_zhtw": "Deadly Momentum 1"
    },
    "6561": {
        "id": 6561,
        "name_enus": "Deadly Momentum 2",
        "name_dede": "Tödlicher Schwung 2",
        "name_eses": "Inercia mortal 2",
        "name_frfr": "Élan mortel 2",
        "name_itit": "Impeto Letale 2",
        "name_ptbr": "Inércia Mortífera 2",
        "name_ruru": "Смертоносный импульс 2",
        "name_kokr": "죽음의 기운 2",
        "name_zhtw": "Deadly Momentum 2"
    },
    "6562": {
        "id": 6562,
        "name_enus": "Deadly Momentum 3",
        "name_dede": "Tödlicher Schwung 3",
        "name_eses": "Inercia mortal 3",
        "name_frfr": "Élan mortel 3",
        "name_itit": "Impeto Letale 3",
        "name_ptbr": "Inércia Mortífera 3",
        "name_ruru": "Смертоносный импульс 3",
        "name_kokr": "죽음의 기운 3",
        "name_zhtw": "Deadly Momentum 3"
    },
    "6567": {
        "id": 6567,
        "name_enus": "Devour Vitality",
        "name_dede": "Vitalität verschlingen",
        "name_eses": "Devorar vitalidad",
        "name_frfr": "Dévorer la vitalité",
        "name_itit": "Divoramento Vitalità",
        "name_ptbr": "Devorar Vitalidade",
        "name_ruru": "Поглощение жизненной силы",
        "name_kokr": "활력 섭취",
        "name_zhtw": "Devour Vitality"
    },
    "6549": {
        "id": 6549,
        "name_enus": "Echoing Void 1",
        "name_dede": "Widerhallende Leere 1",
        "name_eses": "Vacío resonante 1",
        "name_frfr": "Vide résonnant 1",
        "name_itit": "Vuoto Riecheggiante 1",
        "name_ptbr": "Caos Ecoante 1",
        "name_ruru": "Эхо Бездны 1",
        "name_kokr": "메아리치는 공허 1",
        "name_zhtw": "Echoing Void 1"
    },
    "6550": {
        "id": 6550,
        "name_enus": "Echoing Void 2",
        "name_dede": "Widerhallende Leere 2",
        "name_eses": "Vacío resonante 2",
        "name_frfr": "Vide résonnant 2",
        "name_itit": "Vuoto Riecheggiante 2",
        "name_ptbr": "Caos Ecoante 2",
        "name_ruru": "Эхо Бездны 2",
        "name_kokr": "메아리치는 공허 2",
        "name_zhtw": "Echoing Void 2"
    },
    "6551": {
        "id": 6551,
        "name_enus": "Echoing Void 3",
        "name_dede": "Widerhallende Leere 3",
        "name_eses": "Vacío resonante 3",
        "name_frfr": "Vide résonnant 3",
        "name_itit": "Vuoto Riecheggiante 3",
        "name_ptbr": "Caos Ecoante 3",
        "name_ruru": "Эхо Бездны 3",
        "name_kokr": "메아리치는 공허 3",
        "name_zhtw": "Echoing Void 3"
    },
    "6474": {
        "id": 6474,
        "name_enus": "Expedient 1",
        "name_dede": "Entschlossen 1",
        "name_eses": "Expeditivo 1",
        "name_frfr": "Efficace 1",
        "name_itit": "Espediente 1",
        "name_ptbr": "Expedito 1",
        "name_ruru": "Скорость 1",
        "name_kokr": "쾌속 1",
        "name_zhtw": "Expedient 1"
    },
    "6475": {
        "id": 6475,
        "name_enus": "Expedient 2",
        "name_dede": "Entschlossen 2",
        "name_eses": "Expeditivo 2",
        "name_frfr": "Efficace 2",
        "name_itit": "Espediente 2",
        "name_ptbr": "Expedito 2",
        "name_ruru": "Скорость 2",
        "name_kokr": "쾌속 2",
        "name_zhtw": "Expedient 2"
    },
    "6476": {
        "id": 6476,
        "name_enus": "Expedient 3",
        "name_dede": "Entschlossen 3",
        "name_eses": "Expeditivo 3",
        "name_frfr": "Efficace 3",
        "name_itit": "Espediente 3",
        "name_ptbr": "Expedito 3",
        "name_ruru": "Скорость 3",
        "name_kokr": "쾌속 3",
        "name_zhtw": "Expedient 3"
    },
    "6570": {
        "id": 6570,
        "name_enus": "Flash of Insight",
        "name_dede": "Moment der Eingebung",
        "name_eses": "Destello de perspicacia",
        "name_frfr": "Éclair de clairvoyance",
        "name_itit": "Lampo di Consapevolezza",
        "name_ptbr": "Lampejo de Perspicácia",
        "name_ruru": "Внезапное озарение",
        "name_kokr": "번뜩이는 통찰",
        "name_zhtw": "Flash of Insight"
    },
    "6546": {
        "id": 6546,
        "name_enus": "Glimpse of Clarity",
        "name_dede": "Augenblick der Klarheit",
        "name_eses": "Atisbo de claridad",
        "name_frfr": "Éclair de lucidité",
        "name_itit": "Barlume di Lucidità",
        "name_ptbr": "Vislumbre de Clareza",
        "name_ruru": "Вспышка ясности",
        "name_kokr": "번뜩이는 명료함",
        "name_zhtw": "Glimpse of Clarity"
    },
    "6486": {
        "id": 6486,
        "name_enus": "Glimpse of Clarity",
        "name_dede": "Augenblick der Klarheit",
        "name_eses": "Atisbo de claridad",
        "name_frfr": "Éclair de lucidité",
        "name_itit": "Barlume di Lucidità",
        "name_ptbr": "Vislumbre de Clareza",
        "name_ruru": "Вспышка ясности",
        "name_kokr": "찰나의 명료함",
        "name_zhtw": "Glimpse of Clarity"
    },
    "6573": {
        "id": 6573,
        "name_enus": "Gushing Wound",
        "name_dede": "Klaffende Wunde",
        "name_eses": "Herida sangrante",
        "name_frfr": "Blessure hémorragique",
        "name_itit": "Ferita Zampillante",
        "name_ptbr": "Ferida Torrente",
        "name_ruru": "Кровоточащая рана",
        "name_kokr": "상처 출혈",
        "name_zhtw": "Gushing Wound"
    },
    "6557": {
        "id": 6557,
        "name_enus": "Honed Mind 1",
        "name_dede": "Geschärfter Verstand 1",
        "name_eses": "Mente aguda 1",
        "name_frfr": "Esprit affûté 1",
        "name_itit": "Mente Concentrata 1",
        "name_ptbr": "Mente Afiada 1",
        "name_ruru": "Сфокусированное сознание 1",
        "name_kokr": "단련된 정신 1",
        "name_zhtw": "Honed Mind 1"
    },
    "6563": {
        "id": 6563,
        "name_enus": "Honed Mind 2",
        "name_dede": "Geschärfter Verstand 2",
        "name_eses": "Mente aguda 2",
        "name_frfr": "Esprit affûté 2",
        "name_itit": "Mente Concentrata 2",
        "name_ptbr": "Mente Afiada 2",
        "name_ruru": "Сфокусированное сознание 2",
        "name_kokr": "단련된 정신 2",
        "name_zhtw": "Honed Mind 2"
    },
    "6564": {
        "id": 6564,
        "name_enus": "Honed Mind 3",
        "name_dede": "Geschärfter Verstand 3",
        "name_eses": "Mente aguda 3",
        "name_frfr": "Esprit affûté 3",
        "name_itit": "Mente Concentrata 3",
        "name_ptbr": "Mente Afiada 3",
        "name_ruru": "Сфокусированное сознание 3",
        "name_kokr": "단련된 정신 3",
        "name_zhtw": "Honed Mind 3"
    },
    "6547": {
        "id": 6547,
        "name_enus": "Ineffable Truth 1",
        "name_dede": "Unbeschreibliche Wahrheit 1",
        "name_eses": "Verdad indescriptible 1",
        "name_frfr": "Vérité ineffable 1",
        "name_itit": "Verità Ineffabile 1",
        "name_ptbr": "Verdade Inefável 1",
        "name_ruru": "Невыразимая истина 1",
        "name_kokr": "형언할 수 없는 진실 1",
        "name_zhtw": "Ineffable Truth 1"
    },
    "6548": {
        "id": 6548,
        "name_enus": "Ineffable Truth 2",
        "name_dede": "Unbeschreibliche Wahrheit 2",
        "name_eses": "Verdad indescriptible 2",
        "name_frfr": "Vérité ineffable 2",
        "name_itit": "Verità Ineffabile 2",
        "name_ptbr": "Verdade Inefável 2",
        "name_ruru": "Невыразимая истина 2",
        "name_kokr": "형언할 수 없는 진실 2",
        "name_zhtw": "Ineffable Truth 2"
    },
    "6552": {
        "id": 6552,
        "name_enus": "Infinite Stars 1",
        "name_dede": "Unendliche Sterne 1",
        "name_eses": "Estrellas del infinito 1",
        "name_frfr": "Étoiles infinies 1",
        "name_itit": "Stelle Infinite 1",
        "name_ptbr": "Estrelas Infinitas 1",
        "name_ruru": "Бесконечные звезды 1",
        "name_kokr": "무한의 별 1",
        "name_zhtw": "Infinite Stars 1"
    },
    "6553": {
        "id": 6553,
        "name_enus": "Infinite Stars 2",
        "name_dede": "Unendliche Sterne 2",
        "name_eses": "Estrellas del infinito 2",
        "name_frfr": "Étoiles infinies 2",
        "name_itit": "Stelle Infinite 2",
        "name_ptbr": "Estrelas Infinitas 2",
        "name_ruru": "Бесконечные звезды 2",
        "name_kokr": "무한의 별 2",
        "name_zhtw": "Infinite Stars 2"
    },
    "6554": {
        "id": 6554,
        "name_enus": "Infinite Stars 3",
        "name_dede": "Unendliche Sterne 3",
        "name_eses": "Estrellas del infinito 3",
        "name_frfr": "Étoiles infinies 3",
        "name_itit": "Stelle Infinite 3",
        "name_ptbr": "Estrelas Infinitas 3",
        "name_ruru": "Бесконечные звезды 3",
        "name_kokr": "무한의 별 3",
        "name_zhtw": "Infinite Stars 3"
    },
    "6569": {
        "id": 6569,
        "name_enus": "Lash of the Void",
        "name_dede": "Peitsche der Leere",
        "name_eses": "Latigazo del Vacío",
        "name_frfr": "Fouet du Vide",
        "name_itit": "Sferzata del Vuoto",
        "name_ptbr": "Açoite do Caos",
        "name_ruru": "Плеть Бездны",
        "name_kokr": "공허의 채찍",
        "name_zhtw": "Lash of the Void"
    },
    "6471": {
        "id": 6471,
        "name_enus": "Masterful 1",
        "name_dede": "Meisterhaft 1",
        "name_eses": "Magistral 1",
        "name_frfr": "Magistral 1",
        "name_itit": "Magistrale 1",
        "name_ptbr": "Primoroso 1",
        "name_ruru": "Искусность 1",
        "name_kokr": "능수능란 1",
        "name_zhtw": "Masterful 1"
    },
    "6472": {
        "id": 6472,
        "name_enus": "Masterful 2",
        "name_dede": "Meisterhaft 2",
        "name_eses": "Magistral 2",
        "name_frfr": "Magistral 2",
        "name_itit": "Magistrale 2",
        "name_ptbr": "Primoroso 2",
        "name_ruru": "Искусность 2",
        "name_kokr": "능수능란 2",
        "name_zhtw": "Masterful 2"
    },
    "6473": {
        "id": 6473,
        "name_enus": "Masterful 3",
        "name_dede": "Meisterhaft 3",
        "name_eses": "Magistral 3",
        "name_frfr": "Magistral 3",
        "name_itit": "Magistrale 3",
        "name_ptbr": "Primoroso 3",
        "name_ruru": "Искусность 3",
        "name_kokr": "능수능란 3",
        "name_zhtw": "Masterful 3"
    },
    "6572": {
        "id": 6572,
        "name_enus": "Obsidian Skin",
        "name_dede": "Obsidianhaut",
        "name_eses": "Piel de obsidiana",
        "name_frfr": "Peau d’obsidienne",
        "name_itit": "Pelle d'Ossidiana",
        "name_ptbr": "Pele de Obsidiana",
        "name_ruru": "Обсидиановая кожа",
        "name_kokr": "흑요석 피부",
        "name_zhtw": "Obsidian Skin"
    },
    "6555": {
        "id": 6555,
        "name_enus": "Racing Pulse 1",
        "name_dede": "Rasender Puls 1",
        "name_eses": "Pulso acelerado 1",
        "name_frfr": "Emballement du pouls 1",
        "name_itit": "Impulso di Corsa 1",
        "name_ptbr": "Pulso Acelerado 1",
        "name_ruru": "Учащенное сердцебиение 1",
        "name_kokr": "질주하는 맥박 1",
        "name_zhtw": "Racing Pulse 1"
    },
    "6559": {
        "id": 6559,
        "name_enus": "Racing Pulse 2",
        "name_dede": "Rasender Puls 2",
        "name_eses": "Pulso acelerado 2",
        "name_frfr": "Emballement du pouls 2",
        "name_itit": "Impulso di Corsa 2",
        "name_ptbr": "Pulso Acelerado 2",
        "name_ruru": "Учащенное сердцебиение 2",
        "name_kokr": "질주하는 맥박 2",
        "name_zhtw": "Racing Pulse 2"
    },
    "6560": {
        "id": 6560,
        "name_enus": "Racing Pulse 3",
        "name_dede": "Rasender Puls 3",
        "name_eses": "Pulso acelerado 3",
        "name_frfr": "Emballement du pouls 3",
        "name_itit": "Impulso di Corsa 3",
        "name_ptbr": "Pulso Acelerado 3",
        "name_ruru": "Учащенное сердцебиение 3",
        "name_kokr": "질주하는 맥박 3",
        "name_zhtw": "Racing Pulse 3"
    },
    "6571": {
        "id": 6571,
        "name_enus": "Searing Flames",
        "name_dede": "Sengende Flammen",
        "name_eses": "Llamas abrasadoras",
        "name_frfr": "Flammes incendiaires",
        "name_itit": "Fiamme Ustionanti",
        "name_ptbr": "Chamas Calcinantes",
        "name_ruru": "Жгучее пламя",
        "name_kokr": "이글거리는 불길",
        "name_zhtw": "Searing Flames"
    },
    "6480": {
        "id": 6480,
        "name_enus": "Severe 1",
        "name_dede": "Schwerwiegend 1",
        "name_eses": "Severo 1",
        "name_frfr": "Drastique 1",
        "name_itit": "Grave 1",
        "name_ptbr": "Grave 1",
        "name_ruru": "Суровость 1",
        "name_kokr": "가혹 1",
        "name_zhtw": "Severe 1"
    },
    "6481": {
        "id": 6481,
        "name_enus": "Severe 2",
        "name_dede": "Schwerwiegend 2",
        "name_eses": "Severo 2",
        "name_frfr": "Drastique 2",
        "name_itit": "Grave 2",
        "name_ptbr": "Grave 2",
        "name_ruru": "Суровость 2",
        "name_kokr": "가혹 2",
        "name_zhtw": "Severe 2"
    },
    "6482": {
        "id": 6482,
        "name_enus": "Severe 3",
        "name_dede": "Schwerwiegend 3",
        "name_eses": "Severo 3",
        "name_frfr": "Drastique 3",
        "name_itit": "Grave 3",
        "name_ptbr": "Grave 3",
        "name_ruru": "Суровость 3",
        "name_kokr": "가혹 3",
        "name_zhtw": "Severe 3"
    },
    "6493": {
        "id": 6493,
        "name_enus": "Siphoner 1",
        "name_dede": "Schröpfer 1",
        "name_eses": "Succionador 1",
        "name_frfr": "Siphonneur 1",
        "name_itit": "Aspirante 1",
        "name_ptbr": "Canalizador 1",
        "name_ruru": "Вытягивание 1",
        "name_kokr": "착취자 1",
        "name_zhtw": "Siphoner 1"
    },
    "6494": {
        "id": 6494,
        "name_enus": "Siphoner 2",
        "name_dede": "Schröpfer 2",
        "name_eses": "Succionador 2",
        "name_frfr": "Siphonneur 2",
        "name_itit": "Aspirante 2",
        "name_ptbr": "Canalizador 2",
        "name_ruru": "Вытягивание 2",
        "name_kokr": "착취자 2",
        "name_zhtw": "Siphoner 2"
    },
    "6495": {
        "id": 6495,
        "name_enus": "Siphoner 3",
        "name_dede": "Schröpfer 3",
        "name_eses": "Succionador 3",
        "name_frfr": "Siphonneur 3",
        "name_itit": "Aspirante 3",
        "name_ptbr": "Canalizador 3",
        "name_ruru": "Вытягивание 3",
        "name_kokr": "착취자 3",
        "name_zhtw": "Siphoner 3"
    },
    "6437": {
        "id": 6437,
        "name_enus": "Strikethrough 1",
        "name_dede": "Durchstoß 1",
        "name_eses": "Golpe penetrante 1",
        "name_frfr": "Invalidation 1",
        "name_itit": "Cancellante 1",
        "name_ptbr": "Riscado 1",
        "name_ruru": "Преодоление защиты 1",
        "name_kokr": "강행 돌파 1",
        "name_zhtw": "Strikethrough 1"
    },
    "6438": {
        "id": 6438,
        "name_enus": "Strikethrough 2",
        "name_dede": "Durchstoß 2",
        "name_eses": "Golpe penetrante 2",
        "name_frfr": "Invalidation 2",
        "name_itit": "Cancellante 2",
        "name_ptbr": "Riscado 2",
        "name_ruru": "Преодоление защиты 2",
        "name_kokr": "강행 돌파 2",
        "name_zhtw": "Strikethrough 2"
    },
    "6439": {
        "id": 6439,
        "name_enus": "Strikethrough 3",
        "name_dede": "Durchstoß 3",
        "name_eses": "Golpe penetrante 3",
        "name_frfr": "Invalidation 3",
        "name_itit": "Cancellante 3",
        "name_ptbr": "Riscado 3",
        "name_ruru": "Преодоление защиты 3",
        "name_kokr": "강행 돌파 3",
        "name_zhtw": "Strikethrough 3"
    },
    "6558": {
        "id": 6558,
        "name_enus": "Surging Vitality 1",
        "name_dede": "Strömende Lebenskraft 1",
        "name_eses": "Vitalidad emergente 1",
        "name_frfr": "Déferlement de vitalité 1",
        "name_itit": "Vitalità Crescente 1",
        "name_ptbr": "Vitalidade Fervilhante 1",
        "name_ruru": "Прилив жизненной силы 1",
        "name_kokr": "솟구치는 활력 1",
        "name_zhtw": "Surging Vitality 1"
    },
    "6565": {
        "id": 6565,
        "name_enus": "Surging Vitality 2",
        "name_dede": "Strömende Lebenskraft 2",
        "name_eses": "Vitalidad emergente 2",
        "name_frfr": "Déferlement de vitalité 2",
        "name_itit": "Vitalità Crescente 2",
        "name_ptbr": "Vitalidade Fervilhante 2",
        "name_ruru": "Прилив жизненной силы 2",
        "name_kokr": "솟구치는 활력 2",
        "name_zhtw": "Surging Vitality 2"
    },
    "6566": {
        "id": 6566,
        "name_enus": "Surging Vitality 3",
        "name_dede": "Strömende Lebenskraft 3",
        "name_eses": "Vitalidad emergente 3",
        "name_frfr": "Déferlement de vitalité 3",
        "name_itit": "Vitalità Crescente 3",
        "name_ptbr": "Vitalidade Fervilhante 3",
        "name_ruru": "Прилив жизненной силы 3",
        "name_kokr": "솟구치는 활력 3",
        "name_zhtw": "Surging Vitality 3"
    },
    "6537": {
        "id": 6537,
        "name_enus": "Twilight Devastation 1",
        "name_dede": "Zwielichtverwüstung 1",
        "name_eses": "Devastación crepuscular 1",
        "name_frfr": "Dévastation du Crépuscule 1",
        "name_itit": "Devastazione del Crepuscolo 1",
        "name_ptbr": "Devastação do Crepúsculo 1",
        "name_ruru": "Сумеречное разрушение 1",
        "name_kokr": "황혼의 파멸 1",
        "name_zhtw": "Twilight Devastation 1"
    },
    "6538": {
        "id": 6538,
        "name_enus": "Twilight Devastation 2",
        "name_dede": "Zwielichtverwüstung 2",
        "name_eses": "Devastación crepuscular 2",
        "name_frfr": "Dévastation du Crépuscule 2",
        "name_itit": "Devastazione del Crepuscolo 2",
        "name_ptbr": "Devastação do Crepúsculo 2",
        "name_ruru": "Сумеречное разрушение 2",
        "name_kokr": "황혼의 파멸 2",
        "name_zhtw": "Twilight Devastation 2"
    },
    "6539": {
        "id": 6539,
        "name_enus": "Twilight Devastation 3",
        "name_dede": "Zwielichtverwüstung 3",
        "name_eses": "Devastación crepuscular 3",
        "name_frfr": "Dévastation du Crépuscule 3",
        "name_itit": "Devastazione del Crepuscolo 3",
        "name_ptbr": "Devastação do Crepúsculo 3",
        "name_ruru": "Сумеречное разрушение 3",
        "name_kokr": "황혼의 파멸 3",
        "name_zhtw": "Twilight Devastation 3"
    },
    "6543": {
        "id": 6543,
        "name_enus": "Twisted Appendage 1",
        "name_dede": "Entstellte Gliedmaße 1",
        "name_eses": "Apéndice retorcido 1",
        "name_frfr": "Appendice dénaturé 1",
        "name_itit": "Appendice Distorta 1",
        "name_ptbr": "Apêndice Retorcido 1",
        "name_ruru": "Искаженный отросток 1",
        "name_kokr": "뒤틀린 신체 부위 1",
        "name_zhtw": "Twisted Appendage 1"
    },
    "6544": {
        "id": 6544,
        "name_enus": "Twisted Appendage 2",
        "name_dede": "Entstellte Gliedmaße 2",
        "name_eses": "Apéndice retorcido 2",
        "name_frfr": "Appendice dénaturé 2",
        "name_itit": "Appendice Distorta 2",
        "name_ptbr": "Apêndice Retorcido 2",
        "name_ruru": "Искаженный отросток 2",
        "name_kokr": "뒤틀린 신체 부위 2",
        "name_zhtw": "Twisted Appendage 2"
    },
    "6545": {
        "id": 6545,
        "name_enus": "Twisted Appendage 3",
        "name_dede": "Entstellte Gliedmaße 3",
        "name_eses": "Apéndice retorcido 3",
        "name_frfr": "Appendice dénaturé 3",
        "name_itit": "Appendice Distorta 3",
        "name_ptbr": "Apêndice Retorcido 3",
        "name_ruru": "Искаженный отросток 3",
        "name_kokr": "뒤틀린 신체 부위 3",
        "name_zhtw": "Twisted Appendage 3"
    },
    "6477": {
        "id": 6477,
        "name_enus": "Versatile 1",
        "name_dede": "Vielseitig 1",
        "name_eses": "Versátil 1",
        "name_frfr": "Polyvalent 1",
        "name_itit": "Versatile 1",
        "name_ptbr": "Versátil 1",
        "name_ruru": "Универсальность 1",
        "name_kokr": "다재다능 1",
        "name_zhtw": "Versatile 1"
    },
    "6478": {
        "id": 6478,
        "name_enus": "Versatile 2",
        "name_dede": "Vielseitig 2",
        "name_eses": "Versátil 2",
        "name_frfr": "Polyvalent 2",
        "name_itit": "Versatile 2",
        "name_ptbr": "Versátil 2",
        "name_ruru": "Универсальность 2",
        "name_kokr": "다재다능 2",
        "name_zhtw": "Versatile 2"
    },
    "6479": {
        "id": 6479,
        "name_enus": "Versatile 3",
        "name_dede": "Vielseitig 3",
        "name_eses": "Versátil 3",
        "name_frfr": "Polyvalent 3",
        "name_itit": "Versatile 3",
        "name_ptbr": "Versátil 3",
        "name_ruru": "Универсальность 3",
        "name_kokr": "다재다능 3",
        "name_zhtw": "Versatile 3"
    },
    "6540": {
        "id": 6540,
        "name_enus": "Void Ritual 1",
        "name_dede": "Leerenritual 1",
        "name_eses": "Ritual del Vacío 1",
        "name_frfr": "Rituel du Vide 1",
        "name_itit": "Rituale del Vuoto 1",
        "name_ptbr": "Ritual do Caos 1",
        "name_ruru": "Ритуал Бездны 1",
        "name_kokr": "공허의 의식 1",
        "name_zhtw": "Void Ritual 1"
    },
    "6541": {
        "id": 6541,
        "name_enus": "Void Ritual 2",
        "name_dede": "Leerenritual 2",
        "name_eses": "Ritual del Vacío 2",
        "name_frfr": "Rituel du Vide 2",
        "name_itit": "Rituale del Vuoto 2",
        "name_ptbr": "Ritual do Caos 2",
        "name_ruru": "Ритуал Бездны 2",
        "name_kokr": "공허의 의식 2",
        "name_zhtw": "Void Ritual 2"
    },
    "6542": {
        "id": 6542,
        "name_enus": "Void Ritual 3",
        "name_dede": "Leerenritual 3",
        "name_eses": "Ritual del Vacío 3",
        "name_frfr": "Rituel du Vide 3",
        "name_itit": "Rituale del Vuoto 3",
        "name_ptbr": "Ritual do Caos 3",
        "name_ruru": "Ритуал Бездны 3",
        "name_kokr": "공허의 의식 3",
        "name_zhtw": "Void Ritual 3"
    },
    "6568": {
        "id": 6568,
        "name_enus": "Whispered Truths",
        "name_dede": "Geflüsterte Wahrheiten",
        "name_eses": "Verdades susurradas",
        "name_frfr": "Vérités murmurées",
        "name_itit": "Verità Sussurrate",
        "name_ptbr": "Verdades Sussurradas",
        "name_ruru": "Шепот истины",
        "name_kokr": "진실의 속삭임",
        "name_zhtw": "Whispered Truths"
    }
}
JSON;

    $bonuses = json_decode($json, true);
    $result = [
        'name'    => 'corruption',
        'results' => [],
    ];
    $result['results'][] = [
        'name' => 'BonusRegionSearch',
        'data' => [
            'items' => CategoryBonusRegionAuctionList($house, 'ab.bonus in (' . implode(',', array_keys($bonuses)) . ')'),
            'bonuses' => $bonuses,
        ],
    ];

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
                'data' => ['name'  => 'Shadowlands Ore',
                           'items' => CategoryRegularItemList($house, 'i.id in (171833, 177061, 171831, 171828, 171830, 171829, 171832, 171841, 171840)')
                ]
            ],
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

function CategoryResult_skinning($house) {

    $tr = ['name' => 'skinning', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Shadowlands Trade Goods',
            'items' => CategoryRegularItemList($house, 'i.id in (172097, 172094, 172096, 172089, 172092, 177279)'),
        ],
    ];

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

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Pandarian Trade Goods',
            'items' => CategoryRegularItemList($house, 'i.id in (72120,72163,79101,72162,98617)'),
        ],
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Cataclysm Trade Goods',
            'items' => CategoryRegularItemList($house, 'i.id in (52979,52980,52982,56516,52976)'),
        ],
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Northrend Trade Goods',
            'items' => CategoryRegularItemList($house, 'i.id in (44128,38558,38425,33568,38557,38561,33567)'),
        ],
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Outland Trade Goods',
            'items' => CategoryRegularItemList($house, 'i.id in (25707,23793,21887,25649,25699,25700,25708,29539,29547,29548)'),
        ],
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Classic Trade Goods',
            'items' => CategoryRegularItemList($house, 'i.id in (15407,15409,12810,8171,4234,8170,8150,15417,4304,8167,2319,15416,4235,15408,2318,4461,17012,8165,5784,7392,8154,15414,783,20381,15419,4289,15410,19767,15412,5082,8172,4232,4233,4236,5785,2934,19768,15415,7286,4231,8169)'),
        ],
    ];

    return $tr;
}

function CategoryResult_herbalism($house)
{
    $tr = ['name' => 'herbalism', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Shadowlands Herbs',
            'items' => CategoryRegularItemList($house, 'i.id in (168586, 170554, 168589, 168583, 169701, 171315)'),
        ],
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Ground Shadowlands Herbs',
            'items' => CategoryRegularItemList($house, 'i.id in (171287, 171290, 171288, 171291, 171289, 171292)'),
        ],
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Battle for Azeroth Herbs',
            'items' => CategoryRegularItemList($house, '(i.id between 152505 and 152511 or i.id in (168487))'),
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
            'name'  => 'Pandarian Herbs',
            'items' => CategoryRegularItemList($house, 'i.id in (72234,72237,72238,79011,79010,72235)'),
        ],
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Cataclysm Herbs',
            'items' => CategoryRegularItemList($house, 'i.id in (52985,52983,52988,52986,52984,52987)'),
        ],
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Northrend Herbs',
            'items' => CategoryRegularItemList($house, 'i.id in (36908,36905,36906,36903,39970,36901,36904,36907,37921)'),
        ],
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Outland Herbs',
            'items' => CategoryRegularItemList($house, 'i.id in (22794,22788,22790,22792,22793,22791,22786,22789,22785,22787,22797,22710)'),
        ],
    ];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Classic Herbs',
            'items' => CategoryRegularItemList($house, 'i.id in (3820,8153,8831,4625,3821,2447,13466,8838,2450,3818,3357,2452,3355,8845,2449,13468,13465,8839,765,8846,3356,13464,13467,3819,8836,3369,13463,2453,3358,785)'),
        ],
    ];

    return $tr;
}

function CategoryResult_alchemy($house) {

    $tr = ['name' => 'alchemy', 'results' => []];

    $tr['results'] = array_merge($tr['results'], CategoryTradeskillResults($house, 171, null, [], [1294]));
    $tr['results'] = array_merge($tr['results'], CategoryTradeskillResults($house, 171, null, [], [592]));

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

function CategoryResult_leatherworking($house) {
    $tr = ['name' => 'leatherworking', 'results' => []];

    $tr['results'] = array_merge($tr['results'], CategoryTradeskillResults($house, 165, 8));
    $tr['results'] = array_merge($tr['results'], CategoryTradeskillResults($house, 165, 7));

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

function CategoryResult_blacksmithing($house) {
    $tr = ['name' => 'blacksmithing', 'results' => []];
    $sortIndex = 0;

    $tr['results'] = array_merge($tr['results'], CategoryTradeskillResults($house, 164, 8));
    $tr['results'] = array_merge($tr['results'], CategoryTradeskillResults($house, 164, 7));

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
            'name'  => 'Shadowlands Uncut Gems',
            'items' => CategoryRegularItemList($house, 'i.id in (173109, 173108, 173110)')
        ]
    ];

    $tr['results'] = array_merge($tr['results'], CategoryTradeskillResults($house, 755, 8));

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
            'items' => CategoryRegularItemList($house, 'i.id in (153706, 168635, 168193, 168189, 168188, 168192, 168191, 168190)')
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
                    'items' => CategoryBonusItemList($house, "i.id in ($itemCsv) and i.id not in (161930,161931,153506,159936, 167997, 164696, 164679, 167996)")
                ]
            ];
            if ($setName == 'Battle for Azeroth Weapons') {
                $tr['results'][] = [
                    'name' => 'ItemList',
                    'data' => [
                        'name'  => 'Alliance Guns',
                        'items' => CategoryBonusItemList($house, "i.id in (161930,161931, 167997, 164696)")
                    ]
                ];
                $tr['results'][] = [
                    'name' => 'ItemList',
                    'data' => [
                        'name'  => 'Horde Guns',
                        'items' => CategoryBonusItemList($house, "i.id in (153506,159936, 164679, 167996)")
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

function CategoryResult_tailoring($house) {
    $tr = ['name' => 'tailoring', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => 'Cloth',
            'items' => CategoryRegularItemList($house, 'i.id in (2589,2592,4306,4338,14047,21877,33470,53010,72988,111557,124437,151567,152576,152577,167738,173202,173204,172439)')
        ]
    ];

    $tr['results'] = array_merge($tr['results'], CategoryTradeskillResults($house, 197, null, [], [1395]));
    $tr['results'] = array_merge($tr['results'], CategoryTradeskillResults($house, 197, null, [], [942]));

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
        [172230,172231,172232],
    ];

    $tr = ['name' => 'enchanting', 'results' => []];

    $tr['results'][] = [
        'name' => 'ItemList',
        'data' => [
            'name'  => $expansions[count($mats) - 1] . ' Reagents',
            'items' => CategoryRegularItemList($house, 'i.id in (' . implode(',', array_pop($mats)) . ')')
        ]
    ];

    $tr['results'] = array_merge($tr['results'], CategoryTradeskillResults($house, 333, 8));

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

    $tr['results'] = array_merge($tr['results'], CategoryTradeskillResults($house, 773, 8));
    $tr['results'] = array_merge($tr['results'], CategoryTradeskillResults($house, 773, 7));

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

    $current = count($expansions) - 1;

    $foods = array_merge([
        $expansions[$current] . ' Meat' => '172052, 172053, 172054, 172055, 179314, 179315',
        $expansions[$current] . ' Fish' => '173032, 173033, 173034, 173035, 173036, 173037',
        ],
        CategoryGetTradeItemsInExpansion(185, $current)
    );

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
(select group_concat(ab.bonus order by 1 separator ':')
 from tblAuctionBonus ab
 where ab.house = a.house
 and ab.id = a.id) as bonusurl
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

function CategoryBonusRegionAuctionList($house, $params) {
    global $canCache;

    $region = GetRegion($house);
    $cacheKey = 'category_bonusRegionAuction2_' . md5(json_encode([$region, $params]));

    if ($canCache && (($tr = MCGet($cacheKey)) !== false)) {
        PopulateLocaleCols($tr, [
            ['func' => 'GetItemNames',     'key' => 'id',       'name' => 'name'],
        ]);
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
select a.house, i.id, i.icon, i.class as classid, i.subclass, i.type, a.quantity,
ifnull(if(ae.level < $minPricingLevel, null, ae.level), i.level) level,
i.level baselevel, a.buy price,
ae.lootedlevel, ae.`rand`, ae.seed,
(select group_concat(ab.bonus order by 1 separator ':')
 from tblAuctionBonus ab
 where ab.house = a.house
 and ab.id = a.id) as bonusurl
$cols
from tblDBCItem i
join tblAuction a on a.item = i.id
join tblRealm r on a.house = r.house and r.canonical is not null and r.region = ?
left join tblAuctionExtra ae on a.house = ae.house and a.id = ae.id
join tblAuctionBonus ab on ab.house = a.house and ab.id = a.id
$joins
where ifnull(i.auctionable,1) = 1
$where
EOF;

    $stmt = $db->stmt_init();
    if (!$stmt->prepare($sql)) {
        DebugMessage("Bad SQL: \n" . $sql, E_USER_ERROR);
    }
    $stmt->bind_param('s', $region);
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

    MCSet($cacheKey, $tr, 60*60);

    PopulateLocaleCols($tr, [
        ['func' => 'GetItemNames',     'key' => 'id',       'name' => 'name'],
    ]);

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

function CategoryTradeskillResults($house, $skillLine, $expansionId, $excludeCategories = [], $categoryParents = []) {
    global $canCache, $qualities;

    $cacheKey = sprintf('category_tradeskill_ids-2_%d_%d_%s_%s',
        $skillLine, $expansionId ?? -1, md5(implode(',', $excludeCategories)), md5(implode(',', $categoryParents)));

    $catData = false;
    if ($canCache) {
        $catData = MCGet($cacheKey);
    }

    if (!$catData) {
        $db = DBConnect();

        $excludeSql = '';
        if ($excludeCategories) {
            $excludeSql .= ' and tsc.id not in (' . implode(',', $excludeCategories) . ')';
        }
        if ($categoryParents) {
            $excludeSql .= ' and tsc.parent in (' . implode(',', $categoryParents) . ')';
        }

        $sql = <<<SQL
select i.id, i.quality, i.class, concat_ws(' ', 
	if(instr(i.name_enus, 'Kul Tiran'), 'Kul Tiran', null),
	if(instr(i.name_enus, 'Zandalari'), 'Zandalari', null),
	tsc.name) catname
from tblDBCSpell s
join tblDBCTradeSkillCategory tsc on s.tradeskillcategory = tsc.id
join tblDBCSpellCrafts xsc on xsc.spell = s.id join tblDBCItem i on xsc.item = i.id
where s.skillline=? and ifnull(s.expansion, -1) = ifnull(?, ifnull(s.expansion, -1)) and i.auctionable=1 {$excludeSql}
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
