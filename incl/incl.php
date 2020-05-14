<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/NewsstandHTTP.incl.php';
require_once __DIR__ . '/database.credentials.php';

$db = false;

if (PHP_SAPI == 'cli') {
    error_reporting(E_ALL);
}

date_default_timezone_set('UTC');
mb_internal_encoding("UTF-8");

define('HISTORY_DAYS', 14);
define('HISTORY_DAYS_DEEP', 93);
define('SOCKET_FAKE_ITEM_NAME_DESC_ID', 10);
define('MIN_ITEM_LEVEL_PRICING', 175); // items with an item level below this amount, after bonuses are applied, will be considered as the base item level for pricing

define('BONUS_STAT_SET_SPEED',          0x1);
define('BONUS_STAT_SET_LEECH',          0x2);
define('BONUS_STAT_SET_AVOIDANCE',      0x4);
define('BONUS_STAT_SET_INDESTRUCTIBLE', 0x8);
define('BONUS_STAT_SET_CORRUPTION',    0x10);

define('BATTLE_PET_CAGE_ITEM', 82800);

$TIMELEFT_ENUM = [
    'SHORT'     => 1,
    'MEDIUM'    => 2,
    'LONG'      => 3,
    'VERY_LONG' => 4,
];

$VALID_LOCALES = ['enus','dede','eses','frfr','itit','ptbr','ruru','zhtw','kokr'];

function DebugMessage($message, $debugLevel = E_USER_NOTICE)
{
    global $argv;

    $message = str_replace("\n", "\n\t", $message);

    if ($debugLevel != E_USER_NOTICE) {
        $bt = debug_backtrace();
        $bt = (isset($bt[1]) && isset($bt[1]['file'])) ? (' ' . $bt[1]['file'] . (isset($bt[1]['function']) ? (' ' . $bt[1]['function']) : '') . ' Line ' . $bt[1]['line']) : '';

        $pth = realpath(__DIR__ . '/../logs/scripterrors.log');
        if ($pth) {
            $me = (PHP_SAPI == 'cli') ? ('CLI:' . realpath($argv[0])) : ('Web:' . $_SERVER['REQUEST_URI']);
            file_put_contents($pth, date('Y-m-d H:i:s') . " $me$bt $message\n", FILE_APPEND | LOCK_EX);
        }
    }

    static $myPid = false;
    if (!$myPid) {
        $myPid = str_pad(getmypid(), 5, " ", STR_PAD_LEFT);
    }

    if (PHP_SAPI == 'cli') {
        echo date('Y-m-d H:i:s') . " $myPid $message\n";
        if ($debugLevel != E_USER_NOTICE) {
            if (strpos($message, "\n") !== false) {
                $message = substr($message, 0, strpos($message, "\n") - 1);
            }
            trigger_error("\n" . date('Y-m-d H:i:s') . " $myPid $message\n", $debugLevel);
        }
    } elseif ($debugLevel != E_USER_NOTICE) {
        trigger_error($message, $debugLevel);
    }
}

function DBConnect($alternate = false)
{
    global $db;

    static $connected = false;

    if ($connected && !$alternate) {
        return $db;
    }

    $isCLI = (PHP_SAPI == 'cli');

    $host = DATABASE_HOST;
    $user = $isCLI ? DATABASE_USERNAME_CLI : DATABASE_USERNAME_WEB;
    $pass = $isCLI ? DATABASE_PASSWORD_CLI : DATABASE_PASSWORD_WEB;
    $database = DATABASE_SCHEMA;

    $thisDb = new mysqli($host, $user, $pass, $database);
    if ($thisDb->connect_error) {
        if (!$isCLI) {
            if ($thisDb->connect_errno == 1226) { // max_user_connections
                APIMaintenance('+2 minutes', '+2 minutes');
                exit;
            }
        }
        $thisDb = false;
    } else {
        $thisDb->set_charset("utf8");
        $thisDb->query('SET time_zone=\'+0:00\'');
    }

    if (!$alternate) {
        $db = $thisDb;
        $connected = !!$db;
    }

    return $thisDb;
}

// key = false, use 1st column as key
// key = 'abc', use col 'abc' as key
// key = null, no key
// key = array('abc', 'def'), use abc as first key, def as second
// key = array('abc', false), use abc as first key, no key for second

