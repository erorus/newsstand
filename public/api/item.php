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
    'region'        => GetRegion($house),
);

if ($json['region'] != 'EU') {
    $json['sellers'] = ItemSellers($house, $item);
}

json_return($json);

function ItemStats($house, $item)
{
    global $db;

    $cacheKey = 'item_stats_' . $item;

    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $localeCols = LocaleColumns('i.name');
    $sql = <<<EOF
select i.id, $localeCols, i.icon, i.display, i.class as classid, i.subclass, i.quality, 
i.level baselevel, i.stacksize, i.binds, i.buyfromvendor, i.selltovendor, i.auctionable,
s.price, s.quantity, s.lastseen, ifnull(s.level, if(i.class in (2,4), i.level, 0)) level,
ivc.copper vendorprice, ivc.npc vendornpc, ivc.npccount vendornpccount
from tblDBCItem i
left join tblItemSummary s on s.house = %d and s.item = i.id
left join tblDBCItemVendorCost ivc on ivc.item = i.id
where i.id = %d
group by s.level
EOF;

    $result = $db->query(sprintf($sql, $house, $item), MYSQLI_USE_RESULT);
    $tr = DBMapArray($result, array('level', null));
    foreach ($tr as &$levelRow) {
        $levelRow = array_pop($levelRow);
    }
    unset($levelRow);

    MCSetHouse($house, $cacheKey, $tr);

    return $tr;
}

function ItemHistory($house, $item)
{
    global $db;

    $key = 'item_history_l_' . $item;

    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<'EOF'
select level, snapshot, silver, quantity
from (select
    if(level = @prevLevel, null, @price := null) resetprice,
    (@prevLevel := level) as level,
    unix_timestamp(updated) snapshot,
    cast(if(quantity is null, @price, @price := silver) as unsigned) `silver`,
    ifnull(quantity,0) as quantity
    from (select @price := null, @prevLevel := null) priceSetup,
        (select `is`.level, s.updated,
        case hour(s.updated)
            when 0 then ih.quantity00
            when 1 then ih.quantity01
            when 2 then ih.quantity02
            when 3 then ih.quantity03
            when 4 then ih.quantity04
            when 5 then ih.quantity05
            when 6 then ih.quantity06
            when 7 then ih.quantity07
            when 8 then ih.quantity08
            when 9 then ih.quantity09
            when 10 then ih.quantity10
            when 11 then ih.quantity11
            when 12 then ih.quantity12
            when 13 then ih.quantity13
            when 14 then ih.quantity14
            when 15 then ih.quantity15
            when 16 then ih.quantity16
            when 17 then ih.quantity17
            when 18 then ih.quantity18
            when 19 then ih.quantity19
            when 20 then ih.quantity20
            when 21 then ih.quantity21
            when 22 then ih.quantity22
            when 23 then ih.quantity23
            else null end as `quantity`,
        case hour(s.updated)
            when 0 then ih.silver00
            when 1 then ih.silver01
            when 2 then ih.silver02
            when 3 then ih.silver03
            when 4 then ih.silver04
            when 5 then ih.silver05
            when 6 then ih.silver06
            when 7 then ih.silver07
            when 8 then ih.silver08
            when 9 then ih.silver09
            when 10 then ih.silver10
            when 11 then ih.silver11
            when 12 then ih.silver12
            when 13 then ih.silver13
            when 14 then ih.silver14
            when 15 then ih.silver15
            when 16 then ih.silver16
            when 17 then ih.silver17
            when 18 then ih.silver18
            when 19 then ih.silver19
            when 20 then ih.silver20
            when 21 then ih.silver21
            when 22 then ih.silver22
            when 23 then ih.silver23
            else null end as `silver`
        from tblSnapshot s
        join tblItemSummary `is` on `is`.house = s.house
        left join tblItemHistoryHourly ih on date(s.updated) = ih.`when` and ih.house = `is`.house and ih.item = `is`.item and ih.level = `is`.level
        where `is`.item = %d and s.house = %d and s.updated >= timestampadd(day,-%d,now()) and s.flags & 1 = 0
        group by `is`.level, s.updated
        order by `is`.level, s.updated asc
        ) ordered
    ) withoutresets
EOF;

    $result = $db->query(sprintf($sql, $item, $house, HISTORY_DAYS));
    $tr = DBMapArray($result, ['level', null]);

    foreach ($tr as &$snapshots) {
        while (count($snapshots) > 0 && is_null($snapshots[0]['silver'])) {
            array_shift($snapshots);
        }
        foreach ($snapshots as &$snapshot) {
            unset($snapshot['level']);
        }
        unset($snapshot);
    }
    unset($snapshots);

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function ItemHistoryDaily($house, $item)
{
    global $db;

    $cacheKey = 'item_historydaily_' . $house . '_' . $item;

    if (($tr = MCGet($cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
select `when` as `date`,
`pricemin` as `silvermin`, `priceavg` as `silveravg`, `pricemax` as `silvermax`,
`pricestart` as `silverstart`, `priceend` as `silverend`,
`quantitymin`, `quantityavg`, `quantitymax`
from tblItemHistoryDaily
where house = ? and item = ?
order by `when` asc
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
    $stmt->close();

    $fiveAm = strtotime('05:00:00 am');
    if ($fiveAm < time()) {
        $fiveAm = strtotime('tomorrow 05:00:00 am');
    }

    MCSet($cacheKey, $tr, $fiveAm);

    return $tr;
}

function ItemHistoryMonthly($house, $item)
{
    global $db;

    $cacheKey = 'item_historymonthly_l_' . $house . '_' . $item;

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
    $levelRows = DBMapArray($result, array('level', null));
    $stmt->close();

    $tr = [];
    $today = strtotime(date('Y-m-d'));
    foreach ($levelRows as $level => &$rows) {
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
                    $tr[$level][] = [
                        'date'     => "$year-$month-$day",
                        'silver'   => $rows[$x]['mktslvr' . $day],
                        'quantity' => $rows[$x]['qty' . $day]
                    ];
                    $prevPrice = $rows[$x]['mktslvr' . $day];
                } else {
                    if (!checkdate($monthNum, $dayNum, $year)) {
                        break;
                    }
                    if (strtotime("$year-$month-$day") >= $today) {
                        break;
                    }
                    if ($prevPrice) {
                        $tr[$level][] = array('date' => "$year-$month-$day", 'silver' => $prevPrice, 'quantity' => 0);
                    }
                }
            }
        }
    }
    unset($rows);

    MCSet($cacheKey, $tr, 60 * 60 * 8);

    return $tr;
}

function ItemSellers($house, $item)
{
    global $db;

    $cacheKey = 'item_sellers_' . $item;

    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<'EOF'
select sum(sih.quantity) quantity, sum(if(sih.snapshot > timestampadd(hour, -97, now()), sih.quantity, 0)) recentquantity,
unix_timestamp(max(sih.snapshot)) lastseen, s.realm sellerrealm, ifnull(s.name, '???') sellername
from tblSellerItemHistory sih
left join tblSeller s on sih.seller = s.id and s.lastseen is not null
where sih.house = ?
and sih.item = ?
group by sih.seller
order by 1 desc
limit 10
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    return $tr;
}

function ItemAuctions($house, $item)
{
    global $db;

    $cacheKey = 'item_auctions_l_' . $item;

    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        PopulateLocaleCols($tr, [
            ['func' => 'GetItemBonusNames', 'key' => 'bonuses', 'name' => 'bonusname'],
            ['func' => 'GetRandEnchantNames', 'key' => 'rand', 'name' => 'randname'],
        ], true);

        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
SELECT a.quantity, a.bid, a.buy, ifnull(ae.`rand`,0) `rand`, ifnull(ae.seed,0) `seed`, 
ifnull(@lootedLevel := ae.lootedlevel,0) `lootedlevel`, ifnull(ae.level, i.level) level,
s.realm sellerrealm, ifnull(s.name, '???') sellername,
concat_ws(':',ae.bonus1,ae.bonus2,ae.bonus3,ae.bonus4,ae.bonus5,ae.bonus6) bonuses
FROM `tblAuction` a
join tblDBCItem i on a.item=i.id
left join tblSeller s on a.seller=s.id and s.lastseen is not null
left join tblAuctionExtra ae on ae.house=a.house and ae.id=a.id
left join tblDBCRandEnchants re on re.id = ae.rand
WHERE a.house=? and a.item=?
group by a.id
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    PopulateLocaleCols($tr, [
        ['func' => 'GetItemBonusNames', 'key' => 'bonuses', 'name' => 'bonusname'],
        ['func' => 'GetRandEnchantNames', 'key' => 'rand', 'name' => 'randname'],
    ], true);

    return $tr;
}

function ItemGlobalNow($region, $item)
{
    global $db;

    $key = 'item_globalnow_l2_' . $region . '_' . $item;
    if (($tr = MCGet($key)) !== false) {
        return $tr;
    }

    DBConnect();
    $goldCapIncreased = date('Y-m-d H:i:s', 1469016000 + (86400 * 2)); // july 20th 2016, plus 2 days for auctions to expire

    $sql = <<<EOF
    SELECT i.level, r.house, i.price, i.quantity, unix_timestamp(i.lastseen) as lastseen
FROM `tblItemSummary` i
join tblRealm r on i.house = r.house and r.region = ? and r.canonical is not null
WHERE i.item=?
and i.lastseen > ?
group by i.level, r.house
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('sis', $region, $item, $goldCapIncreased);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('level', null));
    $stmt->close();

    foreach ($tr as &$levels) {
        foreach ($levels as &$house) {
            unset($house['level']);
        }
        unset($house);
    }
    unset($levels);

    MCSet($key, $tr, 60 * 60);

    return $tr;
}

