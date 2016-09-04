<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

mb_internal_encoding("UTF-8");

if (!isset($_GET['realm']) || !isset($_GET['seller'])) {
    json_return(array());
}

$realm = intval($_GET['realm'], 10);
$seller = mb_convert_case(mb_substr($_GET['seller'], 0, 12), MB_CASE_LOWER);

if (!$seller || !$realm || (!($house = GetHouse($realm)))) {
    json_return(array());
}

BotCheck();
HouseETag($house);

$sellerRow = SellerStats($house, $realm, $seller);
if (!$sellerRow) {
    json_return(array());
}

$json = array(
    'stats'       => $sellerRow,
    'history'     => SellerHistory($house, $sellerRow['id']),
    'byClass'     => SellerByClass($house, $sellerRow['id']),
    'auctions'    => SellerAuctions($house, $sellerRow['id']),
    'petAuctions' => SellerPetAuctions($house, $sellerRow['id']),
);

json_return($json);

function SellerStats($house, $realm, $seller)
{
    $seller = mb_ereg_replace(' ', '', $seller);
    $seller = mb_strtoupper(mb_substr($seller, 0, 1)) . mb_strtolower(mb_substr($seller, 1));

    $key = 'seller_stats_' . $realm . '_' . $seller;

    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    $db = DBConnect();

    $sql = 'SELECT * FROM tblSeller s WHERE realm = ? AND name = ?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $realm, $seller);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    if (count($tr)) {
        $tr = $tr[0];
    }
    $stmt->close();

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function SellerHistory($house, $seller)
{
    $key = 'seller_history2_' . $seller;

    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    $db = DBConnect();

    $historyDays = HISTORY_DAYS;

    $sql = <<<EOF
select unix_timestamp(s.updated) snapshot, ifnull(case hour(s.updated)
    when  0 then h.total00 when  1 then h.total01 when  2 then h.total02 when  3 then h.total03
    when  4 then h.total04 when  5 then h.total05 when  6 then h.total06 when  7 then h.total07
    when  8 then h.total08 when  9 then h.total09 when 10 then h.total10 when 11 then h.total11
    when 12 then h.total12 when 13 then h.total13 when 14 then h.total14 when 15 then h.total15
    when 16 then h.total16 when 17 then h.total17 when 18 then h.total18 when 19 then h.total19
    when 20 then h.total20 when 21 then h.total21 when 22 then h.total22 when 23 then h.total23
    else null end, 0) `total`, ifnull(case hour(s.updated)
    when  0 then h.new00 when  1 then h.new01 when  2 then h.new02 when  3 then h.new03
    when  4 then h.new04 when  5 then h.new05 when  6 then h.new06 when  7 then h.new07
    when  8 then h.new08 when  9 then h.new09 when 10 then h.new10 when 11 then h.new11
    when 12 then h.new12 when 13 then h.new13 when 14 then h.new14 when 15 then h.new15
    when 16 then h.new16 when 17 then h.new17 when 18 then h.new18 when 19 then h.new19
    when 20 then h.new20 when 21 then h.new21 when 22 then h.new22 when 23 then h.new23
    else null end,0) as `new`
from tblSnapshot s
left join tblSellerHistoryHourly h on date(s.updated) = h.`when` and h.seller=?
where s.house = ? and s.updated >= timestampadd(day,-$historyDays,now()) and s.flags & 1 = 0
order by s.updated asc
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $seller, $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
    $stmt->close();

    while (count($tr) > 0 && is_null($tr[0]['total'])) {
        array_shift($tr);
    }

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function SellerByClass($house, $seller)
{
    $key = 'seller_byclass_' . $seller;

    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    $db = DBConnect();

    $historyDays = HISTORY_DAYS;

    $sql = <<<EOF
select i.class, i.subclass, sum(sih.auctions) aucs
from tblSellerItemHistory sih
join tblDBCItem i on i.id = sih.item
where sih.seller = ?
and sih.snapshot >= timestampadd(day,-$historyDays,now())
group by i.class, i.subclass
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $seller);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
    $stmt->close();

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function SellerAuctions($house, $seller)
{
    $cacheKey = 'seller_auctions_b3_' . $seller;
    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        PopulateLocaleCols($tr, [
                ['func' => 'GetItemNames',          'key' => 'item',    'name' => 'name'],
                ['func' => 'GetItemBonusNames',     'key' => 'bonuses', 'name' => 'bonusname'],
                ['func' => 'GetItemBonusTags',      'key' => 'bonuses', 'name' => 'bonustag'],
                ['func' => 'GetRandEnchantNames',   'key' => 'rand',    'name' => 'randname'],
            ], true);
        return $tr;
    }

    $db = DBConnect();

    $sql = <<<EOF
select item, level, quality, class, subclass, icon, stacksize, quantity, bid, buy, `rand`, seed, lootedlevel, bonuses, tagurl,
(SELECT ifnull(sum(quantity),0) from tblAuction a2 left join tblAuctionExtra ae2 on a2.house=ae2.house and a2.id=ae2.id where a2.house=results.house and a2.item=results.item and ifnull(ae2.bonusset,0) = ifnull(results.bonusset,0) and
((results.buy > 0 and a2.buy > 0 and (a2.buy / a2.quantity < results.buy / results.quantity)) or (results.buy = 0 and (a2.bid / a2.quantity < results.bid / results.quantity)))) cheaper
from (
    SELECT a.item, nullif(ae.level,i.level) level, i.quality, i.class, i.subclass, i.icon, i.stacksize, a.quantity, a.bid, a.buy,
    ifnull(ae.`rand`, 0) `rand`, ifnull(ae.seed,0) seed, ae.lootedlevel,
    concat_ws(':',ae.bonus1,ae.bonus2,ae.bonus3,ae.bonus4,ae.bonus5,ae.bonus6) bonuses,
    ifnull(GROUP_CONCAT(distinct bs.`tagid` ORDER BY 1 SEPARATOR ':'), '') tagurl,
    a.house, a.seller, ae.bonusset
    FROM `tblAuction` a
    left join tblDBCItem i on a.item=i.id
    left join tblAuctionExtra ae on ae.house=a.house and ae.id=a.id
    left join tblBonusSet bs on ae.bonusset = bs.set
    WHERE a.seller = ?
    and a.item != 82800
    group by a.id
) results
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $seller);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    PopulateLocaleCols($tr, [
            ['func' => 'GetItemNames',          'key' => 'item',    'name' => 'name'],
            ['func' => 'GetItemBonusNames',     'key' => 'bonuses', 'name' => 'bonusname'],
            ['func' => 'GetItemBonusTags',      'key' => 'bonuses', 'name' => 'bonustag'],
            ['func' => 'GetRandEnchantNames',   'key' => 'rand',    'name' => 'randname'],
        ], true);

    return $tr;
}