function DBMapArray(&$result, $key = false, $autoClose = true)
{
    $tr = array();
    $singleCol = null;

    while ($row = $result->fetch_assoc()) {
        if (is_null($singleCol)) {
            $singleCol = false;
            if (count(array_keys($row)) == 1) {
                $singleCol = array_keys($row);
                $singleCol = array_shift($singleCol);
            }
        }
        if ($key === false) {
            $key = array_keys($row);
            $key = array_shift($key);
        }
        if (is_array($key)) {
            switch (count($key)) {
                case 1:
                    $tr[$row[$key[0]]] = $singleCol ? $row[$singleCol] : $row;
                    break;
                case 2:
                    if ($key[1]) {
                        $tr[$row[$key[0]]][$row[$key[1]]] = $singleCol ? $row[$singleCol] : $row;
                    } else {
                        $tr[$row[$key[0]]][] = $singleCol ? $row[$singleCol] : $row;
                    }
                    break;
                case 3:
                    if ($key[2]) {
                        $tr[$row[$key[0]]][$row[$key[1]]][$row[$key[2]]] = $row;
                    } else {
                        $tr[$row[$key[0]]][$row[$key[1]]][] = $singleCol ? $row[$singleCol] : $row;
                    }
                    break;
            }
        } elseif (is_null($key)) {
            $tr[] = $singleCol ? $row[$singleCol] : $row;
        } else {
            $tr[$row[$key]] = $singleCol ? $row[$singleCol] : $row;
        }
    }

    if ($autoClose) {
        $result->close();
    }

    return $tr;
}

function TimeDiff($time, $opt = array())
{
    if (is_null($time)) {
        return '';
    }

    // The default values
    $defOptions = array(
        'to'        => 0,
        'parts'     => 2,
        'precision' => 'minute',
        'distance'  => true,
        'separator' => ', '
    );
    $opt = array_merge($defOptions, $opt);
    // Default to current time if no to point is given
    (!$opt['to']) && ($opt['to'] = time());
    // Init an empty string
    $str = '';
    // To or From computation
    $diff = ($opt['to'] > $time) ? $opt['to'] - $time : $time - $opt['to'];
    // An array of label => periods of seconds;
    $periods = array(
        'decade' => 315569260,
        'year'   => 31556926,
        'month'  => 2629744,
        'week'   => 604800,
        'day'    => 86400,
        'hour'   => 3600,
        'minute' => 60,
        'second' => 1
    );
    // Round to precision
    if ($opt['precision'] != 'second') {
        $diff = round(($diff / $periods[$opt['precision']])) * $periods[$opt['precision']];
    }
    // Report the value is 'less than 1 ' precision period away
    (0 == $diff) && ($str = 'less than 1 ' . $opt['precision']);
    // Loop over each period
    foreach ($periods as $label => $value) {
        // Stitch together the time difference string
        (($x = floor($diff / $value)) && $opt['parts']--) && $str .= ($str ? $opt['separator'] : '') . ($x . ' ' . $label . ($x > 1 ? 's' : ''));
        // Stop processing if no more parts are going to be reported.
        if ($opt['parts'] == 0 || $label == $opt['precision']) {
            break;
        }
        // Get ready for the next pass
        $diff -= $x * $value;
    }
    $opt['distance'] && $str .= ($str && $opt['to'] >= $time) ? ' ago' : ' away';
    return $str;
}

function CatchKill($use = true)
{
    static $caughtKill = false;
    static $setCatch = false;

    if (PHP_SAPI != 'cli') {
        DebugMessage('Cannot catch kill if not CLI', E_USER_WARNING);
        return false;
    }

    if (!$use) {
        $setCatch = $caughtKill = false;
        return pcntl_signal(SIGTERM, SIG_DFL);
    }

    if ($setCatch) {
        pcntl_signal_dispatch();
        return $caughtKill;
    }

    $setCatch = true;

    pcntl_signal(SIGTERM, function($sig) use (&$caughtKill) {
        if ($sig == SIGTERM) {
            $caughtKill = true;
            DebugMessage('Caught kill message, exiting soon..');
        }
    });

    return false;
}

function RunMeNTimes($howMany = 1)
{
    global $argv;

    if (PHP_SAPI != 'cli') {
        DebugMessage('Cannot run once if not CLI', E_USER_WARNING);
        return;
    }

    if (intval(shell_exec('ps -o args -C php | grep ' . escapeshellarg(implode(' ', $argv)) . ' | wc -l')) > $howMany) {
        die();
    }
}

// pass in nothing to get whether out of maintenance ("false") or timestamp when maintenance is expected to end
// pass in timestamp to set maintenance and when it's expected to end
function APIMaintenance($when = -1, $expire = false) {
    if (!function_exists('MCGet')) {
        DebugMessage('Tried to test for APIMaintenance without memcache loaded!', E_USER_ERROR);
    }
    $cacheKey = 'APIMaintenance';

    if ($when == -1) {
        return MCGet($cacheKey);
    }
    if ($when === false) {
        $when = 0;
    }
    if (!is_numeric($when)) {
        $when = strtotime($when);
    }
    if ($when) {
        if ($expire == false) {
            $expire = $when + 72*60*60;
        } elseif (!is_numeric($expire)) {
            $expire = strtotime($expire);
        }
        DebugMessage('Setting API maintenance mode, expected to end '.TimeDiff($when).', maximum '.TimeDiff($expire));
        MCSet($cacheKey, $when, $expire);
    } else {
        DebugMessage('Ending API maintenance mode.');
        MCDelete($cacheKey);
    }

    return $when;
}

