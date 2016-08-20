<?php

require_once(__DIR__ . '/../incl/incl.php');
require_once(__DIR__ . '/../incl/memcache.incl.php');
require_once(__DIR__ . '/../incl/battlenet.incl.php');

if (php_sapi_name() == 'cli') {
    DebugMessage('This script is meant to be run from the private/admin area as a web page.', E_USER_ERROR);
}

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
echo '<h1>' . date('Y-m-d H:i:s') . '</h1>';

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

ShowRealms();
ShowMemcacheStats();
ShowLogs();
ShowErrors();

echo '</body></html>';

function BnetGet()
{
    $parts = explode('-', $_GET['bnetget'], 2);
    if (count($parts) != 2) {
        echo 'Not enough parts.';
        exit;
    }

    switch ($parts[0]) {
        case 'US':
        case 'EU':
            break;
        default:
            echo 'Bad region';
            exit;
    }

    $urlPart = 'wow/auction/data/' . $parts[1];
    header('Location: ' . GetBattleNetURL($parts[0], $urlPart));
}

function ShowMemcacheStats() {
    global $memcache;

    $status = $memcache->getstats();
    echo '<h1>Memcache</h1>';
    echo "<table>";

    echo "<tr><td>Memcache Server version:</td><td> ".$status["version"]."</td></tr>";
    echo "<tr><td>Process id of this server process </td><td>".$status["pid"]."</td></tr>";
    echo "<tr><td>Server was started </td><td>".TimeDiff(time() - $status["uptime"])."</td></tr>";
    echo "<tr><td>Accumulated user time for this process </td><td>".$status["rusage_user"]." seconds</td></tr>";
    echo "<tr><td>Accumulated system time for this process </td><td>".$status["rusage_system"]." seconds</td></tr>";
    echo "<tr><td>Total number of items stored by this server ever since it started </td><td>".$status["total_items"]."</td></tr>";
    echo "<tr><td>Number of open connections </td><td>".$status["curr_connections"]."</td></tr>";
    echo "<tr><td>Total number of connections opened since the server started running </td><td>".$status["total_connections"]."</td></tr>";
    echo "<tr><td>Number of connection structures allocated by the server </td><td>".$status["connection_structures"]."</td></tr>";
    echo "<tr><td>Cumulative number of retrieval requests </td><td>".$status["cmd_get"]."</td></tr>";
    echo "<tr><td> Cumulative number of storage requests </td><td>".$status["cmd_set"]."</td></tr>";

    $percCacheHit=((real)$status["get_hits"]/ (real)$status["cmd_get"] *100);
    $percCacheHit=round($percCacheHit,3);
    $percCacheMiss=100-$percCacheHit;

    echo "<tr><td>Number of keys that have been requested and found present </td><td>".$status["get_hits"]." ($percCacheHit%)</td></tr>";
    echo "<tr><td>Number of items that have been requested and not found </td><td>".$status["get_misses"]." ($percCacheMiss%)</td></tr>";

    $MBRead= round($status["bytes_read"]/(1024*1024));
    echo "<tr><td>Total number of bytes read by this server from network </td><td>".$MBRead."MB</td></tr>";
    $MBWrite=round($status["bytes_written"]/(1024*1024)) ;
    echo "<tr><td>Total number of bytes sent by this server to network </td><td>".$MBWrite."MB</td></tr>";
    $MBSize=round($status["limit_maxbytes"]/(1024*1024)) ;
    echo "<tr><td>Number of bytes this server is allowed to use for storage.</td><td>".$MBSize."MB</td></tr>";
    echo "<tr><td>Number of valid items removed from cache to free memory for new items.</td><td>".$status["evictions"]."</td></tr>";

    echo "</table>";
}

