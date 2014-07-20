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
    global $houseRegionCache;

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

    $sqlStart = 'insert into tblAuction (house, id, item, quantity, bid, buy, seller, rand, seed) values ';
    $sql = '';

    foreach ($json as $faction => &$factionData)
        if (isset($factionData['auctions']))
        {
            if ($faction == 'neutral')
                continue;
            $factionHouse = ($faction == 'horde') ? ($house * -1) : $house;

            DebugMessage("Parsing ".count($factionData['auctions'])." $faction auctions for house $house");

            $auctionCount =  count($factionData['auctions']);
            $sellerInfo = array();
            for ($x = 0; $x < $auctionCount; $x++)
            {
                $auction =& $factionData['auctions'][$x];
                if ($auction['owner'] == '???')
                    continue;
                if (!isset($sellerInfo[$auction['ownerRealm']]))
                    $sellerInfo[$auction['ownerRealm']] = array();
                if (!isset($sellerInfo[$auction['ownerRealm']][$auction['owner']]))
                    $sellerInfo[$auction['ownerRealm']][$auction['owner']] = array(
                        'new' => 0,
                        'total' => 0,
                        'id' => 0,
                    );
                $sellerInfo[$auction['ownerRealm']][$auction['owner']]['total']++;
            }

            GetSellerIds($maxPacketSize, $region, $sellerInfo, $snapshot);

            for ($x = 0; $x < $auctionCount; $x++)
            {
                $auction =& $factionData['auctions'][$x];

                $thisSql = sprintf('(%d, %u, %u, %u, %u, %u, %u, %d, %d)',
                    $factionHouse,
                    $auction['auc'],
                    $auction['item'],
                    $auction['quantity'],
                    $auction['bid'],
                    $auction['buyout'],
                    $auction['owner'] == '???' ? 0 : $sellerInfo[$auction['ownerRealm']][$auction['owner']]['id'],
                    $auction['rand'],
                    $auction['seed']);
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

function GetSellerIds($maxPacketSize, $region, &$sellerInfo, $snapshot, $afterInsert = false)
{
    global $db, $realmCache;

    $workingRealms = array_keys($sellerInfo);
    $neededInserts = false;

    for ($r = 0; $r < count($workingRealms); $r++)
    {
        if (!isset($realmCache[$region][$workingRealms[$r]]))
            continue;

        $realmName = $workingRealms[$r];

        $realmId = $realmCache[$region][$realmName]['id'];

        $sqlStart = "select name, id from tblSeller where realm = $realmId and name in (";
        $sql = $sqlStart;
        $namesInQuery = 0;
        $names = array_keys($sellerInfo[$realmName]);
        $nameCount = count($names);
        $needInserts = false;

        for ($s = 0; $s < $nameCount; $s++)
        {
            if ($sellerInfo[$realmName][$names[$s]]['id'] != 0)
                continue;

            $nameEscaped = '\''.$db->real_escape_string($names[$s]).'\'';
            if (strlen($sql) + strlen($nameEscaped) + 5 > $maxPacketSize)
            {
                $sql .= ')';

                $stmt = $db->prepare($sql);
                $stmt->execute();
                $result = $stmt->get_result();
                $someIds = DBMapArray($result, null);
                $lastSeenIds = array();

                for ($n = 0; $n < count($someIds); $n++)
                    if (isset($sellerInfo[$realmName][$someIds[$n]['name']]))
                    {
                        $sellerInfo[$realmName][$someIds[$n]['name']]['id'] = $someIds[$n]['id'];
                        $lastSeenIds[] = $someIds[$n]['id'];
                    }

                if (count($lastSeenIds) > 0 && !$afterInsert)
                {
                    $stmt = $db->prepare(sprintf('update tblSeller set lastseen = from_unixtime(%d) where id in (%d)', $snapshot, implode(',',$lastSeenIds)));
                    $stmt->execute();
                    $stmt->close();
                }

                $needInserts |= (count($lastSeenIds) < $namesInQuery);

                $sql = $sqlStart;
                $namesInQuery = 0;
            }
            $sql .= ($namesInQuery++ > 0 ? ',' : '') . $nameEscaped;
        }

        if ($namesInQuery > 0)
        {
            $sql .= ')';

            $stmt = $db->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $someIds = DBMapArray($result, null);
            $lastSeenIds = array();

            for ($n = 0; $n < count($someIds); $n++)
                if (isset($sellerInfo[$realmName][$someIds[$n]['name']]))
                {
                    $sellerInfo[$realmName][$someIds[$n]['name']]['id'] = $someIds[$n]['id'];
                    $lastSeenIds[] = $someIds[$n]['id'];
                }

            if (count($lastSeenIds) > 0 && !$afterInsert)
            {
                $stmt = $db->prepare(sprintf('update tblSeller set lastseen = from_unixtime(%d) where id in (%d)', $snapshot, implode(',',$lastSeenIds)));
                $stmt->execute();
                $stmt->close();
            }

            $needInserts |= (count($lastSeenIds) < $namesInQuery);
        }

        if ($afterInsert || !$needInserts)
            continue;

        $neededInserts = true;

        $sqlStart = "insert into tblSeller (realm, name, firstseen, lastseen) values ";
        $sql = $sqlStart;
        $namesInQuery = 0;

        for ($s = 0; $s < $nameCount; $s++)
        {
            if ($sellerInfo[$realmName][$names[$s]]['id'] != 0)
                continue;

            $insertBit = sprintf('(%d,%s,from_unixtime(%u),from_unixtime(%u))', $realmId, '\''.$db->real_escape_string($names[$s]).'\'', $snapshot, $snapshot);
            if (strlen($sql) + strlen($insertBit) + 5 > $maxPacketSize)
            {

                $stmt = $db->prepare($sql);
                $stmt->execute();
                $stmt->close();

                $sql = $sqlStart;
                $namesInQuery = 0;
            }
            $sql .= ($namesInQuery++ > 0 ? ',' : '') . $insertBit;
        }

        if ($namesInQuery > 0)
        {
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($neededInserts)
        GetSellerIds($maxPacketSize, $region, $sellerInfo, $snapshot, true);

}