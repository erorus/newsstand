<?php

require_once('../../incl/incl.php');
require_once('../../incl/memcache.incl.php');
require_once('../../incl/api.incl.php');
require_once('../../incl/battlenet.incl.php');
require_once('../../incl/NewsstandHTTP.incl.php');

if (!isset($_GET['realm']) || !isset($_GET['char'])) {
    json_return(array());
}

$realm = intval($_GET['realm'], 10);
$player = mb_convert_case(mb_substr($_GET['char'], 0, 12), MB_CASE_LOWER);

BotCheck();

$json = array(
    'pets' => PlayerPets($realm, $player),
);

json_return($json);

function PlayerPets($realmId, $player) {
    $realm = GetRealmById($realmId);
    if (!$realm) {
        return [];
    }

    $cacheKey = sprintf('characterpets_%d_%s', $realmId, md5($player));

    $pets = MCGet($cacheKey);
    if ($pets !== false) {
        return $pets;
    }

    $pets = [];

    $data = GetBattleNetURL($realm['region'], sprintf('wow/character/%s/%s?fields=pets', $realm['slug'], $player));
    if ($data) {
        $data = \Newsstand\HTTP::Get($data[0], $data[1]);
    }

    if (!$data) {
        MCSet($cacheKey, $pets, 300);
        return $pets;
    }

    $json = json_decode($data, true);
    if (json_last_error() != JSON_ERROR_NONE) {
        MCSet($cacheKey, $pets, 300);
        return $pets;
    }

    if (isset($json['pets']['collected'])) {
        foreach ($json['pets']['collected'] as $pet) {
            if (!isset($pet['stats']['speciesId'])) {
                continue;
            }
            $species = $pet['stats']['speciesId'];
            if (!$species) {
                continue;
            }
            if (!isset($pets[$species])) {
                $pets[$species] = 0;
            }
            $pets[$species]++;
        }
    }

    MCSet($cacheKey, $pets, 3 * 60 * 60);

    return $pets;
}
