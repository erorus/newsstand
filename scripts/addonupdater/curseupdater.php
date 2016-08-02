<?php

date_default_timezone_set('UTC');

// http://wow.curseforge.com/game-versions.json
//$gameversion = 149; //4.0.6

if ($argc < 2) {
    echo "Run curseupdater.sh\n";
    exit(1);
}

$fpath = $argv[1];

if (substr($fpath,-1) != '/') $fpath .= '/';
$filpath = $fpath.'TheUndermineJournal.zip';

//echo "\n-----\n$fpath\n-----\n";

if (!file_exists($filpath)) {
    echo "Could not find $filpath\n";
    exit(2);
}

$versionjson = `wget -O - --quiet "https://wow.curseforge.com/game-versions.json"`;
$json = json_decode($versionjson,true,12);
krsort($json);
foreach ($json as $vers => $o)
    if (!$o['is_development']) {
        $gameversion = $vers;
        break;
    }

/*
echo $gameversion."\n";
print_r($o);
die();
*/

function mimeset(&$a) {
    do {
        $boundary = '';
        for ($x = 0; $x < 16; $x++)
            $boundary .= chr(mt_rand(97,122));

        $foundit = false;
        foreach ($a as $i)
            if (isset($i['data']) && (strpos($i['data'],$boundary) !== false)) {
                $foundit = true;
                break;
            }

    } while ($foundit);

    //echo "Boundary: $boundary\n";
    $tr = '';

    foreach ($a as $i)
        if (isset($i['data']) && isset($i['headers'])) {
            $tr .= "--$boundary\r\n";
            foreach ($i['headers'] as $n => $v) $tr .= "$n: $v\r\n";
            $tr .= "\r\n".$i['data']."\r\n";
        }

    $tr .= "--$boundary--\r\n\r\n";

    return $tr;
}

$m = array();

$fil = file_get_contents($filpath);
$version = Date('oW');
$m[] = array('data' => $fil, 'headers' => array('Content-Disposition' => 'form-data; name="file"; filename="TheUndermineJournal.zip"', 'Content-Type' => 'application/zip', 'Content-Transfer-Encoding' => 'binary'));

$m[] = array('data' => 'The Undermine Journal', 'headers' => array('Content-Disposition' => 'form-data; name="name"', 'Content-Type' => 'text/plain;charset=UTF-8'));
$m[] = array('data' => $gameversion, 'headers' => array('Content-Disposition' => 'form-data; name="game_versions"', 'Content-Type' => 'text/plain;charset=UTF-8'));
$m[] = array('data' => 'r', 'headers' => array('Content-Disposition' => 'form-data; name="file_type"', 'Content-Type' => 'text/plain;charset=UTF-8'));
$m[] = array('data' => 'Automatic data update for '.Date('l, F j, Y'), 'headers' => array('Content-Disposition' => 'form-data; name="change_log"', 'Content-Type' => 'text/plain;charset=UTF-8'));
$m[] = array('data' => 'plain', 'headers' => array('Content-Disposition' => 'form-data; name="change_markup_type"', 'Content-Type' => 'text/plain;charset=UTF-8'));

$m[] = array('data' => '', 'headers' => array('Content-Disposition' => 'form-data; name="known_caveats"', 'Content-Type' => 'text/plain;charset=UTF-8'));
$m[] = array('data' => 'plain', 'headers' => array('Content-Disposition' => 'form-data; name="caveats_markup_type"', 'Content-Type' => 'text/plain;charset=UTF-8'));

$t = mimeset($m);

file_put_contents($fpath.'topost.txt',$t);

preg_match('/--([a-z]+)/',$t,$res);
$boundary = $res[1];

echo $boundary;

exit(0);
