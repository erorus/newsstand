<?php

require_once('memcache.incl.php');

define('THROTTLE_PERIOD', 3600); // seconds
define('THROTTLE_MAXHITS', 200);

function json_return($json)
{
    if ($json === false)
    {
        header('HTTP/1.1 400 Bad Request');
        exit;
    }

    ini_set('zlib.output_compression', 1);

    if (!is_string($json))
        $json = json_encode($json, JSON_NUMERIC_CHECK);

    header('Content-type: application/json');
    echo $json;
    exit;
}

function GetRealms($region)
{
    global $db;

    if ($realms = MCGet('realms_'.$region))
        return $realms;

    DBConnect();

    $stmt = $db->prepare('select * from tblRealm where region = ?');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $realms = DBMapArray($result);
    $stmt->close();

    MCSet('realms_'.$region, $realms);

    return $realms;
}

function GetRegion($house)
{
    global $db;

    $house = abs($house);
    if (($tr = MCGet('getregion_'.$house)) !== false)
        return $tr;

    DBConnect();

    $sql = 'SELECT max(region) from `tblRealm` where house=?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();
    $tr = array_pop($tr);

    MCSet('getregion_'.$house, $tr, 24*60*60);

    return $tr;
}

function GetHouse($realm)
{
    global $db;

    if (($tr = MCGet('gethouse_'.$realm)) !== false)
        return $tr;

    DBConnect();

    $sql = 'SELECT house from `tblRealm` where id=?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $realm);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();
    $tr = array_pop($tr);

    MCSet('gethouse_'.$realm, $tr);

    return $tr;
}

function BotCheck()
{
    $c = UserThrottleCount();
    if ($c > THROTTLE_MAXHITS * 2)
        BanIP();
    if ($c > THROTTLE_MAXHITS)
        json_return(array('captcha' => CaptchaDetails()));
}

function BanIP()
{
    // TODO
    header('HTTP/1.1 429 Too Many Requests');
    exit;
}

function CaptchaDetails()
{
    global $db;

    $cacheKey = 'captcha_'.$_SERVER['REMOTE_ADDR'];
    if (($details = MCGet($cacheKey)) !== false)
        return $details['public'];

    DBConnect();

    $races = array(
        'bloodelf' => 10,
        'draenei' => 11,
        'dwarf' => 3,
        'gnome' => 7,
        'goblin' => 9,
        'human' => 1,
        'nightelf' => 4,
        'orc' => 2,
        'tauren' => 6,
        'troll' => 8,
        'undead' => 5,
    );

    $raceExclude = array(
        $races['bloodelf'] => array($races['nightelf']),
        $races['nightelf'] => array($races['bloodelf']),
    );

    $keys = array_keys($races);
    $goodRace = $races[$keys[rand(0, count($keys)-1)]];

    $howMany = rand(2,3);

    $sql = 'select * from tblCaptcha where race = ? and helm = 0 order by rand() limit ?';

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $goodRace, $howMany);
    $stmt->execute();
    $result = $stmt->get_result();
    $goodRows = DBMapArray($result);
    $stmt->close();

    $sql = 'select * from tblCaptcha where race not in (%s) order by rand() limit %d';
    $exclude = array($goodRace);
    if (isset($raceExclude[$goodRace]))
        $exclude = array_merge($exclude, $raceExclude[$goodRace]);

    $sql = sprintf($sql, implode(',',$exclude), 12 - $howMany);
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $badRows = DBMapArray($result);
    $stmt->close();

    $allRows = array_merge($goodRows, $badRows);
    shuffle($allRows);

    $details = array(
        'answer' => '',
        'public' => array(
            'lookfor' => $goodRace,
            'ids' => array()
        )
    );

    for ($x = 0; $x < count($allRows); $x++)
    {
        if (isset($goodRows[$allRows[$x]['id']]))
            $details['answer'] .= ($x+1);
        $details['public']['ids'][] = $allRows[$x]['id'];
    };

    MCSet($cacheKey, $details);

    return $details['public'];
}

function UserThrottleCount($reset = false)
{
    static $returned = false;
    if (!$reset && $returned !== false)
        return $returned;

    global $memcache;

    $k = 'throttle_%s_'.$_SERVER['REMOTE_ADDR'];
    $kTime = sprintf($k, 'time');
    $kCount = sprintf($k, 'count');

    if ($reset)
    {
        $memcache->delete($kTime);
        return $returned = 0;
    }

    $vals = $memcache->get(array($kTime,$kCount));
    $memcache->set($kTime, time(), false, THROTTLE_PERIOD);
    if (!isset($vals[$kTime]) || !isset($vals[$kCount]) || ($vals[$kTime] < time() - THROTTLE_PERIOD))
    {
        $memcache->set($kCount, 1, false, THROTTLE_PERIOD*2);
        return $returned = 1;
    }
    $memcache->increment($kCount);

    return $returned = ++$vals[$kCount];
}

function HouseETag($house)
{
    $curTag = 'W/"'.MCGetHouse($house).'"';
    $theirTag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : '';

    if ($curTag && $curTag == $theirTag)
    {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }

    header('ETag: '.$curTag);
}
