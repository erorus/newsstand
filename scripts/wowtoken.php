<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');

RunMeNTimes(1);
CatchKill();

define('SNAPSHOT_PATH', '/home/wowtoken/pending/');

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

$loopStart = time();
$loops = 0;
$gotData = [];
while ((!$caughtKill) && (time() < ($loopStart + 60))) {
    heartbeat();
    if (!($region = NextDataFile())) {
        break;
    }
    if ($region !== true) {
        $gotData[] = $region;
    }
    if ($loops++ > 30) {
        break;
    }
}
if ($gotData || (isset($argv[1]) && $argv[1] == 'build')) {
    $gotData = array_unique($gotData);
    if (!$gotData) {
        $gotData = ['US','EU'];
    }
    BuildIncludes($gotData);
    DebugMessage('Done! Started ' . TimeDiff($startTime));
}

function NextDataFile()
{
    $dir = scandir(substr(SNAPSHOT_PATH, 0, -1), SCANDIR_SORT_ASCENDING);
    $gotFile = false;
    foreach ($dir as $fileName) {
        if (preg_match('/^(\d+)-(US|EU)\.lua$/', $fileName, $res)) {
            if (($handle = fopen(SNAPSHOT_PATH . $fileName, 'rb')) === false) {
                continue;
            }

            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                fclose($handle);
                continue;
            }

            if (feof($handle)) {
                fclose($handle);
                unlink(SNAPSHOT_PATH . $fileName);
                continue;
            }

            $gotFile = $fileName;
            break;
        }
    }
    unset($dir);

    if (!$gotFile) {
        return false;
    }

    $snapshot = intval($res[1], 10);
    $region = $res[2];

    DebugMessage(
        "Region $region data file from " . TimeDiff(
            $snapshot, array(
                'parts'     => 2,
                'precision' => 'second'
            )
        )
    );
    $lua = LuaDecode(fread($handle, filesize(SNAPSHOT_PATH . $fileName)), true);

    ftruncate($handle, 0);
    fclose($handle);
    unlink(SNAPSHOT_PATH . $fileName);

    if (!$lua) {
        DebugMessage("Region $region $snapshot data file corrupted!", E_USER_WARNING);
        return true;
    }

    return ParseTokenData($region, $snapshot, $lua);
}

function ParseTokenData($region, $snapshot, &$lua)
{
    global $db;

    if (!isset($lua['now']) || !isset($lua['region'])) {
        DebugMessage("Region $region $snapshot data file does not have snapshot or region!", E_USER_WARNING);
        return false;
    }
    $snapshotString = Date('Y-m-d H:i:s', $lua['now']);
    foreach (['selltime', 'market', 'result', 'guaranteed'] as $col) {
        if (!isset($lua[$col])) {
            $lua[$col] = null;
        }
    }

    $sql = 'replace into tblWowToken (`region`, `when`, `marketgold`, `sellseconds`, `guaranteedgold`, `result`) values (?, ?, floor(?/10000), ?, floor(?/10000), ?)';

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ssiiii',
        $lua['region'],
        $snapshotString,
        $lua['market'],
        $lua['selltime'],
        $lua['guaranteed'],
        $lua['result']
    );
    $stmt->execute();
    $stmt->close();

    return $lua['region'];
}

function LuaDecode($rawLua) {
    $tr = [];
    $c = preg_match_all('/\["([^"]+)"\] = ("[^"]+"|\d+),/', $rawLua, $res);
    if (!$c) {
        return false;
    }
    for ($x = 0; $x < $c; $x++) {
        $tr[$res[1][$x]] = trim($res[2][$x],'"');
    }

    return $tr;
}

function BuildIncludes($regions)
{
    global $db;

    $resultCodes = [
        1 => 'Success',
        2 => 'Disabled',
        3 => 'Other Error',
        4 => 'None For Sale',
        5 => 'Too Many Tokens',
        6 => 'No',
        8 => 'Auctionable Token Owned',
        9 => 'Trial Restricted',
    ];

    $timeZones = [
        'US' => 'America/New_York',
        'EU' => 'Europe/Paris',
    ];

    $htmlFormat = <<<EOF
<div class="table-wrapper">
    <table>
        <tbody>
        <tr>
            <td>Buy Price</td>
            <td>##BUY##g</td>
        </tr>
        <tr>
            <td>Guaranteed Sell Price </td>
            <td>##SELL##g</td>
        </tr>
        <tr>
            <td>Estimated Time to Sell</td>
            <td>##TIMETOSELL##</td>
        </tr>
        <tr>
            <td>API Result</td>
            <td>##RESULT##</td>
        </tr>
        <tr>
            <td>Updated</td>
            <td>##UPDATED##</td>
        </tr>
        </tbody>
    </table>
</div>
EOF;

    foreach ($regions as $region) {
        $fileRegion = strtolower($region);
        if ($fileRegion == 'us') {
            $fileRegion = 'na';
        }
        $filenm = __DIR__.'/../wowtoken/'.$fileRegion.'.incl.html';

        $sql = 'select * from tblWowToken w where region = ? and `when` = (select max(w2.`when`) from tblWowToken w2 where w2.region = ?)';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ss', $region, $region);
        $stmt->execute();
        $result = $stmt->get_result();
        $tokenData = DBMapArray($result, null);
        $tokenData = array_pop($tokenData);
        $stmt->close();

        $d = new DateTime('now', timezone_open($timeZones[$region]));
        $d->setTimestamp(strtotime($tokenData['when']));

        $replacements = [
            'BUY' => number_format($tokenData['marketgold']),
            'SELL' => number_format($tokenData['guaranteedgold']),
            'TIMETOSELL' => TimeDiff($tokenData['sellseconds'] + 1, ['to' => 1, 'parts' => 3, 'precision' => 'minute', 'distance'  => false,]),
            'RESULT' => isset($resultCodes[$tokenData['result']]) ? $resultCodes[$tokenData['result']] : ('Unknown: ' . $tokenData['result']),
            'UPDATED' => $d->format('M jS, Y g:ia T'),
        ];

        $html = preg_replace_callback('/##([a-zA-Z0-9]+)##/', function ($m) use ($replacements) {
                if (isset($replacements[$m[1]])) {
                    return $replacements[$m[1]];
                }
                return $m[0];
            }, $htmlFormat);

        file_put_contents($filenm, $html);
    }
}
