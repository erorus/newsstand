<?php

require_once '../../incl/incl.php';
require_once '../../incl/memcache.incl.php';
require_once '../../incl/api.incl.php';
require_once '../../incl/subscription.incl.php';

$fields = array('from', 'subject', 'message');
foreach ($fields as $field) {
    if (!isset($_POST[$field])) {
        json_return(false);
    }
}

if ($_POST['subject'] != "Subject") {
    json_return(false);
}

unset($_POST['subject']);

if (isset($_SERVER['CONTENT_TYPE'])) {
    if (preg_match('/;\s*charset=([^;]+)/i', $_SERVER['CONTENT_TYPE'], $m)) {
        if ($m[1] != 'UTF-8' && in_array($m[1], mb_list_encodings())) {
            foreach ($_POST as &$value) {
                $value = mb_convert_encoding($value, 'UTF-8', $m[1]);
            }
            unset($value);
        }
    }
}

$headers = array();
$headers['Date'] = date(DATE_RFC2822);
$headers['Content-Type'] = 'text/plain; charset=UTF-8; format="flowed"';
$headers['Content-Transfer-Encoding'] = 'base64';
$headers['From'] = 'Contact Form <contactform@from.theunderminejournal.com>';

if (preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/i', $_POST['from'], $res) > 0) {
    $headers['Reply-To'] = $res[0];
}

$_POST['message'] = preg_replace('/\r\n?/', "\n", $_POST['message']);

$body = "Date: " . date('Y-m-d H:i:s') . "\nFrom: " . $_POST['from'] . "\nIP: " . $_SERVER['REMOTE_ADDR'] . "\nUser Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\n";

$banned = BotCheck(true);
$body .= "Banned: " . ($banned['isbanned'] ? 'yes: ' . $banned['reason'] . ' ' . $banned['ip'] : 'no') . "\n";

$loginState = GetLoginState();
$body .= "User: ".(isset($loginState['id']) ? ($loginState['id'] . ' ' . $loginState['name']) : 'none') . "\n";
if (isset($loginState['id'])) {
    $body .= "Paid until: " . date('Y-m-d H:i:s', GetUserPaidUntil($loginState['id'])) . "\n";
}

if (isset($_POST['region'])) {
    $body .= "Region: " . $_POST['region'] . "\n";
}
if (isset($_POST['realm'])) {
    $body .= "Realm: " . $_POST['realm'] . "\n";
}
if (isset($_POST['house'])) {
    $body .= "House: " . $_POST['house'] . "\n";
}

$body .= "\n---------------\n" . $_POST['message'];

$body = wordwrap(base64_encode($body), 70, "\n", true);

$headerString = '';
foreach ($headers as $k => $v) {
    $headerString .= ($headerString == '' ? '' : "\n") . "$k: $v";
}
$result = mail('The Editor <editor@theunderminejournal.com>','Letter to the Editor',$body,$headerString, '-fcontactform@from.theunderminejournal.com');

json_return($result ? array() : false);

