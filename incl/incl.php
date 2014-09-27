<?php

require_once(__DIR__.'/database.credentials.php');

$db = false;

if (php_sapi_name() == 'cli')
    error_reporting(E_ALL);

date_default_timezone_set('UTC');

define('HISTORY_DAYS', 14);

function DebugMessage($message, $debugLevel = E_USER_NOTICE)
{
    static $myPid = false;
    if (!$myPid)
        $myPid = str_pad(getmypid(),5," ",STR_PAD_LEFT);
    
    if (php_sapi_name() == 'cli')
    {
        if ($debugLevel == E_USER_NOTICE)
            echo Date('Y-m-d H:i:s')." $myPid $message\n";
        else
            trigger_error(Date('Y-m-d H:i:s')." $myPid $message", $debugLevel);
    }
    elseif ($debugLevel != E_USER_NOTICE)
        trigger_error($message, $debugLevel);
}

function DBConnect($alternate = false)
{
    global $db;

    static $connected = false;

    if ($connected && !$alternate)
        return $db;

    $isCLI = (php_sapi_name() == 'cli');

    $host = 'localhost';
    $user = $isCLI ? DATABASE_USERNAME_CLI : DATABASE_USERNAME_WEB;
    $pass = $isCLI ? DATABASE_PASSWORD_CLI : DATABASE_PASSWORD_WEB;
    $database = DATABASE_SCHEMA;

    $thisDb = new mysqli($host, $user, $pass, $database);
    if ($thisDb->connect_error)
        $thisDb = false;
    else
    {
        $thisDb->set_charset("utf8");
        $thisDb->query('SET time_zone=\'+0:00\'');
    }

    if (!$alternate)
    {
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

    while ($row = $result->fetch_assoc())
    {
        if (is_null($singleCol))
        {
            $singleCol = false;
            if (count(array_keys($row)) == 1)
            {
                $singleCol = array_keys($row);
                $singleCol = array_shift($singleCol);
            }
        }
        if ($key === false)
        {
            $key = array_keys($row);
            $key = array_shift($key);
        }
        if (is_array($key))
            switch (count($key))
            {
                case 1:
                    $tr[$row[$key[0]]] = $singleCol ? $row[$singleCol] : $row;
                    break;
                case 2:
                    if($key[1])
                        $tr[$row[$key[0]]][$row[$key[1]]] = $singleCol ? $row[$singleCol] : $row;
                    else
                        $tr[$row[$key[0]]][] = $singleCol ? $row[$singleCol] : $row;
                    break;
            }
        elseif (is_null($key))
            $tr[] = $singleCol ? $row[$singleCol] : $row;
        else
            $tr[$row[$key]] = $singleCol ? $row[$singleCol] : $row;
    }

    if ($autoClose)
        $result->close();

    return $tr;
}

function FetchHTTP($url, $inHeaders = array(), &$outHeaders = array())
{
    static $isRetry = false;
    global $fetchHTTPErrorCaught;

    $wasRetry = $isRetry;
    $isRetry = false;

    $fetchHTTPErrorCaught = false;
    if (!isset($inHeaders['Connection'])) $inHeaders['Connection']='Keep-Alive';
    $inHeaders['Accept-Encoding'] = 'gzip';
    $http_opt = array(
        'timeout' => 60,
        'connecttimeout' => 6,
        'headers' => $inHeaders,
        'compress' => true,
        'redirect' => (preg_match('/^https?:\/\/(?:[a-z]+\.)*\bbattle\.net\//',$url) > 0)?0:3
    );
    //if ($eTag) $http_opt['etag'] = $eTag;

    $http_info = array();
    $fetchHTTPErrorCaught = false;
    $oldErrorReporting = error_reporting(error_reporting()|E_WARNING);
    set_error_handler('FetchHTTPError',E_WARNING);
    $data = http_parse_message(http_get($url,$http_opt,$http_info));
    restore_error_handler();
    error_reporting($oldErrorReporting);
    unset($oldErrorReporting);

    if (!$data) {
        $outHeaders = array();
        return false;
    }

    $outHeaders = array_merge(array(
        'httpVersion' => $data->httpVersion,
        'responseCode' => $data->responseCode,
        'responseStatus' => $data->responseStatus,
    ), $data->headers);

    //if (isset($data->headers['Etag']))
    //    $eTag = $data->headers['Etag'];

    if ($fetchHTTPErrorCaught) return false;
    if (preg_match('/^2\d\d$/',$http_info['response_code']) > 0)
        return $data->body;
    elseif (!$wasRetry && isset($data->headers['Retry-After']))
    {
        $delay = intval($data->headers['Retry-After'],10);
        if ($delay > 0 && $delay <= 10)
            sleep($delay);
        $isRetry = true;
        return FetchHTTP($url, $inHeaders, $outHeaders);
    }
    else
        return false;
}

function FetchHTTPError($errno, $errstr, $errfile,  $errline,  $errcontext) {
    global $fetchHTTPErrorCaught;
    $fetchHTTPErrorCaught = true;
    return true;
}

function TimeDiff($time, $opt = array()) {
    if (is_null($time)) return '';

    // The default values
    $defOptions = array(
        'to' => 0,
        'parts' => 2,
        'precision' => 'minute',
        'distance' => TRUE,
        'separator' => ', '
    );
    $opt = array_merge($defOptions, $opt);
    // Default to current time if no to point is given
    (!$opt['to']) && ($opt['to'] = time());
    // Init an empty string
    $str = '';
    // To or From computation
    $diff = ($opt['to'] > $time) ? $opt['to']-$time : $time-$opt['to'];
    // An array of label => periods of seconds;
    $periods = array(
        'decade' => 315569260,
        'year' => 31556926,
        'month' => 2629744,
        'week' => 604800,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60,
        'second' => 1
    );
    // Round to precision
    if ($opt['precision'] != 'second')
        $diff = round(($diff/$periods[$opt['precision']])) * $periods[$opt['precision']];
    // Report the value is 'less than 1 ' precision period away
    (0 == $diff) && ($str = 'less than 1 '.$opt['precision']);
    // Loop over each period
    foreach ($periods as $label => $value) {
        // Stitch together the time difference string
        (($x=floor($diff/$value))&&$opt['parts']--) && $str.=($str?$opt['separator']:'').($x.' '.$label.($x>1?'s':''));
        // Stop processing if no more parts are going to be reported.
        if ($opt['parts'] == 0 || $label == $opt['precision']) break;
        // Get ready for the next pass
        $diff -= $x*$value;
    }
    $opt['distance'] && $str.=($str&&$opt['to']>=$time)?' ago':' away';
    return $str;
}

$caughtKill = false;
function CatchKill()
{
    static $setCatch = false;
    if ($setCatch)
        return;
    $setCatch = true;

    if (php_sapi_name() != 'cli')
    {
        DebugMessage('Cannot catch kill if not CLI', E_USER_WARNING);
        return;
    }

    declare(ticks = 1);
    pcntl_signal(SIGTERM, 'KillSigHandler');
}

function KillSigHandler($sig)
{
    global $caughtKill;
    if ($sig == SIGTERM)
    {
        $caughtKill = true;
        DebugMessage('Caught kill message, exiting soon..');
    }
}

function RunMeNTimes($howMany = 1)
{
    global $argv;

    if (php_sapi_name() != 'cli')
    {
        DebugMessage('Cannot run once if not CLI', E_USER_WARNING);
        return;
    }

    if (intval(shell_exec('ps -o args -C php | grep '.escapeshellarg(implode(' ',$argv)).' | wc -l')) > $howMany) die();
}