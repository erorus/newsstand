<?php

$memcache = new Memcache;
if (!$memcache->connect('127.0.0.1', 11211)) {
    DebugMessage('Cannot connect to memcached!', E_USER_ERROR);
}
$memcache->setCompressThreshold(50 * 1024);

function MCGetHouse($house, $key = 'ts')
{
    global $memcache;

    static $houseKeys = array();
    if ($key == 'ts') {
        if (isset($houseKeys[$house])) {
            return $houseKeys[$house];
        }
        $houseKeys[$house] = $memcache->get('h' . $house . '_ts');
        if ($houseKeys[$house] === false) {
            $houseKeys[$house] = 1;
            MCSetHouse($house, $key, $houseKeys[$house]); // so we don't query a billion times on this key
            $altDb = DBConnect(true);
            if ($altDb) {
                $stmt = $altDb->prepare('SELECT unix_timestamp(updated) FROM tblSnapshot WHERE house = ?');
                $stmt->bind_param('i', $house);
                $stmt->execute();
                $stmt->bind_result($houseKeys[$house]);
                $gotHouse = $stmt->fetch() === true;
                $stmt->close();

                if ($gotHouse) {
                    MCSetHouse($house, $key, $houseKeys[$house]);
                } else {
                    $houseKeys[$house] = 1;
                }

                $altDb->close();
            }
        }
        return $houseKeys[$house];
    }

    return MCGet('h' . $house . '_' . MCGetHouse($house) . '_' . $key);
}

function MCSetHouse($house, $key, $val, $expire = 10800)
{
    global $memcache;

    $prefix = '';
    if ($key != 'ts') {
        $prefix = MCGetHouse($house) . '_';
    }

    $fullKey = 'h' . $house . '_' . $prefix . $key;
    return $memcache->set($fullKey, $val, 0, $expire);
}

function MCGet($key)
{
    global $memcache;

    //if (isset($_GET['refresh']))
    //    return false;

    return $memcache->get($key);
}

function MCSet($key, $val, $expire = 10800)
{
    global $memcache;

    return $memcache->set($key, $val, 0, $expire);
}

function MCAdd($key, $val, $expire = 10800)
{
    global $memcache;

    return $memcache->add($key, $val, 0, $expire);
}

function MCDelete($key)
{
    global $memcache;

    //if (isset($_GET['refresh']))
    //    return false;

    return $memcache->delete($key);
}