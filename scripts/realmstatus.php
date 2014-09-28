<?php

require_once(__DIR__.'/../incl/incl.php');
require_once(__DIR__.'/../incl/battlenet.incl.php');

if (isset($_GET['bnetget'])) {
    BnetGet();
    exit;
}

header('Content-type: text/html; charset=UTF-8');

echo <<<EOF
<html><head><title>Realm Status</title>
<style type="text/css">
td { border-top: 1px solid black }
td, th { padding: 4px }
td.r { text-align: right }

</style></head><body>
EOF;
echo '<h1>'.Date('Y-m-d H:i:s').'</h1>';

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

ShowRealms();
ShowLogs();

echo '</body></html>';

function BnetGet() {
    $parts = explode('-', $_GET['bnetget'], 2);
    if (count($parts) != 2) {
        echo 'Not enough parts.';
        exit;
    }

    switch($parts[0]) {
        case 'US':
        case 'EU':
            break;
        default:
            echo 'Bad region';
            exit;
    }

    $urlPart = 'wow/auction/data/'.$parts[1];
    header('Location: '.GetBattleNetURL($parts[0], $urlPart));
}

function ShowRealms() {
    echo '<h1>Realms</h1>';

    global $db;

    $sql = <<<EOF
SELECT r.house, r.region, r.canonical, sch.nextcheck scheduled, hc.nextcheck delayednext, sch.lastupdate, sch.mindelta, sch.avgdelta, sch.maxdelta
FROM tblRealm r
left join tblHouseCheck hc on hc.house = r.house
left join (
        select deltas.house, timestampadd(second, least(ifnull(min(delta)+15, 45*60), 150*60), max(deltas.updated)) nextcheck, max(deltas.updated) lastupdate, min(delta) mindelta, round(avg(delta)) avgdelta, max(delta) maxdelta
        from (
            select sn.updated,
            if(@prevhouse = sn.house and sn.updated > timestampadd(hour, -72, now()), unix_timestamp(sn.updated) - @prevdate, null) delta,
            @prevdate := unix_timestamp(sn.updated) updated_ts,
            @prevhouse := sn.house house
            from (select @prevhouse := null, @prevdate := null) setup, tblSnapshot sn
            order by sn.house, sn.updated) deltas
        group by deltas.house
        ) sch on sch.house = r.house
where r.canonical is not null
order by if(delayednext is null, 1, 0) asc, ifnull(delayednext, scheduled), sch.lastupdate, region, canonical
EOF;

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = DBMapArray($result, null);

    echo '<table cellspacing="0"><tr><th>House</th><th>Region</th><th>Canonical</th><th>Updated</th><th>Scheduled</th><th>Min</th><th>Avg</th><th>Max</th></tr>';
    foreach ($rows as &$row) {
        echo '<tr><td class="r">'.$row['house'].'</td>';
        echo '<td>'.$row['region'].'</td>';
        echo '<td><a href="?bnetget='.$row['region'].'-'.$row['canonical'].'">'.$row['canonical'].'</a></td>';

        if (is_null($row['lastupdate'])) {
            echo '<td>&nbsp;</td>';
        } else {
            $css='';
            $updateDelta = time() - strtotime($row['lastupdate']);
            if ($updateDelta > ($row['maxdelta'] + 180)) {
                $css = 'color: red';
            } elseif ($updateDelta > $row['avgdelta']) {
                $css = 'color: #999900';
            }
            echo '<td style="'.$css.'" class="r">'.TimeDiff(strtotime($row['lastupdate'])).'</td>';
        }

        if (is_null($row['scheduled'])) {
            echo '<td>&nbsp;</td>';
        } elseif (is_null($row['delayednext'])) {
            echo '<td style="color: green" class="r">'.TimeDiff(strtotime($row['scheduled'])).'</td>';
        } else {
            echo '<td style="color: #999900" class="r">'.TimeDiff(strtotime($row['delayednext'])).'</td>';
        }

        echo '<td class="r">'.round(intval($row['mindelta'],10)/60).' min</td>';
        echo '<td class="r">'.round(intval($row['avgdelta'],10)/60).' min</td>';
        echo '<td class="r">'.round(intval($row['maxdelta'],10)/60).' min</td>';

        echo '</tr>';
    }
    unset($row);

    echo '</table>';
}

function ShowLogs() {
    echo '<h1>Logs</h1>';
    $dir = realpath(__DIR__.'/../logs');
    $files = array_values(array_filter(glob($dir.'/*.log'), function($f) {
            $parts = explode('.',basename($f));
            if (count($parts) == 2)
                return true;

            for ($x = 1; $x < count($parts) - 1; $x++) {
                switch ($parts[$x]) {
                    case 'US':
                    case 'EU':
                        break;
                    default:
                        return false;
                }
            }
            return true;
        }));

    sort($files);

    foreach ($files as $path) {
        echo '<h2>'.htmlentities($path).'</h2>';
        echo '<pre>';
        passthru('tail -n 20 '.escapeshellarg($path));
        echo '</pre>';
    }
}