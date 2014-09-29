<?php

require_once('../incl/incl.php');
require_once('../incl/api.incl.php');

$url = '/';
if (isset($_GET['category']) && isset($_GET['realm']) && preg_match('/^[AH]-/', $_GET['realm']))
{
    $faction = substr($_GET['realm'], 0, 1) == 'H' ? '/horde/' : '/alliance/';
    $toMatch = strtolower(trim(substr($_GET['realm'],2)));
    $realms = GetRealms(GetSiteRegion());
    $realm = '';
    foreach ($realms as $realmRow)
    {
        if (strtolower($realmRow['name']) == $toMatch)
        {
            $realm = $realmRow['slug'];
            break;
        }
    }

    if ($realm)
        $url .= '#'.$realm.$faction.'category/'.$_GET['category'];
}

header('HTTP/1.1 301 Moved Permanently');
header('Location: '.$url);