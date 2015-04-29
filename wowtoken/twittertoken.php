<?php

require_once(__DIR__.'/../incl/memcache.incl.php');
require_once(__DIR__.'/../incl/wowtoken-twitter.credentials.php');

header('Content-type: text/plain');

if (isset($_GET['newkey'])) {
    $oauth = new OAuth($twitterCredentials['consumerKey'], $twitterCredentials['consumerSecret']);
    $requestTokenInfo = $oauth->getRequestToken('https://api.twitter.com/oauth/request_token','https://wowtoken.info/twittertoken.php?callback=showkey');
    if (!empty($requestTokenInfo)) {
        MCSet('twittertoken-'.$requestTokenInfo['oauth_token'], $requestTokenInfo, 30*60);
        header('Location: https://api.twitter.com/oauth/authorize?oauth_token='.rawurlencode($requestTokenInfo['oauth_token']));
    } else {
        echo 'No request token info.';
    }
}

if (isset($_GET['callback']) && ($_GET['callback']=='showkey') && isset($_GET['oauth_token'])) {
    $requestTokenInfo['oauth_token'] = $_GET['oauth_token'];
    $verifier = $_GET['oauth_verifier'];
    $requestTokenInfo = MCGet('twittertoken-'.$requestTokenInfo['oauth_token']);
    if ($requestTokenInfo === false) {
        echo 'Could not find cached token';
        exit;
    }
    MCDelete('twittertoken-'.$requestTokenInfo['oauth_token']);

    $oauth = new OAuth($twitterCredentials['consumerKey'], $twitterCredentials['consumerSecret']);
    $oauth->setToken($requestTokenInfo['oauth_token'],$requestTokenInfo['oauth_token_secret']);
    $accessToken = $oauth->getAccessToken('https://api.twitter.com/oauth/access_token', '', $verifier);
    header('Content-type: text/plain');
    print_r($accessToken);
}
