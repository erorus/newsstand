<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house'])) {
    json_return(array());
}

$house = intval($_GET['house'], 10);

HouseETag($house, true);

header('Expires: '.date(DATE_RFC1123, time() + 10));

$json = array(
    'timestamps'    => HouseTimestamps($house),
    'sellers'       => [], //HouseTopSellers($house),
    'mostAvailable' => HouseMostAvailable($house),
    'deals'         => [], //HouseDeals($house),
    'sellerbots'    => [],
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
least(min(delta), 150*60) mindelta,
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

    $cacheKey = 'house_topsellers';
    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<'EOF'
SELECT r.id realm, s.name
FROM tblAuction a
join tblSeller s on a.seller = s.id
join tblRealm r on s.realm = r.id
left join tblAuctionExtra ae on ae.id = a.id and ae.house = a.house
left join tblItemGlobal g on a.item = g.item and g.level = ifnull(ae.level, 0) and g.region = r.region and a.item != %1$d
left join tblAuctionPet ap on ap.id = a.id and ap.house = a.house
left join tblPetGlobal pg on ap.species = pg.species and pg.region = r.region and a.item = %1$d
where a.house = ?
and s.lastseen is not null
group by a.seller
order by sum(least(if(a.buy = 0, a.bid, a.buy), ifnull(g.median, pg.median) * a.quantity)) desc
limit 10
EOF;

    $stmt = $db->prepare(sprintf($sql, BATTLE_PET_CAGE_ITEM));
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    return $tr;
}

function HouseMostAvailable($house)
{
    global $db;

    $cacheKey = 'house_mostavailable_l2';

    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        PopulateLocaleCols($tr, [['func' => 'GetItemNames', 'key' => 'id', 'name' => 'name']]);
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
SELECT i.id
FROM `tblItemSummary` tis
join tblDBCItem i on tis.item = i.id
WHERE house = ?
order by tis.quantity desc
limit 10
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    PopulateLocaleCols($tr, [['func' => 'GetItemNames', 'key' => 'id', 'name' => 'name']]);
    return $tr;
}

function HouseDeals($house)
{
    global $db;

    $cacheKey = 'house_deals_l2';

    if (($tr = MCGetHouse($house, $cacheKey)) !== false) {
        PopulateLocaleCols($tr, [['func' => 'GetItemNames', 'key' => 'id', 'name' => 'name']]);
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
SELECT i.id
FROM `tblItemSummary` tis
join tblDBCItem i on tis.item = i.id
join tblItemGlobal g on g.item = tis.item and g.level = tis.level and g.region = ?
WHERE house = ?
and tis.quantity > 0
and i.quality > 0
and g.median > 1000000
order by tis.price / g.median asc
limit 10
EOF;

    $region = GetRegion($house);

    $stmt = $db->prepare($sql);
    $stmt->bind_param('si', $region, $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    MCSetHouse($house, $cacheKey, $tr);

    PopulateLocaleCols($tr, [['func' => 'GetItemNames', 'key' => 'id', 'name' => 'name']]);
    return $tr;
}
