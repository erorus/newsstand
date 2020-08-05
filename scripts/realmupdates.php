<?php

/**
 * Some tasks to update columns in tblRealm. Safe to run via cron.
 */

require_once __DIR__ . '/../incl/incl.php';
require_once __DIR__ . '/../incl/battlenet.incl.php';

use \Newsstand\HTTP;

RunMeNTimes(1);

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

$regions = [
    'US' => 'en_US',
    'EU' => 'en_GB',
//    'CN' => 'zh_CN',
    'TW' => 'zh_TW',
    'KR' => 'ko_KR',
];
foreach ($regions as $region => $locale) {
    GetBlizzIds($region, $locale);
}

/**
 * Updates the blizzId and blizzConnection columns in tblRealm.
 *
 * @param string $region
 * @param string $locale The default locale for that region
 */
function GetBlizzIds($region, $locale) {
    $requestInfo = GetBattleNetURL($region, 'data/wow/connected-realm/index');
    $json = $requestInfo ? HTTP::Get($requestInfo[0], $requestInfo[1]) : '';
    $data = json_decode($json, true, 512, JSON_BIGINT_AS_STRING);

    if (!isset($data['connected_realms'])) {
        DebugMessage("Could not get connected realms for {$region}");
        return;
    }

    $db = DBConnect();
    $stmt = $db->prepare('update tblRealm set blizzConnection = null where region = ?');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $stmt->close();

    foreach ($data['connected_realms'] as $realmRow) {
        if (!isset($realmRow['href']) || !preg_match('/\/data\/wow\/connected-realm\/(\d+)/', $realmRow['href'], $res)) {
            DebugMessage("Invalid {$region} realm row format: " . json_encode($realmRow));
            return;
        }

        SetConnectedRealm($region, $locale, $res[1]);
    }
}

/**
 * Given a region and connection ID, fetches the list of realms in that connection, and updates the blizzId and
 * blizzConnection columns for those realms in tblRealm.
 *
 * @param string $region
 * @param string $locale The default locale for that region
 * @param int $connectionId
 */
function SetConnectedRealm($region, $locale, $connectionId) {
    DebugMessage("Getting $region connection $connectionId");

    $requestInfo = GetBattleNetURL($region, "data/wow/connected-realm/{$connectionId}?locale={$locale}");
    $json = $requestInfo ? HTTP::Get($requestInfo[0], $requestInfo[1]) : '';
    $data = json_decode($json, true, 512, JSON_BIGINT_AS_STRING);

    if (!isset($data['realms'][0])) {
        DebugMessage("Invalid data format for connection $connectionId\n");
        return;
    }

    $db = DBConnect();
    $stmt = $db->prepare('update tblRealm set blizzConnection=?, blizzId=?, slug=? where region=? and (slug=? or name=?) and blizzConnection is null');
    foreach ($data['realms'] as $realmRow) {
        $stmt->bind_param('iissss', $connectionId, $realmRow['id'], $realmRow['slug'], $region, $realmRow['slug'], $realmRow['name']);
        $stmt->execute();
    }
    $stmt->close();
}
