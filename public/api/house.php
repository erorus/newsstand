<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house'])) {
    json_return(array());
}

$house = intval($_GET['house'], 10);

HouseETag($house, true);

$json = array(
    'timestamps'    => HouseTimestamps($house),
    'sellers'       => HouseTopSellers($house),
    'mostAvailable' => HouseMostAvailable($house),
    'deals'         => HouseDeals($house),
    'sellerbots'    => HouseBotSellers($house),
);

$json = json_encode($json, JSON_NUMERIC_CHECK);

json_return($json);

function HouseTimestamps($house)
{
    global $db;

    $cacheKey = MCGet('housecheck_'.$house);
    if ($cacheKey === false) {
        $cacheKey = 0;
    }
    $cacheKey = 'house_timestamps_'.$cacheKey;
    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $tr = [
        'scheduled'   => 0,
        'delayednext' => 0,
        'lastupdate'  => 0,
        'mindelta'    => 0,
        'avgdelta'    => 0,
        'maxdelta'    => 0,
        'lastcheck'   => ['ts' => 0, 'json' => 0],
        'lastsuccess' => ['ts' => 0, 'json' => 0],
    ];

    $sql = <<<EOF
select unix_timestamp(timestampadd(second, least(ifnull(min(delta)+15, 45*60), 150*60), max(deltas.updated))) scheduled,
unix_timestamp(hc.nextcheck),
unix_timestamp(max(deltas.updated)) lastupdate,
min(delta) mindelta,
round(avg(delta)) avgdelta,
max(delta) maxdelta,
unix_timestamp(hc.lastcheck),
hc.lastcheckresult,
unix_timestamp(hc.lastchecksuccess),
hc.lastchecksuccessresult
from (
    select sn.updated,
    if(sn.updated > timestampadd(hour, -72, now()), unix_timestamp(sn.updated) - @prevdate, null) delta,
    @prevdate := unix_timestamp(sn.updated) updated_ts
    from (select @prevdate := null) setup, tblSnapshot sn
where sn.house = ?
    order by sn.updated) deltas
left join tblHouseCheck hc on hc.house = ?
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $house);
    $stmt->execute();
    $stmt->bind_result(
        $tr['scheduled'],
        $tr['delayednext'],
        $tr['lastupdate'],
        $tr['mindelta'],
        $tr['avgdelta'],
        $tr['maxdelta'],
        $tr['lastcheck']['ts'],
        $tr['lastcheck']['json'],
        $tr['lastsuccess']['ts'],
        $tr['lastsuccess']['json']
    );
    $stmt->fetch();
    $stmt->close();

    foreach (['lastcheck','lastsuccess'] as $k) {
        if (!is_null($tr[$k]['json'])) {
            $decoded = json_decode($tr[$k]['json'], true);
            if (json_last_error() == JSON_ERROR_NONE) {
                $tr[$k]['json'] = $decoded;
            }
        }
    }

    MCSetHouse($house, $cacheKey, $tr);

    return $tr;
}

function HouseTopSellers($house)
{
    global $db;

    $cacheKey = 'house_topsellers2';
    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
SELECT r.id realm, s.name
FROM tblAuction a
left join tblAuctionExtra ae on ae.id = a.id and ae.house = a.house
join tblSeller s on a.seller = s.id
join tblRealm r on s.realm = r.id
join tblItemGlobal g on a.item = g.item and g.bonusset = ifnull(ae.bonusset, 0)
where a.item != 86400
and a.house = ?
group by a.seller
order by sum(g.median * a.quantity) desc
limit 10
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    return $tr;
}

function HouseMostAvailable($house)
{
    global $db;

    $cacheKey = 'house_mostavailable_l';

    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $localizedItemNames = LocaleColumns('i.name');
    $sql = <<<EOF
SELECT i.id, $localizedItemNames
FROM `tblItemSummary` tis
join tblDBCItem i on tis.item = i.id
WHERE house = ?
and tis.bonusset = 0
order by tis.quantity desc
limit 10
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    return $tr;
}

function HouseDeals($house)
{
    global $db;

    $cacheKey = 'house_deals_l';

    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $localizedItemNames = LocaleColumns('i.name');
    $sql = <<<EOF
SELECT i.id, $localizedItemNames
FROM `tblItemSummary` tis
join tblDBCItem i on tis.item = i.id
join tblItemGlobal g on g.item = tis.item and g.bonusset = tis.bonusset
WHERE house = ?
and tis.quantity > 0
and i.quality > 0
and g.median > 1000000
order by tis.price / g.median asc
limit 10
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    return $tr;
}

function HouseBotSellers($house)
{
    global $db;

    $cacheKey = 'house_botsellers2';
    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $items = '128159, 127736, 127738, 127732, 127731, 127737, 127735, 127730, 127734, 127733, 127718, 128158, 127720, 127714, 127713, 127719, 127717, 127712, 127716, 127715';

    $sql = <<<'EOF'
select s.realm, s.name
from (
    select seller, count(distinct `snapshot`) cnt
    from (
        SELECT sih.seller, sih.item, sih.`snapshot`
        FROM `tblSellerItemHistory` sih
        join tblSeller s on sih.seller = s.id
        join tblRealm r on s.realm = r.id
        where r.house = ?
        and sih.item in (%1$s)
        and s.firstseen > timestampadd(day, -14, now())
    ) z1
    group by seller
    having count(distinct item) = 2
) z2
join tblSeller s on s.id = z2.seller
left join tblSellerItemHistory h on h.seller = z2.seller and h.item not in (%1$s)
where h.seller is null
and z2.cnt > 7
and s.lastseen > timestampadd(hour, -48, now())
order by s.lastseen desc
limit 20
EOF;

    $stmt = $db->prepare(sprintf($sql, $items));
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    return $tr;
}
