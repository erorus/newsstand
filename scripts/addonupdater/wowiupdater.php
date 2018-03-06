<?php

require_once(__DIR__.'/credentials.incl.php');

date_default_timezone_set('UTC');

if (count($argv) < 3) {
    echo "Run curseupdater.sh\n";
    exit(1);
}

fwrite(STDERR, "Starting WoWI Updater..\n");

$zipPath = $argv[1];
$version = $argv[2];

if (!file_exists($zipPath)) {
    fwrite(STDERR, 'File does not exist: '.$zipPath."\n");
    exit(1);
}

$sh = curl_share_init();
curl_share_setopt($sh, CURLSHOPT_SHARE, CURL_LOCK_DATA_COOKIE);

$curl = curl_init();
curl_setopt_array($curl, [
        CURLOPT_SHARE => $sh,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => [
            'vb_login_username' => WOWI_USERNAME,
            'vb_login_password' => WOWI_PASSWORD,
            'do' => 'login',
            'cookieuser' => 1,
        ],
        CURLOPT_URL => 'https://secure.wowinterface.com/forums/login.php'
    ]);
curl_exec($curl); // sets login cookies
curl_close($curl);

$curl = curl_init();
curl_setopt_array($curl, [
        CURLOPT_SHARE => $sh,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_URL => 'http://www.wowinterface.com/downloads/editfile.php?id=19662'
    ]);

$html = curl_exec($curl);
curl_close($curl);

if (preg_match('/name="securitytoken" value="([^"]+)"/', $html, $res) == 0) {
    fwrite(STDERR, "Could not get security token\n");
    curl_share_close($sh);
    exit(1);
}
$securityToken = $res[1];
fwrite(STDERR, "Security token: $securityToken\n");
if (($c = preg_match_all('/"compatible\[(\d+)\]"/', $html, $res)) == 0) {
    fwrite(STDERR, "Could not get compatible versions\n");
    curl_share_close($sh);
    exit(1);
}
$compatible = 0;
for ($x = 0; $x < $c; $x++) {
    if (intval($res[1][$x]) > $compatible) {
        $compatible = $res[1][$x];
    }
}
fwrite(STDERR, "Compatible: $compatible\n");

if (preg_match('/<textarea name="message"[^>]+>([\w\W]+?)<\/textarea>/', $html, $res) == 0) {
    fwrite(STDERR, "Could not get description\n");
    curl_share_close($sh);
    exit(1);
}
$description = $res[1];
fwrite(STDERR, "Description: $description\n");

$curl = curl_init();
$f = new CURLFile($zipPath,'application/x-zip-compressed','TheUndermineJournal.zip');

curl_setopt_array($curl, [
        CURLOPT_SHARE => $sh,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(30, ceil(filesize($zipPath)/204800)),
        CURLOPT_URL => 'http://www.wowinterface.com/downloads/editfile.php',
        CURLOPT_POSTFIELDS => [
            'replacementfile' => $f,
            'archiveold' => 1,
            'ftitle' => 'The Undermine Journal',
            'version' => $version,
            'wysiwyg' => 0,
            'message' => $description,
            'changelog' => 'Automatic data update for '.date('l, F j, Y'),
            'donatepage' => '',
            'compatible[]' => $compatible,
            'overlaytype' => 0,
            'overlaysid' => 0,
            'allowpa' => 1,
            'certify' => 'yes',
            'docertify' => 'yes',
            's' => '',
            'securitytoken' => $securityToken,
            'op' => 'editfile',
            'id' => '19662',
            'type' => 0,
            'sbutton' => 'Update AddOn',
        ]
    ]);

$html = curl_exec($curl);
curl_close($curl);

if (strpos($html, 'The file has been updated.') === false) {
    fwrite(STDERR, "Some upload error:\n$html\n");
    curl_share_close($sh);
    exit(1);
}

fwrite(STDERR, "WoW Interface update successful.\n");

curl_share_close($sh);



