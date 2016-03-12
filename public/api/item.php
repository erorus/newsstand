<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house']) || !isset($_GET['item'])) {
    json_return(array());
}

$house = intval($_GET['house'], 10);
$item = intval($_GET['item'], 10);

if (!$item) {
    json_return(array());
}

BotCheck();
HouseETag($house);

$stats = ItemStats($house, $item);
if (count($stats) == 0) {
    json_return([]);
}
$json = array(
    'stats'         => $stats,
    'history'       => ItemHistory($house, $item),
    'daily'         => ItemHistoryDaily($house, $item),
    'monthly'       => ItemHistoryMonthly($house, $item),
    'auctions'      => ItemAuctions($house, $item),
    'globalnow'     => ItemGlobalNow(GetRegion($house), $item),
    'globalmonthly' => ItemGlobalMonthly(GetRegion($house), $item),
);

json_return($json);

function ItemStats($house, $item)
{
    global $db, $LANG_LEVEL;

    $cacheKey = 'item_stats_' . $item;

    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $localeCols = LocaleColumns('i.name');
    $bonusTags = LocaleColumns('ifnull(group_concat(ib.`tag%1$s` order by ib.tagpriority separator \' \'), if(ifnull(s.bonusset,0)=0,\'\',concat(\'__LEVEL%1$s__ \', i.level+sum(ifnull(ib.level,0))))) bonustag%1$s', true);
    $bonusTags = strtr($bonusTags, $LANG_LEVEL);
    $sql = <<<EOF
select i.id, $localeCols, i.icon, i.display, i.class as classid, i.subclass, ifnull(max(ib.quality), i.quality) quality, i.level+sum(ifnull(ib.level,0)) level, i.stacksize, i.binds, i.buyfromvendor, i.selltovendor, i.auctionable,
s.price, s.quantity, s.lastseen,
ifnull(s.bonusset,0) bonusset, ifnull(GROUP_CONCAT(bs.`bonus` ORDER BY 1 SEPARATOR ':'), '') bonusurl,
$bonusTags,
ivc.copper vendorprice, ivc.npc vendornpc, ivc.npccount vendornpccount,
GetReagentPrice(s.house, i.id, null) reagentprice
from tblDBCItem i
left join tblItemSummary s on s.house = %d and s.item = i.id
left join tblBonusSet bs on s.bonusset = bs.`set`
left join tblDBCItemBonus ib on ifnull(bs.bonus, i.basebonus) = ib.id
left join tblDBCItemVendorCost ivc on ivc.item = i.id
where i.id = %d
group by s.bonusset
EOF;

    $result = $db->query(sprintf($sql, $house, $item), MYSQLI_USE_RESULT);
    $tr = DBMapArray($result, array('bonusset', null));
    foreach ($tr as &$bonusRow) {
        $bonusRow = array_pop($bonusRow);
    }
    unset($bonusRow);

    MCSetHouse($house, $cacheKey, $tr);

    return $tr;
}

function ItemIsCrafted($item)
{
    global $db;

    $key = 'item_crafted_' . $item;

    if (($tr = MCGet($key)) !== false) {
        return $tr == 'yes';
    }

    DBConnect();

    $tr = 0;

    $stmt = $db->prepare('select count(*) from tblDBCItemReagents where item = ?');
    $stmt->bind_param('i', $item);
    $stmt->execute();
    $stmt->bind_result($tr);
    $stmt->fetch();
    $stmt->close();

    $tr = $tr > 0 ? 'yes' : 'no';

    MCSet($key, $tr);

    return ($tr == 'yes');
}

