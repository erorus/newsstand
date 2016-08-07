<?php

require_once('../incl/incl.php');
require_once('../incl/memcache.incl.php');

$now = date('r');

echo <<<EOF
<!DOCTYPE html>
<html><head>
<title>Battle.net Auction House API Data Intervals</title>
</head>
<body>
<h1>Battle.net Auction House API Data Intervals</h1>

Delays observed between AH API snapshots over the past 48 hours, as of $now
<p>

EOF;

echo BuildDataIntervalsTable(DataIntervalsData());

echo '</body></html>';

function DataIntervalsData()
{
    $cacheKey = 'dataintervalstable';

    if (($tr = MCGet($cacheKey)) !== false)
        return $tr;

    $db = DBConnect();

    $sql = <<<'EOF'
select t.house, t.lastupdate,
	t.mindelta, modes.delta as modedelta, t.avgdelta, t.maxdelta,
	r.region, group_concat(r.name order by 1 separator ', ') nms
from (
	select deltas.house, max(deltas.updated) lastupdate, round(min(delta)/5)*5 mindelta, round(avg(delta)/5)*5 avgdelta, round(max(delta)/5)*5 maxdelta
	from (
		select sn.updated,
			if(@prevhouse = sn.house and sn.updated > timestampadd(hour, -48, now()), unix_timestamp(sn.updated) - @prevdate, null) delta,
			@prevdate := unix_timestamp(sn.updated) updated_ts,
			@prevhouse := sn.house house
		from (select @prevhouse := null, @prevdate := null) setup, tblSnapshot sn
		order by sn.house, sn.updated) deltas
	group by deltas.house) t
left join (
	select house, delta
	from (
		select if(@prev = house, @rownum := @rownum + 1, @rownum := 0) o, delta, (@prev := house) as house, c
		from (
			select house, delta, count(*) c
			from (
				select sn.updated,
					round(if(@prevh = sn.house, unix_timestamp(sn.updated) - @prevd, null)/5)*5 delta,
					@prevd := unix_timestamp(sn.updated) updated_ts,
					@prevh := sn.house house
				from (select @prevh := null, @prevd := null) setup, tblSnapshot sn
				where sn.updated > timestampadd(hour, -48, now())
				order by sn.house, sn.updated) deltas
			where delta is not null
			group by house, delta
			order by house, c desc
		) tosort,
		(select @rownum := 0, @prev := null) setup) filtered
		where o=0
	) modes on modes.house = t.house
join tblRealm r on r.house = t.house and r.locale is not null
group by r.house
order by 4 asc, 3 asc, region asc, nms asc
EOF;

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    MCSet($cacheKey, $tr, 60);

    return $tr;
}

function BuildDataIntervalsTable(&$rows)
{
    $tr = <<<EOF
<table border="1" cellspacing="0" cellpadding="5">
<tr>
    <th>Region</th>
    <th>Realms</th>
    <th>Minimum Delay</th>
    <th>Usual Delay</th>
    <th>Average Delay</th>
    <th>Maximum Delay</th>
    <th>Last Update</th>
</tr>
EOF;
    $opt = [
        'distance' => false,
    ];

    foreach ($rows as $row) {
        $tr .= '<tr>';
        $tr .= '<td>'.$row['region'].'</td>';
        $tr .= '<td>'.$row['nms'].'</td>';
        $tr .= '<td align="right">'.(is_null($row['mindelta']) ? '' : TimeDiff(time() - $row['mindelta'], $opt)).'</td>';
        $tr .= '<td align="right">'.(is_null($row['modedelta']) ? '' : TimeDiff(time() - $row['modedelta'], $opt)).'</td>';
        $tr .= '<td align="right">'.(is_null($row['avgdelta']) ? '' : TimeDiff(time() - $row['avgdelta'], $opt)).'</td>';
        $tr .= '<td align="right">'.(is_null($row['maxdelta']) ? '' : TimeDiff(time() - $row['maxdelta'], $opt)).'</td>';
        $tr .= '<td align="right">'.TimeDiff(strtotime($row['lastupdate'])).'</td>';
        $tr .= '</tr>';
    }

    $tr .= '</table>';

    return $tr;
}