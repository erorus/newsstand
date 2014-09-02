<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

if (!isset($_GET['answer']))
    json_return(false);

$cacheKey = 'captcha_'.$_SERVER['REMOTE_ADDR'];
if (($details = MCGet($cacheKey)) === false)
    json_return(array());

$memcache->delete($cacheKey);
if ($_GET['answer'] == $details['answer'])
{
    UserThrottleCount(true);
    json_return(array());
}

BotCheck();
json_return(array());