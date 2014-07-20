<?php

$db = false;

if (php_sapi_name() == 'cli')
    error_reporting(E_ALL);

date_default_timezone_set('UTC');

function DebugMessage($message, $debugLevel = E_USER_NOTICE)
{
    if (php_sapi_name() == 'cli')
    {
        if ($debugLevel == E_USER_NOTICE)
            echo Date('Y-m-d H:i:s')." $message\n";
        else
            trigger_error(Date('Y-m-d H:i:s')." $message", $debugLevel);
    }
}

function DBConnect($alternate = false)
{
    global $db;

    static $connected = false;

    if ($connected && !$alternate)
        return $db;

    $host = 'localhost';
    $user = 'newsstand';
    $pass = 'D2seYZcwz3sPcTYt';
    $database = 'newsstand';

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

function DBMapArray(&$result, $key = false, $autoClose = true)
{
    $tr = array();

    while ($row = $result->fetch_assoc())
    {
        if ($key === false)
        {
            $key = array_keys($row);
            $key = array_shift($key);
        }
        if (is_array($key))
            switch (count($key))
            {
                case 1:
                    $tr[$row[$key[0]]] = $row;
                    break;
                case 2:
                    $tr[$row[$key[0]]][$row[$key[1]]] = $row;
                    break;
            }
        elseif (is_null($key))
            $tr[] = $row;
        else
            $tr[$row[$key]] = $row;
    }

    if ($autoClose)
        $result->close();

    return $tr;
}

function FetchHTTP($url, $inHeaders = array(), &$outHeaders = array())
{
    global $fetchHTTPErrorCaught;

    $fetchHTTPErrorCaught = false;
    if (!isset($inHeaders['Connection'])) $inHeaders['Connection']='Keep-Alive';
    $http_opt = array(
        'timeout' => 60,
        'connecttimeout' => 6,
        'headers' => $inHeaders,
        'compress' => true,
        'redirect' => (preg_match('/^https?:\/\/(?:[a-z]+\.)?battle\.net\//',$url) > 0)?0:6
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
    else
        return false;
}

function FetchHTTPError($errno, $errstr, $errfile,  $errline,  $errcontext) {
    global $fetchHTTPErrorCaught;
    $fetchHTTPErrorCaught = true;
    return true;
}

function BattleNetGet($url, $headers = array(), &$outHeaders = array())
{
    if (preg_match('/^https?:\/\/(?:[a-z]+\.)?battle\.net\//',$url))
    {
        $publicKey = 'S63F5VS9YZP4';
        $privateKey = '2295QXK07YZJ';

        $dt = date_format(date_create('now',timezone_open('GMT')),'D, d M Y H:i:s').' GMT';
        // TODO: remove protocol/host from url in $toSign
        $toSign = "GET\n$dt\n$url\n";
        $sig = base64_encode(hmacsha1($privateKey,$toSign));
        $headers = array('Date' => $dt, 'Authorization' => "BNET $publicKey:$sig");;
    }

    return FetchHTTP($url, $headers, $eTag, $outHeaders);
}

function hmacsha1($key,$data) {
    $blocksize=64;
    $hashfunc='sha1';
    if (strlen($key)>$blocksize)
        $key=pack('H*', $hashfunc($key));
    $key=str_pad($key,$blocksize,chr(0x00));
    $ipad=str_repeat(chr(0x36),$blocksize);
    $opad=str_repeat(chr(0x5c),$blocksize);
    $hmac = pack(
        'H*',$hashfunc(
            ($key^$opad).pack(
                'H*',$hashfunc(
                    ($key^$ipad).$data
                )
            )
        )
    );
    return $hmac;
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
