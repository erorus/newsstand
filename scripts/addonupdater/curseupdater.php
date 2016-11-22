<?php

require_once(__DIR__.'/credentials.incl.php');
require_once(__DIR__.'/../../incl/NewsstandHTTP.incl.php');

use \Newsstand\HTTP;

date_default_timezone_set('UTC');

if (count($argv) < 2) {
    echo "Run curseupdater.sh\n";
    exit(1);
}

fwrite(STDERR, "Starting Curse Updater..\n");

$zipPath = $argv[1];

if (!file_exists($zipPath)) {
    fwrite(STDERR, 'File does not exist: '.$zipPath."\n");
    exit(1);
}

function GetLatestGameVersionID() {
    $url = sprintf("https://wow.curseforge.com/api/game/versions?token=%s", CURSEFORGE_API_TOKEN);
    $json = HTTP::Get($url);
    if (!$json) {
        trigger_error("Empty response from curseforge game versions");
        return false;
    }
    $json = json_decode($json, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        trigger_error("Invalid json response from curseforge game versions");
        return false;
    }
    if (!count($json) || !isset($json[0]['id']) || !isset($json[0]['name'])) {
        trigger_error("Unknown json response from curseforge game versions");
        return false;
    }
    usort($json, function($a,$b){
        return version_compare($a['name'], $b['name']);
    });
    $latest = array_pop($json);
    return $latest['id'];
}

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

    $tr = '';

    foreach ($a as $i)
        if (isset($i['data']) && isset($i['headers'])) {
            $tr .= "--$boundary\r\n";
            foreach ($i['headers'] as $n => $v) $tr .= "$n: $v\r\n";
            $tr .= "\r\n".$i['data']."\r\n";
        }

    $tr .= "--$boundary--\r\n\r\n";

    return [$tr, $boundary];
}

$latestVersion = GetLatestGameVersionID();
if (!$latestVersion) {
    exit(1);
}

$metaData = [
    'changelog' => sprintf('Automatic data update for %s', date('l, F j, Y')),
    'gameVersions' => [$latestVersion],
    'releaseType' => 'release',
];

$postFields = [];
$postFields[] = [
    'data' => file_get_contents($zipPath),
    'headers' => [
        'Content-Disposition' => 'form-data; name="file"; filename="TheUndermineJournal.zip"',
        'Content-Type' => 'application/zip',
        'Content-Transfer-Encoding' => 'binary',
        ]
];
$postFields[] = [
    'data' => json_encode($metaData),
    'headers' => [
        'Content-Disposition' => 'form-data; name="metadata"',
        'Content-Type' => 'application/json;charset=UTF-8',
    ]
];

list($toPost, $boundary) = mimeset($postFields);
unset($postFields);

fwrite(STDERR, sprintf("Starting upload of %d bytes..\n", strlen($toPost)));

$responseHeaders = [];

$result = HTTP::Post(sprintf('https://wow.curseforge.com/api/projects/%d/upload-file', CURSEFORGE_PROJECT_ID), $toPost, [
    sprintf('Content-Type: multipart/form-data; boundary=%s', $boundary),
    sprintf('X-Api-Token: %s', CURSEFORGE_API_TOKEN),
], $responseHeaders);

$json = [];
if ($result) {
    $json = json_decode($result, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        $json = [];
    }
}
if (isset($json['id'])) {
    fwrite(STDERR, sprintf("Uploaded as file ID %d\n", $json['id']));
} else {
    fwrite(STDERR, "Error received from server.\n");
    if (isset($responseHeaders['responseCode'])) {
        fwrite(STDERR, sprintf("Received response code %d\n", $responseHeaders['responseCode']));
    }
    fwrite(STDERR, $result);
    fwrite(STDERR, "\n");
}
