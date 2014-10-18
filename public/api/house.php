<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house']))
    json_return(array());

$house = intval($_GET['house'], 10);

HouseETag($house);

$json = array(
    'timestamps' => HouseTimestamps($house),
    'sellers' => HouseTopSellers($house),
);

$json = json_encode($json, JSON_NUMERIC_CHECK);

json_return($json);

function HouseTimestamps($house)
{
    global $db;

    if (($tr = MCGetHouse($house, 'house_timestamps')) !== false)
        return $tr;

    DBConnect();

    $tr = [
        'delayednext' => 0,
        'scheduled' => 0,
        'lastupdate' => 0,
        'mindelta' => 0,
        'avgdelta' => 0,
        'maxdelta' => 0,
    ];

    $sql = <<<EOF
select unix_timestamp(timestampadd(second, least(ifnull(min(delta)+15, 45*60), 150*60), max(deltas.updated))) scheduled,
(SELECT hc.nextcheck FROM tblHouseCheck hc WHERE hc.house = ?),
unix_timestamp(max(deltas.updated)) lastupdate, min(delta) mindelta, round(avg(delta)) avgdelta, max(delta) maxdelta
from (
    select sn.updated,
    if(sn.updated > timestampadd(hour, -72, now()), unix_timestamp(sn.updated) - @prevdate, null) delta,
    @prevdate := unix_timestamp(sn.updated) updated_ts
    from (select @prevdate := null) setup, tblSnapshot sn
where sn.house = ?
    order by sn.updated) deltas
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $house);
    $stmt->execute();
    $stmt->bind_result($tr['scheduled'], $tr['delayednext'], $tr['lastupdate'], $tr['mindelta'], $tr['avgdelta'], $tr['maxdelta']);
    $stmt->fetch();
    $stmt->close();

    MCSetHouse($house, 'house_timestamps', $tr);

    return $tr;
}

function HouseTopSellers($house)
{
    global $db;

    if (($tr = MCGetHouse($house, 'house_topsellers')) !== false)
        return $tr;

    DBConnect();

    $sql = <<<EOF
SELECT r.id realm, s.name
FROM tblAuction a
join tblSeller s on a.seller=s.id
join tblRealm r on s.realm = r.id
join tblItemGlobal g on a.item=g.item
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

    MCSetHouse($house, 'house_topsellers', $tr);

    return $tr;
}