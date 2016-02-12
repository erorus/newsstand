<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');

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

$headers = array();
$headers['Date'] = Date(DATE_RFC2822);
$headers['Content-Type'] = 'text/plain; charset=ISO-8859-1; format=flowed';
$headers['From'] = 'Contact Form <contactform@from.theunderminejournal.com>';

if (preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/i', $_POST['from'], $res) > 0) {
    $headers['Reply-To'] = $res[0];
}

$_POST['message'] = preg_replace('/\r\n?/', "\n", $_POST['message']);

$body = "Date: " . Date('Y-m-d H:i:s') . "\nFrom: " . $_POST['from'] . "\nIP: " . $_SERVER['REMOTE_ADDR'] . "\nUser Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\n";

$banned = BotCheck(true);
$body .= "Banned: " . ($banned['isbanned'] ? 'yes: ' . $banned['reason'] . ' ' . $banned['ip'] : 'no') . "\n";

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

$body = preg_replace_callback('/(?<=^|\n)([^\n]+)(?:\n|$)/', create_function('$m', 'return ((strlen(trim($m[1]))>0)?wordwrap($m[1],66," \n"):"")."\n";'), strip_8bit_chars(utf8_decode(strip_tags(str_replace('&amp;', '&', str_replace('&quot;', '"', str_replace('&lt;', '<', $body)))))));

$headerString = '';
foreach ($headers as $k => $v) {
    $headerString .= ($headerString == '' ? '' : "\n") . "$k: $v";
}
$result = mail('The Editor <editor@theunderminejournal.com>','Letter to the Editor',$body,$headerString, '-fcontactform@from.theunderminejournal.com');

json_return($result ? array() : false);

function strip_8bit_chars($str)
{
    $l = strlen($str);
    for ($x = 0; $x < $l; $x++) {
        if (ord(substr($str, $x, 1)) > 127) {
            $str = substr($str, 0, $x) . '?' . substr($str, $x + 1);
        }
    }
    return $str;
}