function ItemHistory($house, $item)
{
    global $db;

    $key = 'item_history_' . $item;

    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    DBConnect();

    if (ItemIsCrafted($item)) {
        $sql = <<<EOF
select bonusset, snapshot, price, quantity, reagentprice
from (select
    if(bonusset = @prevBonusSet, null, @price := null) resetprice,
    (@prevBonusSet := bonusset) as bonusset,
    unix_timestamp(updated) snapshot,
    cast(if(quantity is null, @price, @price := price) as decimal(11,0)) `price`,
    ifnull(quantity,0) as quantity,
    reagentprice
    from (select @price := null, @prevBonusSet := null) priceSetup,
        (select `is`.bonusset, s.updated, ih.quantity, ih.price, GetReagentPrice(s.house, `is`.item, s.updated) reagentprice
        from tblSnapshot s
        join tblItemSummary `is` on `is`.house = s.house
        left join tblItemHistory ih on s.updated = ih.snapshot and ih.house = `is`.house and ih.item = `is`.item and ih.bonusset = `is`.bonusset
        where `is`.item = %d and s.house = %d and s.updated >= timestampadd(day,-%d,now()) and s.flags & 1 = 0
        group by `is`.bonusset, s.updated
        order by `is`.bonusset, s.updated asc
        ) ordered
    ) withoutresets
EOF;
    } else {
        $sql = <<<EOF
select bonusset, snapshot, price, quantity
from (select
    if(bonusset = @prevBonusSet, null, @price := null) resetprice,
    (@prevBonusSet := bonusset) as bonusset,
    unix_timestamp(updated) snapshot,
    cast(if(quantity is null, @price, @price := price) as decimal(11,0)) `price`,
    ifnull(quantity,0) as quantity
    from (select @price := null, @prevBonusSet := null) priceSetup,
        (select `is`.bonusset, s.updated, ih.quantity, ih.price
        from tblSnapshot s
        join tblItemSummary `is` on `is`.house = s.house
        left join tblItemHistory ih on s.updated = ih.snapshot and ih.house = `is`.house and ih.item = `is`.item and ih.bonusset = `is`.bonusset
        where `is`.item = %d and s.house = %d and s.updated >= timestampadd(day,-%d,now()) and s.flags & 1 = 0
        group by `is`.bonusset, s.updated
        order by `is`.bonusset, s.updated asc
        ) ordered
    ) withoutresets
EOF;
    }

    $result = $db->query(sprintf($sql, $item, $house, HISTORY_DAYS));
    $tr = DBMapArray($result, array('bonusset', null));

    foreach ($tr as &$bonusSet) {
        while (count($bonusSet) > 0 && is_null($bonusSet[0]['price'])) {
            array_shift($bonusSet);
        }
    }
    unset($bonusSet);

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function ItemHistoryDaily($house, $item)
{
    global $db;

    $cacheKey = 'item_historydaily2_' . $house . '_' . $item;

    if (($tr = MCGet($cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
select `when` as `date`,
`pricemin` as `silvermin`, `priceavg` as `silveravg`, `pricemax` as `silvermax`,
`pricestart` as `silverstart`, `priceend` as `silverend`,
`quantitymin`, `quantityavg`, `quantitymax`, round(`presence`/255*100,1) as `presence`
from tblItemHistoryDaily
where house = ? and item = ?
and `when` not between '2015-12-16' and '2015-12-22'
order by `when` asc
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
    $stmt->close();

    MCSet($cacheKey, $tr, 60 * 60 * 8);

    return $tr;
}

function ItemHistoryMonthly($house, $item)
{
    global $db;

    $cacheKey = 'item_historymonthly_' . $house . '_' . $item;

    if (($tr = MCGet($cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
select *
from tblItemHistoryMonthly
where house = ? and item = ?
order by `month` asc
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $bonusRows = DBMapArray($result, array('bonusset', null));
    $stmt->close();

    $tr = [];
    foreach ($bonusRows as $bonusSet => &$rows) {
        $prevPrice = 0;
        for ($x = 0; $x < count($rows); $x++) {
            $year = 2014 + floor(($rows[$x]['month'] - 1) / 12);
            $monthNum = $rows[$x]['month'] % 12;
            if ($monthNum == 0) {
                $monthNum = 12;
            }
            $month = ($monthNum < 10 ? '0' : '') . $monthNum;
            for ($dayNum = 1; $dayNum <= 31; $dayNum++) {
                $day = ($dayNum < 10 ? '0' : '') . $dayNum;
                if ($year == 2015 && $monthNum == 12 && ($dayNum >= 16 && $dayNum <= 22)) {
                    continue;
                }
                if (!is_null($rows[$x]['mktslvr' . $day])) {
                    $tr[$bonusSet][] = array('date'     => "$year-$month-$day",
                                  'silver'   => $rows[$x]['mktslvr' . $day],
                                  'quantity' => $rows[$x]['qty' . $day]
                    );
                    $prevPrice = $rows[$x]['mktslvr' . $day];
                } else {
                    if (!checkdate($monthNum, $dayNum, $year)) {
                        break;
                    }
                    if (strtotime("$year-$month-$day") > time()) {
                        break;
                    }
                    if ($prevPrice) {
                        $tr[$bonusSet][] = array('date' => "$year-$month-$day", 'silver' => $prevPrice, 'quantity' => 0);
                    }
                }
            }
        }
    }
    unset($rows);

    MCSet($cacheKey, $tr, 60 * 60 * 8);

    return $tr;
}

function ItemAuctions($house, $item)
{
    global $db, $LANG_LEVEL;

    $cacheKey = 'item_auctions_l_' . $item;

    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $bonusNames = LocaleColumns('ifnull(group_concat(distinct ib.name%1$s order by ib.namepriority desc separator \'|\'), \'\') bonusname%1$s', true);
    $bonusTags = LocaleColumns('ifnull(group_concat(distinct ib.`tag%1$s` order by ib.tagpriority separator \' \'), if(ifnull(bs.set,0)=0,\'\',concat(\'__LEVEL%1$s__ \', i.level+sum(ifnull(ib.level,0))))) bonustag%1$s', true);
    $bonusTags = strtr($bonusTags, $LANG_LEVEL);
    $randNames = LocaleColumns('re.name%1$s randname%1$s', true);

    $sql = <<<EOF
SELECT ifnull(ae.bonusset,0) bonusset, a.quantity, a.bid, a.buy, ifnull(ae.`rand`,0) `rand`, ifnull(ae.seed,0) `seed`, s.realm sellerrealm, ifnull(s.name, '???') sellername,
concat_ws(':',ae.bonus1,ae.bonus2,ae.bonus3,ae.bonus4,ae.bonus5,ae.bonus6) bonuses,
ifnull(GROUP_CONCAT(distinct bs.`bonus` ORDER BY 1 SEPARATOR ':'), '') bonusurl,
$bonusNames,
$bonusTags,
$randNames
FROM `tblAuction` a
join tblDBCItem i on a.item=i.id
left join tblSeller s on a.seller=s.id
left join tblAuctionExtra ae on ae.house=a.house and ae.id=a.id
left join tblBonusSet bs on ae.bonusset = bs.set
left join tblDBCItemBonus ib on ib.id in (ae.bonus1, ae.bonus2, ae.bonus3, ae.bonus4, ae.bonus5, ae.bonus6)
left join tblDBCRandEnchants re on re.id = ae.rand
WHERE a.house=? and a.item=?
group by a.id
EOF;
    // order by buy/quantity, bid/quantity, quantity, s.name, a.id


    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('bonusset', null));
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    return $tr;
}

function ItemGlobalNow($region, $item)
{
    global $db;

    $key = 'item_globalnow3_' . $region . '_' . $item;
    if (($tr = MCGet($key)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
    SELECT i.bonusset, r.house, i.price, i.quantity, unix_timestamp(i.lastseen) as lastseen
FROM `tblItemSummary` i
join tblRealm r on i.house = r.house and r.region = ? and r.canonical is not null
WHERE i.item=?
group by i.bonusset, r.house
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('si', $region, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('bonusset', null));
    $stmt->close();

    MCSet($key, $tr, 60 * 60);

    return $tr;
}


function ItemGlobalMonthly($region, $item)
{
    global $db;

    $key = 'item_globalmonthly2_' . $region . '_' . $item;
    if (($tr = MCGet($key)) !== false) {
        return $tr;
    }

    DBConnect();

    $sqlCols = '';
    for ($x = 1; $x <= 31; $x++) {
        $padded = str_pad($x, 2, '0', STR_PAD_LEFT);
        $sqlCols .= ", round(avg(mktslvr$padded)*100) mkt$padded, ifnull(sum(qty$padded),0) qty$padded";
    }


    $sql = <<<EOF
SELECT bonusset, month $sqlCols
FROM `tblItemHistoryMonthly` ihm
join tblRealm r on ihm.house = r.house and r.region = ? and r.canonical is not null
WHERE ihm.item=?
group by bonusset, month
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('si', $region, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $bonusRows = DBMapArray($result, array('bonusset', null));
    $stmt->close();

    $tr = array();
    $today = strtotime(Date('Y-m-d'));
    foreach ($bonusRows as $bonusSet => &$rows) {
        $prevPrice = 0;
        for ($x = 0; $x < count($rows); $x++) {
            $year = 2014 + floor(($rows[$x]['month'] - 1) / 12);
            $monthNum = $rows[$x]['month'] % 12;
            if ($monthNum == 0) {
                $monthNum = 12;
            }
            $month = ($monthNum < 10 ? '0' : '') . $monthNum;
            for ($dayNum = 1; $dayNum <= 31; $dayNum++) {
                $day = ($dayNum < 10 ? '0' : '') . $dayNum;
                if ($year == 2015 && $monthNum == 12 && ($dayNum >= 16 && $dayNum <= 22)) {
                    continue;
                }
                if (!is_null($rows[$x]['mkt' . $day])) {
                    $tr[$bonusSet][] = array('date'     => "$year-$month-$day",
                                  'silver'   => round($rows[$x]['mkt' . $day] / 100, 2),
                                  'quantity' => $rows[$x]['qty' . $day]
                    );
                    $prevPrice = round($rows[$x]['mkt' . $day] / 100, 2);
                } else {
                    if (!checkdate($monthNum, $dayNum, $year)) {
                        break;
                    }
                    if (strtotime("$year-$month-$day") >= $today) {
                        break;
                    }
                    if ($prevPrice) {
                        $tr[$bonusSet][] = array('date' => "$year-$month-$day", 'silver' => $prevPrice, 'quantity' => 0);
                    }
                }
            }
        }
    }
    unset($rows);

    MCSet($key, $tr);

    return $tr;
}