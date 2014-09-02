<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house']) || !isset($_GET['species']))
    json_return(array());

$house = intval($_GET['house'], 10);
$species = intval($_GET['species'], 10);

if (!$species)
    json_return(array());

HouseETag($house);
BotCheck();

$json = array(
    'stats'     => PetStats($house, $species),
    'history'   => PetHistory($house, $species),
    'auctions'  => PetAuctions($house, $species),
    'globalnow' => PetGlobalNow(GetRegion($house), $house < 0 ? -1 : 1, $species),
);

json_return($json);

function PetStats($house, $species)
{
    global $db;

    if (($tr = MCGetHouse($house, 'battlepet_stats_'.$species)) !== false)
        return $tr;

    DBConnect();

    $sql = <<<EOF
select i.id, i.name, i.icon, i.type, i.npc,
s.price, s.quantity, s.lastseen, s.breed
from tblPet i
left join tblPetSummary s on s.house = ? and s.species = i.id
where i.id = ?
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $species);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('breed', null));
    foreach ($tr as &$breedRow)
        $breedRow = array_pop($breedRow);
    unset($breedRow);
    $stmt->close();

    MCSetHouse($house, 'battlepet_stats_'.$species, $tr);

    return $tr;
}

function PetHistory($house, $species)
{
    global $db;

    if (($tr = MCGetHouse($house, 'battlepet_history_'.$species)) !== false)
        return $tr;

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
    where s.house = ? and ps.species = ? and s.updated >= timestampadd(day,-$historyDays,now())
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

    foreach ($tr as &$breedSet)
        while(count($breedSet) > 0 && is_null($breedSet[0]['price']))
            array_shift($breedSet);
    unset($breedSet);

    MCSetHouse($house, 'battlepet_history_'.$species, $tr);

    return $tr;
}

function PetAuctions($house, $species)
{
    global $db;

    if (($tr = MCGetHouse($house, 'battlepet_auctions_'.$species)) !== false)
        return $tr;

    DBConnect();

    $sql = <<<EOF
SELECT ap.breed, quantity, bid, buy, `rand`, seed, s.realm sellerrealm, s.name sellername
FROM `tblAuction` a
JOIN `tblAuctionPet` ap on a.house = ap.house and a.id = ap.id
left join tblSeller s on a.seller=s.id
WHERE a.house=? and a.item=82800 and ap.species=?
EOF;
    // order by buy/quantity, bid/quantity, quantity, s.name, a.id

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $house, $species);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('breed', null));
    $stmt->close();

    MCSetHouse($house, 'battlepet_auctions_'.$species, $tr);

    return $tr;
}

function PetGlobalNow($region, $faction, $species)
{
    global $db;

    $key = 'battlepet_globalnow_'.$region.'_'.$faction.'_'.$species;
    if (($tr = MCGet($key)) !== false)
        return $tr;

    DBConnect();

    $sql = <<<EOF
    SELECT i.breed, r.house, i.price, i.quantity, unix_timestamp(i.lastseen) as lastseen
FROM `tblPetSummary` i
join tblRealm r on i.house = cast(r.house as signed) * ? and r.region = ?
WHERE i.species=?
group by i.breed, r.house
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('isi', $faction, $region, $species);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, array('breed', null));
    $stmt->close();

    MCSet($key, $tr);

    return $tr;
}