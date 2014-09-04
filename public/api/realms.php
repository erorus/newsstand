<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

header('Expires: '.Date(DATE_RFC1123, strtotime('+3 hours')));

$region = (isset($_SERVER['HTTP_HOST']) && (preg_match('/^eu./i',$_SERVER['HTTP_HOST']) > 0))?'EU':'US';

json_return(array('region' => $region, 'realms' => GetRealms($region)));