function ShowRealms()
{
    echo '<h1>Realms</h1>';

    global $db;

    $sql = <<<EOF
SELECT r.house, r.region, r.canonical, sch.nextcheck scheduled, hc.nextcheck delayednext, sch.lastupdate, sch.mindelta, sch.avgdelta, sch.maxdelta
FROM tblRealm r
left join tblHouseCheck hc on hc.house = r.house
left join (
        select deltas.house, timestampadd(second, least(ifnull(min(delta)+15-120, 45*60), 150*60), max(deltas.updated)) nextcheck, max(deltas.updated) lastupdate, min(delta) mindelta, round(avg(delta)) avgdelta, max(delta) maxdelta
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
order by unix_timestamp(ifnull(delayednext, scheduled)) - unix_timestamp(scheduled) desc, ifnull(delayednext, scheduled), sch.lastupdate, region, canonical
EOF;

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = DBMapArray($result, null);

    echo '<table cellspacing="0"><tr><th>House</th><th>Region</th><th>Canonical</th><th>Updated</th><th>Scheduled</th><th>Min</th><th>Avg</th><th>Max</th></tr>';
    foreach ($rows as &$row) {
        echo '<tr><td class="r">' . $row['house'] . '</td>';
        echo '<td>' . $row['region'] . '</td>';
        echo '<td><a href="?bnetget=' . $row['region'] . '-' . $row['canonical'] . '">' . $row['canonical'] . '</a></td>';

        if (is_null($row['lastupdate'])) {
            echo '<td>&nbsp;</td>';
        } else {
            $css = '';
            $updateDelta = time() - strtotime($row['lastupdate']);
            if ($updateDelta > ($row['maxdelta'] + 180)) {
                $css = 'color: red';
            } elseif ($updateDelta > $row['avgdelta'] + 60) {
                $css = 'color: #999900';
            }
            echo '<td style="' . $css . '" class="r">' . TimeDiff(strtotime($row['lastupdate'])) . '</td>';
        }

        if (is_null($row['scheduled'])) {
            echo '<td>&nbsp;</td>';
        } elseif (is_null($row['delayednext'])) {
            echo '<td style="color: green" class="r">' . TimeDiff(strtotime($row['scheduled'])) . '</td>';
        } else {
            echo '<td style="color: #999900" class="r">' . TimeDiff(strtotime($row['delayednext'])) . '</td>';
        }

        echo '<td class="r">' . round(intval($row['mindelta'], 10) / 60) . ' min</td>';
        echo '<td class="r">' . round(intval($row['avgdelta'], 10) / 60) . ' min</td>';
        echo '<td class="r">' . round(intval($row['maxdelta'], 10) / 60) . ' min</td>';

        echo '</tr>';
    }
    unset($row);

    echo '</table>';
}

function ShowLogs()
{
    echo '<h1>Logs</h1>';
    $dir = realpath(__DIR__ . '/../logs');
    $files = array_values(
        array_filter(
            glob($dir . '/*.log'), function ($f) {
                $parts = explode('.', basename($f));
                if (count($parts) == 2) {
                    return true;
                }

                for ($x = 0; $x < count($parts) - 1; $x++) {
                    switch ($parts[$x]) {
                        case 'yesterday':
                        case 'lastweek':
                            return false;
                    }
                }
                return true;
            }
        )
    );

    sort($files);

    foreach ($files as $path) {
        ob_start();
        switch (basename($path)) {
            case 'backupdata.log':
            case 'backupuser.log':
                passthru('grep -v '.escapeshellarg('^--').' '.escapeshellarg($path).' | tail -n 20');
                break;
            case 'error.undermine.log':
            case 'error.wowtoken.log':
                passthru('grep -v '.escapeshellarg('SSL:').' '.escapeshellarg($path).' | tail -n 20');
                break;
            case 'scripterrors.log':
                passthru('grep -v '.escapeshellarg('worldofwarcraft.com/auction-data/').' '.escapeshellarg($path).' | tail -n 20');
                break;
            case 'private.access.log':
            case 'error.private.log':
                passthru('grep -v '.escapeshellarg('^'.$_SERVER['REMOTE_ADDR'].' ').' '.escapeshellarg($path).' | tail -n 20');
                break;
            default:
                passthru('tail -n 20 ' . escapeshellarg($path));
                break;
        }
        $log = ob_get_clean();

        echo '<h2>' . htmlentities($path) . '</h2>';
        echo '<pre>' . htmlentities($log) . '</pre>';
    }
}

function ShowErrors()
{
    echo '<h1>Errors</h1>';
    echo '<pre>';
    passthru('cat '.escapeshellarg(__DIR__.'/../crontab.txt').' | grep php | grep -o \'/var/newsstand/logs/.*.log\' | sort -u | xargs grep \'Fatal error\'');
    echo '</pre>';
}