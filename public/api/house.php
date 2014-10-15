<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house']))
    json_return(array());

$house = intval($_GET['house'], 10);

HouseETag($house);

if ($json = MCGetHouse($house, 'houseinfo2'))
    json_return($json);

DBConnect();

$json = array(
    'timestamps' => HouseTimestamps($house),
);

$ak = array_keys($json);
foreach ($ak as $k)
    if (count($json[$k]) == 0)
        unset($json[$k]);

$json = json_encode($json, JSON_NUMERIC_CHECK);

MCSetHouse($house, 'houseinfo2', $json);

json_return($json);

function HouseTimestamps($house)
{
    global $db;

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

    return $tr;
}
