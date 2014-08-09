<?php

$memcache = new Memcache;
if (!$memcache->connect('127.0.0.1', 11211))
    DebugMessage('Cannot connect to memcached!', E_USER_ERROR);

function MCGetHouse($house, $key = 'ts')
{
    global $memcache;

    static $houseKeys = array();
    if ($key == 'ts')
    {
        if (isset($houseKeys[$house]))
            return $houseKeys[$house];
        $houseKeys[$house] = $memcache->get('h'.$house.'_ts');
        if ($houseKeys[$house] === false)
            $houseKeys[$house] = 0;
        return $houseKeys[$house];
    }

    return MCGet('h'.$house.'_'.MCGetHouse($house).'_'.$key);
}

function MCSetHouse($house, $key, $val, $expire = 10800)
{
    global $memcache;

    $prefix = '';
    if ($key != 'ts')
        $prefix = MCGetHouse($house).'_';

    $fullKey = 'h'.$house.'_'.$prefix.$key;
    return $memcache->set($fullKey, $val, false, $expire);
}

function MCGet($key)
{
    global $memcache;

    if (isset($_GET['refresh']))
        return false;

    return $memcache->get($key);
}

function MCSet($key, $val, $expire = 10800)
{
    global $memcache;

    return $memcache->set($key, $val, false, $expire);
}

