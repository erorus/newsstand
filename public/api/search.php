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
$searchCacheKey = 'search_' . $locale . '_' . md5($search);

if ($json = MCGetHouse($house, $searchCacheKey)) {
    json_return($json);
}

DBConnect();

$json = array(
    'items'      => SearchItems($house, $search, $locale),
    'sellers'    => SearchSellers($house, $search),
    'battlepets' => SearchBattlePets($house, $search),
);

$ak = array_keys($json);
foreach ($ak as $k) {
    if (count($json[$k]) == 0) {
        unset($json[$k]);
    }
}

$json = json_encode($json, JSON_NUMERIC_CHECK);

MCSetHouse($house, $searchCacheKey, $json);

json_return($json);

function SearchItems($house, $search, $locale)
{
    global $db, $LANG_LEVEL;

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

    $localizedItemNames = LocaleColumns('i.name');
    $bonusNames = LocaleColumns('ifnull(group_concat(distinct ib.name%1$s order by ib.namepriority desc separator \'|\'), \'\') bonusname%1$s', true);
    $bonusTags = LocaleColumns('ifnull(group_concat(distinct ib.`tag%1$s` order by ib.tagpriority separator \' \'), if(results.bonusset=0,\'\',concat(\'__LEVEL%1$s__ \', results.level+sum(ifnull(ib.level,0))))) bonustag%1$s', true);
    $bonusTags = strtr($bonusTags, $LANG_LEVEL);

    $sql = <<<EOF
select results.*,
ifnull(GROUP_CONCAT(bs.`bonus` ORDER BY 1 SEPARATOR ':'), '') bonusurl,
$bonusNames,
$bonusTags,
results.level+sum(ifnull(ib.level,0)) sortlevel
from (
    select i.id, $localizedItemNames, i.quality, i.icon, i.class as classid, s.price, s.quantity, unix_timestamp(s.lastseen) lastseen, round(avg(h.price)) avgprice,
    ifnull(s.bonusset,0) bonusset, i.level, i.basebonus
    from tblDBCItem i
    left join tblItemSummary s on s.house=? and s.item=i.id
    left join tblItemHistory h on h.house=? and h.item=i.id and h.bonusset = s.bonusset
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
    $tr = DBMapArray($result, null);
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
    $tr = DBMapArray($result, null);
    $stmt->close();
    return $tr;
}

function SearchBattlePets($house, $search)
{
    global $db;

    $terms = preg_replace('/\s+/', '%', " $search ");

    $sql = <<<EOF
select i.id, i.name, i.icon, i.type, i.npc,
min(if(s.quantity>0,s.price,null)) price, sum(s.quantity) quantity, unix_timestamp(max(s.lastseen)) lastseen,
(select round(avg(h.price)) from tblPetHistory h where h.house=? and h.species=i.id group by h.breed order by 1 asc limit 1) avgprice
from tblDBCPet i
left join tblPetSummary s on s.house=? and s.species=i.id
where i.name like ?
and not i.flags & 0x10
group by i.id
limit ?
EOF;
    $limit = 50 * strlen(preg_replace('/\s/', '', $search));

    $stmt = $db->prepare($sql);
    $stmt->bind_param('iisi', $house, $house, $terms, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();
    return $tr;
}
