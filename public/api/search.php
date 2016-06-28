<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['house']) || !isset($_GET['search'])) {
    json_return(array());
}

$house = intval($_GET['house'], 10);
$search = mb_strtolower(substr(trim($_GET['search'], " \t\n\r[]"), 0, 50));

if ($search == '') {
    json_return(array());
}

BotCheck();
HouseETag($house);

$locale = GetLocale();
$searchCacheKey = 'search2_' . $locale . '_' . md5($search);

if ($json = MCGetHouse($house, $searchCacheKey)) {
    PopulateLocaleCols($json['battlepets'], [['func' => 'GetPetNames', 'key' => 'id', 'name' => 'name']]);
    PopulateLocaleCols($json['items'], [
        ['func' => 'GetItemNames',      'key' => 'id',      'name' => 'name'],
        ['func' => 'GetItemBonusNames', 'key' => 'bonuses', 'name' => 'bonusname'],
        ['func' => 'GetItemBonusTags',  'key' => 'bonuses', 'name' => 'bonustag'],
    ]);

    json_return($json);
}

DBConnect();

$json = array(
    'items'      => SearchItems($house, $search, $locale),
    'sellers'    => SearchSellers($house, $search),
    'battlepets' => SearchBattlePets($house, $search, $locale),
);

$ak = array_keys($json);
foreach ($ak as $k) {
    if (count($json[$k]) == 0) {
        unset($json[$k]);
    }
}

MCSetHouse($house, $searchCacheKey, $json);

PopulateLocaleCols($json['battlepets'], [['func' => 'GetPetNames', 'key' => 'id', 'name' => 'name']]);
PopulateLocaleCols($json['items'], [
    ['func' => 'GetItemNames',      'key' => 'id',      'name' => 'name'],
    ['func' => 'GetItemBonusNames', 'key' => 'bonuses', 'name' => 'bonusname'],
    ['func' => 'GetItemBonusTags',  'key' => 'bonuses', 'name' => 'bonustag'],
]);

json_return($json);

function SearchItems($house, $search, $locale)
{
    global $db;

    $suffixes = MCGet('search_itemsuffixes_' . $locale);
    if ($suffixes === false) {
        $stmt = $db->prepare('SELECT lower(suffix) FROM tblDBCItemRandomSuffix where locale=\''.$locale.'\' union select lower(name_'.$locale.') from tblDBCItemBonus where name_'.$locale.' is not null');
        $stmt->execute();
        $result = $stmt->get_result();
        $suffixes = DBMapArray($result, null);
        $stmt->close();

        MCSet('search_itemsuffixes_' . $locale, $suffixes, 86400);
    }

    $terms = preg_replace('/\s+/', '%', " $search ");
    $nameSearch = "i.name_$locale like ?";

    $terms2 = '';

    $barewords = trim(preg_replace('/ {2,}/', ' ', preg_replace('/[^ a-zA-Z0-9\'\.]/', '', $search)));

    for ($x = 0; $x < count($suffixes); $x++) {
        if (substr($barewords, -1 * strlen($suffixes[$x])) == $suffixes[$x]) {
            $terms2 = '%' . str_replace(' ', '%', substr($barewords, 0, -1 * strlen($suffixes[$x]) - 1)) . '%';
            $nameSearch = "(i.name_$locale like ? or i.name_$locale like ?)";
        }
    }

    $sql = <<<EOF
select results.*,
(select round(avg(case hours.h
        when  0 then ihh.silver00 when  1 then ihh.silver01 when  2 then ihh.silver02 when  3 then ihh.silver03
        when  4 then ihh.silver04 when  5 then ihh.silver05 when  6 then ihh.silver06 when  7 then ihh.silver07
        when  8 then ihh.silver08 when  9 then ihh.silver09 when 10 then ihh.silver10 when 11 then ihh.silver11
        when 12 then ihh.silver12 when 13 then ihh.silver13 when 14 then ihh.silver14 when 15 then ihh.silver15
        when 16 then ihh.silver16 when 17 then ihh.silver17 when 18 then ihh.silver18 when 19 then ihh.silver19
        when 20 then ihh.silver20 when 21 then ihh.silver21 when 22 then ihh.silver22 when 23 then ihh.silver23
        else null end) * 100)
        from tblItemHistoryHourly ihh,
        (select  0 h union select  1 h union select  2 h union select  3 h union
         select  4 h union select  5 h union select  6 h union select  7 h union
         select  8 h union select  9 h union select 10 h union select 11 h union
         select 12 h union select 13 h union select 14 h union select 15 h union
         select 16 h union select 17 h union select 18 h union select 19 h union
         select 20 h union select 21 h union select 22 h union select 23 h) hours
        where ihh.house = ? and ihh.item = results.id and ihh.bonusset = results.bonusset) avgprice,
ifnull(GROUP_CONCAT(bs.`bonus` ORDER BY 1 SEPARATOR ':'), '') bonusurl,
ifnull(GROUP_CONCAT(bs.`bonus` ORDER BY 1 SEPARATOR ':'), ifnull(results.basebonus, '')) bonuses
from (
    select i.id, i.quality, i.icon, i.class as classid, s.price, s.quantity, unix_timestamp(s.lastseen) lastseen,
    ifnull(s.bonusset,0) bonusset, i.level, i.basebonus
    from tblDBCItem i
    left join tblItemSummary s on s.house=? and s.item=i.id
    where $nameSearch
    and (s.item is not null or ifnull(i.auctionable,1) = 1)
    group by i.id, ifnull(s.bonusset,0)
    limit ?
) results
left join tblBonusSet bs on results.bonusset = bs.`set`
left join tblDBCItemBonus ib on ifnull(bs.bonus, results.basebonus) = ib.id
group by results.id, results.bonusset
EOF;
    $limit = 50 * strlen(preg_replace('/\s/', '', $search));

    $stmt = $db->prepare($sql);
    if ($terms2 == '') {
        $stmt->bind_param('iisi', $house, $house, $terms, $limit);
    } else {
        $stmt->bind_param('iissi', $house, $house, $terms, $terms2, $limit);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $tr;
}

function SearchSellers($house, $search)
{
    global $db;

    $terms = mb_ereg_replace('\s+', '%', " $search ");

    $sql = <<<EOF
select s.id, r.id realm, s.name, unix_timestamp(s.firstseen) firstseen, unix_timestamp(s.lastseen) lastseen
from tblSeller s
join tblRealm r on s.realm=r.id and r.house=?
where convert(s.name using utf8) collate utf8_unicode_ci like ?
limit 50
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $house, $terms);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $tr;
}

