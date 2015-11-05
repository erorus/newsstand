<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house']) || !isset($_GET['species'])) {
    json_return(array());
}

$house = intval($_GET['house'], 10);
$species = intval($_GET['species'], 10);

if (!$species) {
    json_return(array());
}

BotCheck();
HouseETag($house);

$json = array(
    'stats'     => PetStats($house, $species),
    'history'   => PetHistory($house, $species),
    'auctions'  => PetAuctions($house, $species),
    'globalnow' => PetGlobalNow(GetRegion($house), $species),
);

json_return($json);

function PetStats($house, $species)
{
    global $db;

    $key = 'battlepet_stats2_' . $species;
    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
select i.id, i.name, i.icon, i.type, i.npc,
s.price, s.quantity, s.lastseen, s.breed
from tblDBCPet i
left join tblPetSummary s on s.house = ? and s.species = i.id
where i.id = ?
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $species);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('breed', null));
    foreach ($tr as &$breedRow) {
        $breedRow = array_pop($breedRow);
    }
    unset($breedRow);
    $stmt->close();

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function PetHistory($house, $species)
{
    global $db;

    $key = 'battlepet_history2_' . $species;
    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    DBConnect();

    $historyDays = HISTORY_DAYS;

    $sql = <<<EOF
select breed, snapshot, price, quantity
from (
    select if(breed = @prevBreed, null, @price := null) resetprice, @prevBreed := breed as breed, unix_timestamp(updated) snapshot,
        cast(if(quantity is null, @price, @price := price) as decimal(11,0)) `price`, ifnull(quantity,0) as quantity
    from (select @price := null, @prevBreed := null) priceSetup, (
    select ps.breed, s.updated, ph.quantity, ph.price
    from tblSnapshot s
    join tblPetSummary ps on ps.house = ?
    left join tblPetHistory ph on s.updated = ph.snapshot and ph.house = ps.house and ph.species = ps.species and ph.breed = ps.breed
    where s.house = ? and ps.species = ? and s.updated >= timestampadd(day,-$historyDays,now()) and s.flags & 1 = 0
    order by ps.breed, s.updated asc
    ) ordered
) withoutresets
EOF;

    $stmt = $db->prepare($sql);
    $realHouse = abs($house);
    $stmt->bind_param('iii', $house, $realHouse, $species);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('breed', null));
    $stmt->close();

    foreach ($tr as &$breedSet) {
        while (count($breedSet) > 0 && is_null($breedSet[0]['price'])) {
            array_shift($breedSet);
        }
    }
    unset($breedSet);

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function PetAuctions($house, $species)
{
    global $db;

    $key = 'battlepet_auctions2_' . $species;
    if (($tr = MCGetHouse($house, $key)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
SELECT ap.breed, quantity, bid, buy, ap.level, ap.quality, s.realm sellerrealm, ifnull(s.name, '???') sellername
FROM `tblAuction` a
JOIN `tblAuctionPet` ap on a.house = ap.house and a.id = ap.id
left join tblSeller s on a.seller=s.id
WHERE a.house=? and a.item=82800 and ap.species=?
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $species);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('breed', null));
    $stmt->close();

    MCSetHouse($house, $key, $tr);

    return $tr;
}

function PetGlobalNow($region, $species)
{
    global $db;

    $key = 'battlepet_globalnow2_' . $region . '_' . $species;
    if (($tr = MCGet($key)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = <<<EOF
    SELECT i.breed, r.house, i.price, i.quantity, unix_timestamp(i.lastseen) as lastseen
FROM `tblPetSummary` i
join tblRealm r on i.house = r.house and r.region = ?
WHERE i.species=?
group by i.breed, r.house
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('si', $region, $species);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('breed', null));
    $stmt->close();

    MCSet($key, $tr);

    return $tr;
}