function NewsstandMail($address, $name, $subject, $message, $locale='enus')
{
    $address = trim($address);
    if ($address == '') {
        return false;
    }

    $name = trim($name);
    if ($name == '') {
        $name = $address;
    }

    $db = DBConnect(true);
    if ($db === false) {
        DebugMessage("Could not connect to DB to send mail to $address");
        return false;
    }

    $cnt = 0;
    $stmt = $db->prepare('select count(*) from tblEmailBlocked where address=?');
    $stmt->bind_param('s', $address);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();

    if ($cnt > 0) {
        DebugMessage("Could not send mail to $address, address is in tblEmailBlocked");
        $db->close();
        return false;
    }

    $lang = GetLang($locale);

    $sha1id = sha1('' . time() . '|' . $address . '|' . $subject . '|' . $message, true);
    $mailId = rtrim(strtr(base64_encode($sha1id), '+/=', '-_,'), ',');

    $headers = "From: The Undermine Journal <notifications@from.theunderminejournal.com>\n";
    $headers .= "Reply-To: Remove My Address <notifications@from.theunderminejournal.com>\n";
    $headers .= "Date: " . date(DATE_RFC2822) . "\n";
    $headers .= "X-Undermine-MailID: $mailId\n";
    $headers .= "References: <{$mailId}@no-thread.from.theunderminejournal.com>\n";

    $headers .= "MIME-Version: 1.0\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"next-MIME-part------\"\n";

    $html = $message;
    $html .= "<br><hr><br>" . sprintf($lang['emailFooter'], "https://theunderminejournal.com/", $address) . "<br><br>";
    $txt = $html;

    $html .= sprintf($lang['emailBlocklistHTML'], "https://theunderminejournal.com/emailremove.php?mailid=$mailId");
    $txt .= sprintf($lang['emailBlocklistText'], "https://theunderminejournal.com/emailremove.php", $mailId);

    $html .= "<br><br>Message ID: $mailId";
    $txt .= "<br><br>Message ID: $mailId";

    $full = "This is a multi-part message in MIME format.";

    $full .= "\n--next-MIME-part------\nContent-Type: text/plain; charset=ISO-8859-1; format=flowed\n\n";
    $full .= preg_replace_callback('/(?<=^|\n)([^\n]*)(?:\n|$)/',
        function($m) {
            return wordwrap($m[1], 66, " \n")."\n";
        },
        htmlspecialchars_decode(
            strip_tags(
                preg_replace('/\s*<br>/i', "\n",
                    str_replace("\n", ' ', utf8_decode($txt))
                )
            ), ENT_COMPAT | ENT_HTML5
        )
    );

    $full .= "\n--next-MIME-part------\nContent-Type: text/html; charset=UTF-8\nContent-Transfer-Encoding: base64\n\n";
    $full .= wordwrap(base64_encode($html), 70, "\n", true);

    $full .= "\n--next-MIME-part--------\n";

    if (preg_match('/[\x80-\xFF]/', $subject)) {
        $subject = '=?UTF-8?B?' . base64_encode($subject) . "?=";
    }

    $headers = trim($headers);

    $tr = mail("$name <$address>", $subject, $full, $headers, '-fnotifications@from.theunderminejournal.com');

    if ($tr) {
        $stmt = $db->prepare('insert into tblEmailLog (sha1id, sent, recipient) values (?, NOW(), ?)');
        $stmt->bind_param('ss', $sha1id, $address);
        $stmt->execute();
        $stmt->close();
    } else {
        DebugMessage("Some error sending mail to $address");
    }

    $db->close();
    return $tr;
}

function GetAddressByMailID($mailId)
{
    $sha1Id = base64_decode(strtr($mailId, '-_,', '+/='));

    $db = DBConnect();
    $address = false;
    $addDate = false;
    $stmt = $db->prepare('select l.recipient, unix_timestamp(b.added) from tblEmailLog l left join tblEmailBlocked b on b.address = l.recipient where l.sha1id = ?');
    $stmt->bind_param('s', $sha1Id);
    $stmt->execute();
    $stmt->bind_result($address, $addDate);
    if (!$stmt->fetch()) {
        $address = false;
    }
    $stmt->close();

    return $address ? ['address' => $address, 'blocked' => $addDate] : false;
}

function GetLang($lang) {
    static $locales = [];
    if (isset($locales[$lang])) {
        return $locales[$lang];
    }

    $path = __DIR__.'/../public/js/locale/'.strtolower($lang).'.json';
    if (!file_exists($path)) {
        $locales[$lang] = false;
        return $locales[$lang];
    }

    $json = json_decode(file_get_contents($path), true);
    if (json_last_error() != JSON_ERROR_NONE) {
        DebugMessage("Error decoding JSON at $path: ".json_last_error_msg());
    }
    if ($lang != 'enus') {
        $enus = GetLang('enus');
        $json = array_replace_recursive($enus, $json);
    }

    $locales[$lang] = $json;

    return $locales[$lang];
}

function FixNullKeys($a) {
    $r = [];
    foreach ($a as $k => $v) {
        $r[trim($k)] = $v;
    }
    return $r;
}
