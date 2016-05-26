<?php

require_once('../incl/incl.php');
require_once('../incl/memcache.incl.php');

$now = Date('r');

echo <<<EOF
<!DOCTYPE html>
<html><head>
<title>Battle.net Auction House API Data Intervals</title>
</head>
<body>
<h1>Battle.net Auction House API Data Intervals</h1>

Delays observed between AH API snapshots over the past 72 hours, as of $now
<p>

EOF;

echo BuildDataIntervalsTable(DataIntervalsData());

echo '</body></html>';

function DataIntervalsData()
{
    global $db;

    if (($tr = MCGet('dataintervalstable')) !== false)
        return $tr;

    DBConnect();

    $sql = <<<EOF
select t.house, t.lastupdate, t.mindelta * 60 mindelta, t.avgdelta * 60 avgdelta, t.maxdelta * 60 maxdelta, r.region, group_concat(r.name order by 1 separator ', ') nms from (
select deltas.house, max(deltas.updated) lastupdate, round(min(delta)/60) mindelta, round(avg(delta)/60) avgdelta, round(max(delta)/60) maxdelta
from (
select sn.updated,
if(@prevhouse = sn.house and sn.updated > timestampadd(hour, -72, now()), unix_timestamp(sn.updated) - @prevdate, null) delta,
@prevdate := unix_timestamp(sn.updated) updated_ts,
@prevhouse := sn.house house
from (select @prevhouse := null, @prevdate := null) setup, tblSnapshot sn
order by sn.house, sn.updated) deltas
group by deltas.house) t
join tblRealm r on r.house = t.house
group by r.house
order by 3 asc, 4 asc, region asc, nms asc
EOF;

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();

    MCSet('dataintervalstable', $tr, 60);

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
        $tr .= '<td align="right">'.(is_null($row['avgdelta']) ? '' : TimeDiff(time() - $row['avgdelta'], $opt)).'</td>';
        $tr .= '<td align="right">'.(is_null($row['maxdelta']) ? '' : TimeDiff(time() - $row['maxdelta'], $opt)).'</td>';
        $tr .= '<td align="right">'.TimeDiff(strtotime($row['lastupdate'])).'</td>';
        $tr .= '</tr>';
    }

    $tr .= '</table>';

    return $tr;
}