function ItemGlobalMonthly($region, $item)
{
    global $db;

    $key = 'item_globalmonthly_l_' . $region . '_' . $item;
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
SELECT level, month $sqlCols
FROM `tblItemHistoryMonthly` ihm
join tblRealm r on ihm.house = r.house and r.region = ? and r.canonical is not null
WHERE ihm.item=?
group by level, month
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('si', $region, $item);
    $stmt->execute();
    $result = $stmt->get_result();
    $levelRows = DBMapArray($result, array('level', null));
    $stmt->close();

    $tr = array();
    $today = strtotime(date('Y-m-d'));
    foreach ($levelRows as $level => &$rows) {
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
                    $tr[$level][] = [
                        'date'     => "$year-$month-$day",
                        'silver'   => round($rows[$x]['mkt' . $day] / 100, 2),
                        'quantity' => $rows[$x]['qty' . $day]
                    ];
                    $prevPrice = round($rows[$x]['mkt' . $day] / 100, 2);
                } else {
                    if (!checkdate($monthNum, $dayNum, $year)) {
                        break;
                    }
                    if (strtotime("$year-$month-$day") >= $today) {
                        break;
                    }
                    if ($prevPrice) {
                        $tr[$level][] = array('date' => "$year-$month-$day", 'silver' => $prevPrice, 'quantity' => 0);
                    }
                }
            }
        }
    }
    unset($rows);

    MCSet($key, $tr);

    return $tr;
}