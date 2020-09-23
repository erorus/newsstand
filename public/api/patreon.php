<?php

require_once '../../incl/incl.php';
require_once '../../incl/memcache.incl.php';
require_once '../../incl/api.incl.php';
require_once '../../incl/patreon.credentials.php';
require_once '../../incl/subscription.incl.php';

use Newsstand\HTTP;

function LoginFinish($hash = '#subscription') {
    header('Location: https://' . $_SERVER["HTTP_HOST"] . '/' . $hash);
    exit;
}

// Get the auth code from the URL
$code = $_GET['code'] ?? null;
if (is_null($code)) {
    LoginFinish();
}

// Make sure the user is logged in with us.
$loginState = GetLoginState();
if (!$loginState) {
    LoginFinish();
}

// Trade the auth code for an access token.
$params = [
    'code' => $code,
    'grant_type' => 'authorization_code',
    'client_id' => PATREON_KEY,
    'client_secret' => PATREON_SECRET,
    'redirect_uri' => 'https://' . strtolower($_SERVER["HTTP_HOST"]) . $_SERVER["SCRIPT_NAME"],
];
$tokens = HTTP::Post(PATREON_TOKEN_URI, $params);
if ($tokens) {
    $tokens = json_decode($tokens);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($tokens->access_token)) {
        $tokens = null;
    }
}
if (!$tokens) {
    LoginFinish('#subscription/nodata');
}

// Use the access token to get the user ID.
$headers = [
    'Authorization: Bearer ' . $tokens->access_token,
];
$profile = HTTP::Get('https://www.patreon.com/api/oauth2/v2/identity', $headers);
if ($profile) {
    $profile = json_decode($profile);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($profile->data->id)) {
        $profile = null;
    }
}
if (!$profile) {
    LoginFinish('#subscription/nodata');
}

if (preg_match('/\D/', $profile->data->id)) {
    LoginFinish('#subscription/baduser');
}

$provider = 'Patreon';
$providerId = intval($profile->data->id);
$userId = $loginState['id'];

$db = DBConnect();

$sql = <<<'SQL'
INSERT INTO tblUserAuth
    (provider, providerid, user, firstseen, lastseen)
VALUES
    (?, ?, ?, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    lastseen = VALUES(lastseen),
    firstseen = IF(user = VALUES(user), firstseen, VALUES(firstseen)),
    user=VALUES(user) 
SQL;

$stmt = $db->prepare($sql);
$stmt->bind_param('ssi', $provider, $providerId, $userId);
$stmt->execute();
$stmt->close();

MCDelete(SUBSCRIPTION_PAID_CACHEKEY . $userId);
ClearLoginStateCache();

LoginFinish();
