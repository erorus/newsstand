<?php

require_once('../incl/incl.php');

define('SNAPSHOT_PATH', '/var/newsstand/snapshots/');

ini_set('memory_limit','512M');

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

$stmt = $db->prepare('select house, region from tblRealm group by house');
$stmt->execute();
$result = $stmt->get_result();
$houseRegionCache = DBMapArray($result);
$stmt->close();

$stmt = $db->prepare('select id, region, name from tblRealm');
$stmt->execute();
$result = $stmt->get_result();
$realmCache = DBMapArray($result, array('region','name'));
$stmt->close();

$loopStart = time();
$toSleep = 0;
while (time() < ($loopStart + 60 * 30))
{
    sleep($toSleep);
    $toSleep = NextDataFile();
    if ($toSleep === false)
        break;
}
DebugMessage('Done!');

function NextDataFile()
{
    $dir = scandir(substr(SNAPSHOT_PATH, 0, -1), SCANDIR_SORT_ASCENDING);
    $lockFail = false;
    $gotFile = false;
    foreach ($dir as $fileName)
    {
        if (preg_match('/^(\d+)-(\d+)\.json$/', $fileName, $res))
        {
            if (($handle = fopen(SNAPSHOT_PATH.$fileName, 'rb')) === false)
                continue;

            if (!flock($handle, LOCK_EX | LOCK_NB))
            {
                $lockFail = true;
                fclose($handle);
                continue;
            }

            if (feof($handle))
            {
                fclose($handle);
                unlink(SNAPSHOT_PATH.$fileName);
                continue;
            }

            $gotFile = $fileName;
            break;
        }
    }
    unset($dir);

    if (!$gotFile)
        return $lockFail ? 3 : 10;

    $snapshot = intval($res[1],10);
    $house = intval($res[2],10);

    DebugMessage("Decoding house $house data file from ".TimeDiff($snapshot));
    $json = json_decode(fread($handle, filesize(SNAPSHOT_PATH.$fileName)), true);

    ftruncate($handle, 0);
    fclose($handle);
    unlink(SNAPSHOT_PATH.$fileName);

    if (json_last_error() != JSON_ERROR_NONE)
    {
        DebugMessage("$house $snapshot data file corrupted! ".json_last_error_msg());
        return 0;
    }

    ParseAuctionData($house, $snapshot, $json);
    return 0;
}

function ParseAuctionData($house, $snapshot, &$json)
{
    global $sellerCache, $houseRegionCache;

    static $maxPacketSize = false;

    $ourDb = DBConnect(true);

    if (!$maxPacketSize)
    {
        $stmt = $ourDb->prepare('show variables like \'max_allowed_packet\'');
        $stmt->execute();
        $stmt->bind_result($nonsense, $maxPacketSize);
        $stmt->fetch();
        $stmt->close();
        unset($nonsense);
    }

    $region = $houseRegionCache[$house]['region'];

    $ourDb->begin_transaction();

    $stmt = $ourDb->prepare('delete from tblAuction where house = ?');
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $stmt->close();

    $sellerCache = array();

    $sqlStart = 'insert into tblAuction (house, id, item, quantity, bid, buy, seller, rand, seed) values ';
    $sql = '';

    foreach ($json as $faction => &$factionData)
        if (isset($factionData['auctions']))
        {
            DebugMessage("Parsing ".count($factionData['auctions'])." $faction auctions for house $house");

            foreach ($factionData['auctions'] as &$auction)
            {
                $seller = isset($sellerCache["{$auction['ownerRealm']}|{$auction['owner']}"]) ?
                    $sellerCache["{$auction['ownerRealm']}|{$auction['owner']}"] :
                    GetSellerId($region, $auction['ownerRealm'], $auction['owner'], $snapshot);

                $thisSql = sprintf('(%u, %u, %u, %u, %u, %u, %u, %d, %d)', $house, $auction['auc'], $auction['item'], $auction['quantity'], $auction['bid'], $auction['buyout'], $seller, $auction['rand'], $auction['seed']);
                if (strlen($sql) + 5 + strlen($thisSql) > $maxPacketSize)
                {
                    $ourDb->query($sql);
                    $sql = '';
                }
                $sql .= ($sql == '' ? $sqlStart : ',') . $thisSql;
            }
        }

    if ($sql != '')
        $ourDb->query($sql);

    $ourDb->commit();
    $ourDb->close();
}

function GetSellerId($region, $realm, $seller, $snapshot)
{
    global $db, $sellerCache, $realmCache;

    $id = null;

    if ($seller == '???')
        return $id;

    if (!isset($realmCache[$region][$realm]))
        return $id;

    $stmt = $db->prepare('select id from tblSeller where realm = ? and name = ?');
    $stmt->bind_param('is', $realmCache[$region][$realm]['id'], $seller);
    $stmt->execute();
    $stmt->bind_result($id);
    $gotSeller = $stmt->fetch() === true;
    $stmt->close();

    if ($gotSeller)
    {
        $stmt = $db->prepare('update tblSeller set lastseen = from_unixtime(?) where id = ?');
        $stmt->bind_param('ii', $snapshot, $id);
        $stmt->execute();
        $stmt->close();
    }
    else
    {
        $stmt = $db->prepare('insert into tblSeller (realm, name, firstseen, lastseen) values (?,?,from_unixtime(?),from_unixtime(?))');
        $stmt->bind_param('isii', $realmCache[$region][$realm]['id'], $seller, $snapshot, $snapshot);
        $stmt->execute();
        $stmt->close();

        $id = $db->insert_id;
    }

    $sellerCache["$realm|$seller"] = $id;
    return $id;
}