function SearchBattlePets($house, $search, $locale)
{
    global $db;

    $terms = preg_replace('/\s+/', '%', " $search ");

    $sql = <<<EOF
select i.id, i.icon, i.type, i.npc,
min(if(s.quantity>0,s.price,null)) price, sum(s.quantity) quantity, unix_timestamp(max(s.lastseen)) lastseen,
(select round(avg(case hours.h
    when  0 then ph.silver00 when  1 then ph.silver01 when  2 then ph.silver02 when  3 then ph.silver03
    when  4 then ph.silver04 when  5 then ph.silver05 when  6 then ph.silver06 when  7 then ph.silver07
    when  8 then ph.silver08 when  9 then ph.silver09 when 10 then ph.silver10 when 11 then ph.silver11
    when 12 then ph.silver12 when 13 then ph.silver13 when 14 then ph.silver14 when 15 then ph.silver15
    when 16 then ph.silver16 when 17 then ph.silver17 when 18 then ph.silver18 when 19 then ph.silver19
    when 20 then ph.silver20 when 21 then ph.silver21 when 22 then ph.silver22 when 23 then ph.silver23
    else null end)*100)
    from tblPetHistoryHourly ph,
    (select  0 h union select  1 h union select  2 h union select  3 h union
     select  4 h union select  5 h union select  6 h union select  7 h union
     select  8 h union select  9 h union select 10 h union select 11 h union
     select 12 h union select 13 h union select 14 h union select 15 h union
     select 16 h union select 17 h union select 18 h union select 19 h union
     select 20 h union select 21 h union select 22 h union select 23 h) hours
    where ph.house = ? and ph.species = i.id group by ph.breed order by 1 asc limit 1) avgprice
from tblDBCPet i
left join tblPetSummary s on s.house=? and s.species=i.id
where i.name_$locale like ?
and not i.flags & 0x10
group by i.id
limit ?
EOF;
    $limit = 50 * strlen(preg_replace('/\s/', '', $search));

    $stmt = $db->prepare($sql);
    $stmt->bind_param('iisi', $house, $house, $terms, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $tr;
}
