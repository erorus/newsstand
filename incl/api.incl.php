<?php

define('THROTTLE_PERIOD', 3600); // seconds
define('THROTTLE_MAXHITS', 200);

function json_return($json)
{
    if ($json === false)
    {
        header('HTTP/1.1 400 Bad Request');
        exit;
    }

    if (!is_string($json))
        $json = json_encode($json, JSON_NUMERIC_CHECK);

    header('Content-type: application/json');
    // expires
    echo $json;
    exit;
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
    // TODO
    return;
    
    $c = UserThrottleCount();
    if ($c > THROTTLE_MAXHITS * 2)
        BanIP();
    if ($c > THROTTLE_MAXHITS)
        json_return(CaptchaDetails());
}

function BanIP()
{
    // TODO
    header('HTTP/1.1 429 Too Many Requests');
    exit;
}

function CaptchaDetails()
{

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