<?php

require_once '../../incl/incl.php';
require_once '../../incl/memcache.incl.php';
require_once '../../incl/api.incl.php';
require_once '../../incl/patreon.credentials.php';
require_once '../../incl/subscription.incl.php';

$event = $_SERVER['HTTP_X_PATREON_EVENT'] ?? '';
if (strpos($event, 'members:pledge:') !== 0) {
    header('HTTP/1.1 400 Bad Request');
    return;
}

$signature = $_SERVER['HTTP_X_PATREON_SIGNATURE'] ?? '';
if (!$signature) {
    header('HTTP/1.1 400 Bad Request');
    DebugMessage("Received webhook event {$event} without a signature.", E_USER_ERROR);
    return;
}
$body = file_get_contents('php://input');
if (!$body) {
    header('HTTP/1.1 400 Bad Request');
    DebugMessage("Received webhook event {$event} without a body.", E_USER_ERROR);
    return;
}
if ($signature !== hash_hmac('md5', $body, PATREON_WEBHOOK_SECRET)) {
    header('HTTP/1.1 400 Bad Request');
    DebugMessage("Received webhook event {$event} with mismatched signature.", E_USER_ERROR);
    return;
}

$bodyJson = json_decode($body);
if (json_last_error() !== JSON_ERROR_NONE) {
    header('HTTP/1.1 400 Bad Request');
    DebugMessage("Received webhook event {$event} with invalid JSON.", E_USER_ERROR);
    return;
}

$cents = 0;
$campaign = 0;
$patreonUser = 0;
if ($event !== 'members:pledge:delete') {
    $cents = $bodyJson->data->attributes->currently_entitled_amount_cents ?? 0;
}
foreach ($bodyJson->included ?? [] as $entity) {
    switch ($entity->type ?? '') {
        case 'campaign':
            $campaign = $entity->id;
            break;
        case 'user':
            $patreonUser = $entity->id;
            break;
    }
}

if ($campaign != PATREON_CAMPAIGN_ID) {
    header('HTTP/1.1 400 Bad Request');
    DebugMessage("Received webhook event {$event} with for campaign {$campaign}.", E_USER_ERROR);
    return;
}
if (!$patreonUser) {
    header('HTTP/1.1 400 Bad Request');
    DebugMessage("Received webhook event {$event} with no user.", E_USER_ERROR);
    return;
}

$db = DBConnect();

$stmt = $db->prepare('REPLACE INTO tblPatreonLog (patreonUser, cents) VALUES (?, ?)');
$stmt->bind_param('si', $patreonUser, $cents);
$stmt->execute();
$stmt->close();

$sql = 'SELECT user FROM tblUserAuth WHERE provider=\'Patreon\' AND providerid=?';
$stmt = $db->prepare($sql);
$stmt->bind_param('s', $patreonUser);
$stmt->execute();
$result = $stmt->get_result();
$users = DBMapArray($result, null);
$stmt->close();

foreach ($users as $userId) {
    MCDelete(SUBSCRIPTION_PAID_CACHEKEY . $userId);
}

header('Content-Type: text/plain');
echo 'OK';
