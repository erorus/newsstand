<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

mb_internal_encoding("UTF-8");

if (!isset($_GET['realm']) || !isset($_GET['seller']))
    json_return(array());

$realm = intval($_GET['realm'], 10);
$seller = mb_convert_case(mb_substr($_GET['seller'], 0, 12), MB_CASE_LOWER);

if (!$seller || !$realm || (!($house = GetHouse($realm))))
    json_return(array());

BotCheck();
HouseETag($house);

$sellerRow = SellerStats($house, $realm, $seller);
if (!$sellerRow)
    json_return(array());

$json = array(
    'stats'     => $sellerRow,
    'history'   => SellerHistory($house, $sellerRow['id']),
    'auctions'  => SellerAuctions($house, $sellerRow['id']),
);

json_return($json);

function SellerStats($house, $realm, $seller)
{
    global $db;

    $key = 'seller_stats_'.$realm.'_'.$seller;

    if (($tr = MCGetHouse($house, $key)) !== false)
        return $tr;

    DBConnect();

    $sql = 'select * from tblSeller s where realm = ? and name = ?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $realm, $seller);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    if (count($tr))
        $tr = $tr[0];
    $stmt->close();

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function SellerHistory($house, $seller)
{
    global $db;

    if (($tr = MCGetHouse($house, 'seller_history_'.$seller)) !== false)
        return $tr;

    DBConnect();

    $historyDays = HISTORY_DAYS;

    $sql = <<<EOF
select unix_timestamp(s.updated) snapshot, if(total is null, @total, @total := total) `total`, ifnull(h.`new`,0) as `new`
from (select @total := null) totalSetup, tblSnapshot s
left join tblSellerHistory h on s.updated = h.snapshot and h.seller=?
where s.house = ? and s.updated >= timestampadd(day,-$historyDays,now())
order by s.updated asc
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $seller, $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    while(count($tr) > 0 && is_null($tr[0]['total']))
        array_shift($tr);

    MCSetHouse($house, 'seller_history_'.$seller, $tr);

    return $tr;
}

function SellerAuctions($house, $seller)
{
    global $db;

    if (($tr = MCGetHouse($house, 'seller_auctions_'.$seller)) !== false)
        return $tr;

    DBConnect();

    $sql = <<<EOF
SELECT a.item, i.name, i.quality, i.class, i.subclass, i.icon, i.stacksize, a.quantity, a.bid, a.buy, a.`rand`, a.seed
FROM `tblAuction` a
left join tblDBCItem i on a.item=i.id
WHERE a.house in (?,?) and a.seller=?
EOF;

    $stmt = $db->prepare($sql);
    $hordeHouse = -1 * $house;
    $stmt->bind_param('iii', $house, $hordeHouse, $seller);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    MCSetHouse($house, 'seller_auctions_'.$seller, $tr);

    return $tr;
}

