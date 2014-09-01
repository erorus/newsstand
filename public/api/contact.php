<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');
require_once('../../incl/mail.credentials.php');

$fields = array('from','subject','message');
foreach ($fields as $field)
    if (!isset($_POST[$field]))
        json_return(false);

if ($_POST['subject'] != "Subject")
    json_return(false);

unset($_POST['subject']);

if (preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/i',$_POST['from'],$res) > 0)
    $mailCredentials['from'] = $res[0];

$headers = array();
$headers['Date'] = Date(DATE_RFC2822);
$headers['Content-Type'] = 'text/plain; charset=ISO-8859-1; format=flowed';


$_POST['message'] = preg_replace('/\r\n?/',"\n",$_POST['message']);

$body = "Date: ".Date('Y-m-d H:i:s')."\nFrom: ".$_POST['from']."\nIP: ".$_SERVER['REMOTE_ADDR']."\nUser Agent: ".$_SERVER['HTTP_USER_AGENT']."\n";

if (isset($_POST['region'])) $body .= "Region: ".$_POST['region']."\n";
if (isset($_POST['realm'])) $body .= "Realm: ".$_POST['realm']."\n";
if (isset($_POST['faction'])) $body .= "Faction: ".$_POST['faction']."\n";
if (isset($_POST['house'])) $body .= "House: ".$_POST['house']."\n";

$body .= "\n---------------\n".$_POST['message'];

$body = preg_replace_callback('/(?<=^|\n)([^\n]+)(?:\n|$)/',create_function('$m','return ((strlen(trim($m[1]))>0)?wordwrap($m[1],66," \n"):"")."\n";'),strip_8bit_chars(utf8_decode(strip_tags(str_replace('&amp;','&',str_replace('&quot;','"',str_replace('&lt;','<',$body)))))));

$cmd = '/bin/mailx ';
foreach ($mailCredentials as $k => $v)
    $cmd .= ' -S ' . escapeshellarg($k . '=' . $v);
$cmd .= ' -s ' . escapeshellarg('Letter to the Editor') . ' ' . escapeshellarg('contactform@theunderminejournal.com');

$f = tempnam('/tmp', 'contactform');
file_put_contents($f, $body);
$cmd .= ' < '.escapeshellarg($f);

$output = array();
$result = 0;
exec($cmd, $output, $result);
unlink($f);

json_return($result === 0 ? array() : false);

function strip_8bit_chars($str) {
    $l = strlen($str);
    for ($x = 0; $x < $l; $x++)
        if (ord(substr($str,$x,1)) > 127)
            $str = substr($str,0,$x).'?'.substr($str,$x+1);
    return $str;
}