function SellerPetAuctions($house, $seller)
{
    $cacheKey = 'seller_petauctions_' . $seller;

    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        PopulateLocaleCols($tr, [['func' => 'GetPetNames', 'key' => 'species', 'name' => 'name']], true);
        return $tr;
    }

    $db = DBConnect();

    $sql = <<<EOF
SELECT ap.species, ap.breed, quantity, bid, buy, ap.level, ap.quality, p.icon, p.type, p.npc,
(SELECT ifnull(sum(quantity),0)
from tblAuctionPet ap2
join tblAuction a2 on a2.house = ap2.house and a2.id = ap2.id
where ap2.house=a.house and ap2.species = ap.species and ap2.level >= ap.level and
((a.buy > 0 and a2.buy > 0 and (a2.buy / a2.quantity < a.buy / a.quantity)) or (a.buy = 0 and (a2.bid / a2.quantity < a.bid / a.quantity)))) cheaper
FROM `tblAuction` a
JOIN `tblAuctionPet` ap on a.house = ap.house and a.id = ap.id
JOIN `tblDBCPet` `p` on `p`.`id` = `ap`.`species`
WHERE a.house = ? and a.seller = ? and a.item = 82800
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $seller);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $result->close();
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    PopulateLocaleCols($tr, [['func' => 'GetPetNames', 'key' => 'species', 'name' => 'name']], true);

    return $tr;
}

