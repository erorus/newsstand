<?php

chdir(__DIR__);

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');

RunMeNTimes(1);

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

$itemMap = array(
    'id'                => array('name' => 'id',                'required' => true),
    'name'              => array('name' => 'name',              'required' => true),
    'quality'           => array('name' => 'quality',           'required' => true),
    'level'             => array('name' => 'itemLevel',         'required' => false),
    'class'             => array('name' => 'itemClass',         'required' => true),
    'subclass'          => array('name' => 'itemSubClass',      'required' => true),
    'icon'              => array('name' => 'icon',              'required' => true),
    'stacksize'         => array('name' => 'stackable',         'required' => false),
    'binds'             => array('name' => 'itemBind',          'required' => false),
    'buyfromvendor'     => array('name' => 'buyPrice',          'required' => false),
    'selltovendor'      => array('name' => 'sellPrice',         'required' => false),
    'auctionable'       => array('name' => 'isAuctionable',     'required' => false),
    'vendorsource'      => array('name' => null,                'required' => false),
);

$petMap = array(
    'id'                => array('name' => 'speciesId',         'required' => true),
    'name'              => array('name' => 'name',              'required' => true),
    'type'              => array('name' => 'petTypeId',         'required' => true),
    'icon'              => array('name' => 'icon',              'required' => true),
    'npc'               => array('name' => 'creatureId',        'required' => false),
);

$reparse = false;
for ($x = 0; $x < count($argv); $x++)
    $reparse |= (strpos($argv[$x], 'reparse') !== false);

if ($reparse)
{
    heartbeat();
    $idSet = GetItemsToReparse();
    for ($x = 0; $x < count($idSet); $x++)
        SaveItems($idSet[$x]);
} else {
    heartbeat();
    $ids = NewItems(50);
    if (count($ids))
        SaveItems(FetchItems($ids));

    heartbeat();
    $ids = NewPets(50);
    if (count($ids))
        SavePets(FetchPets($ids));
}


function NewItems($limit = 20)
{
    global $db;

    $sql = <<<EOF
    select `is`.item from
    (select distinct item from tblItemSummary) `is`
    left join tblItem i on i.id = `is`.item
    where i.id is null
    limit ?
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i',$limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = DBMapArray($result);
    $stmt->close();

    return $items;
}

function FetchItems($items)
{
    $results = array();

    foreach ($items as $id)
    {
        heartbeat();
        DebugMessage('Fetching item '.$id);
        $url = 'http://local.theunderminejournal.com/api/bnetapi.php?region=us&path=wow/item/'.$id;
        $json = FetchHTTP($url);
        $dta = json_decode($json, true);
        if ((json_last_error() != JSON_ERROR_NONE) || (!isset($dta['id'])))
        {
            DebugMessage('Error fetching item '.$id.' from battle.net, trying wowhead..');
            $json = FetchWowheadItem($id);
            if ($json === false)
                continue;
            $dta = json_decode($json, true);
            if ((json_last_error() != JSON_ERROR_NONE) || (!isset($dta['id'])))
            {
                DebugMessage('Error parsing Wowhead item '.$id);
                continue;
            }
            DebugMessage('Using wowhead for item '.$id);
        }

        $results[$id] = ParseItem($json);
        if ($results[$id] === false)
            unset($results[$id]);
    }

    return $results;
}

function ParseItem($json)
{
    global $itemMap;

    heartbeat();
    $dta = json_decode($json, true);
    $tr = array('json' => $json);

    foreach ($itemMap as $ours => $details)
    {
        if ($ours == 'vendorsource')
        {
            $tr[$ours] = null;
            if (isset($dta['itemSource']) && isset($dta['itemSource']['sourceType']))
            {
                $tr[$ours] = 0;
                if ($dta['itemSource']['sourceType'] == 'VENDOR')
                    $tr[$ours] = isset($dta['itemSource']['sourceId']) ? $dta['itemSource']['sourceId'] : 1;
            }

            continue;
        }
        if (!isset($dta[$details['name']]))
        {
            if ($details['required'])
            {
                DebugMessage('Item '.$dta['id'].' did not have required column '.$details['name'], E_USER_WARNING);
                return false;
            }
            $dta[$details['name']] = null;
        }
        if (is_bool($dta[$details['name']]))
            $tr[$ours] = $dta[$details['name']] ? 1 : 0;
        else
            $tr[$ours] = $dta[$details['name']];
    }

    return $tr;
}

function SaveItems($items)
{
    global $itemMap, $db;

    $cols = array_keys($itemMap);

    $sql = 'insert into tblItem (`json`,`created`,`updated`,`'.implode('`,`', $cols).'`) values (%s,NOW(),NOW()';
    foreach ($cols as $col)
        $sql .= ',%s';

    $sql .= ') on duplicate key update `updated` = values(`updated`), `json` = values(`json`)';
    foreach ($cols as $col)
        if ($col != 'id')
            $sql .= ", `$col` = values(`$col`)";

    foreach ($items as $item)
    {
        $params[0] = $sql;
        $params[1] = "'" . $db->real_escape_string($item['json']) . "'";
        $x = 2;
        foreach ($cols as $col)
            $params[$x++] = (is_null($item[$col]) ? 'null' : "'" . $db->real_escape_string($item[$col]) . "'");

        $q = call_user_func_array('sprintf', $params);

        if ($db->query($q))
            DebugMessage('Item '.$item['id'].' updated');
        else
            DebugMessage('Error updating item '.$item['id'], E_USER_WARNING);
    }
}

function GetItemsToReparse()
{
    global $db;

    $tr = array();

    $maxSaveSet = 50;

    $stmt = $db->prepare('select `id`, `json` from tblItem where `json` is not null');
    $stmt->execute();
    $result = $stmt->get_result();
    $items = DBMapArray($result, null);
    $stmt->close();

    $set = array();
    for ($x = 0; $x < count($items); $x++)
    {
        heartbeat();
        if (count($set) >= $maxSaveSet)
        {
            $tr[] = $set;
            DebugMessage(str_pad(''.(count($tr) * $maxSaveSet), 6, ' ', STR_PAD_LEFT).' items reparsed.');
            $set = array();
        }
        $set[$items[$x]['id']] = ParseItem($items[$x]['json']);
    }
    if (count($set) >= 0)
        $tr[] = $set;

    return $tr;
}

function FetchWowheadItem($id)
{
    $url = sprintf('http://www.wowhead.com/item=%d&xml', $id);
    $xml = FetchHTTP($url);
    if ($xml == '')
        return false;

    $xml = new SimpleXMLElement($xml);
    $item = $xml->item[0];
    if (!isset($item['id']) || ($item['id'] != $id))
        return false;

    $json = array();
    $json['id'] = $id;
    $json['wowhead'] = true;
    $json['name'] = (string)$item->name;
    $json['quality'] = intval($item->quality['id'],10);
    $json['itemLevel'] = intval($item->level,10);
    $json['itemClass'] = intval($item->{'class'}['id'],10);
    $json['itemSubClass'] = intval($item->subclass['id'],10);
    $json['icon'] = strtolower((string)$item->icon);
    if (preg_match('/Max Stack: (\d+)/', (string)$item->htmlTooltip, $res) > 0)
        $json['stackable'] = intval($res[1],10);
    if (preg_match('/"sellprice":(\d+)/', (string)$item->jsonEquip, $res) > 0)
        $json['sellPrice'] = intval($res[1],10);
    if (preg_match('/"source":\[5\]/', (string)$item->json, $res) > 0)
        $json['itemSource']['sourceType'] = 'VENDOR';

    return json_encode($json);
}

function NewPets($limit = 20)
{
    global $db;

    $sql = <<<EOF
    select `is`.species from
    (select distinct species from tblPetSummary) `is`
    left join tblPet i on i.id = `is`.species
    where i.id is null
    limit ?
EOF;

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i',$limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = DBMapArray($result);
    $stmt->close();

    return $items;
}

function SavePets($pets)
{
    global $petMap, $db;

    $cols = array_keys($petMap);

    $sql = 'insert into tblPet (`json`,`created`,`updated`,`'.implode('`,`', $cols).'`) values (%s,NOW(),NOW()';
    foreach ($cols as $col)
        $sql .= ',%s';

    $sql .= ') on duplicate key update `updated` = values(`updated`), `json` = values(`json`)';
    foreach ($cols as $col)
        if ($col != 'id')
            $sql .= ", `$col` = values(`$col`)";

    foreach ($pets as $pet)
    {
        $params[0] = $sql;
        $params[1] = "'" . $db->real_escape_string($pet['json']) . "'";
        $x = 2;
        foreach ($cols as $col)
            $params[$x++] = (is_null($pet[$col]) ? 'null' : "'" . $db->real_escape_string($pet[$col]) . "'");

        $q = call_user_func_array('sprintf', $params);

        if ($db->query($q))
            DebugMessage('Pet '.$pet['id'].' updated');
        else
            DebugMessage('Error updating pet '.$pet['id'], E_USER_WARNING);
    }
}

function FetchPets($pets)
{
    global $petMap;

    $results = array();

    foreach ($pets as &$id)
    {
        heartbeat();
        DebugMessage('Fetching pet '.$id);
        $url = 'http://local.theunderminejournal.com/api/bnetapi.php?region=us&path=wow/battlePet/species/'.$id;
        $json = FetchHTTP($url);
        $dta = json_decode($json, true);
        if ((json_last_error() != JSON_ERROR_NONE) || (!isset($dta['speciesId'])))
        {
            DebugMessage('Error fetching pet '.$id.' from battle.net..');
            continue;
        }

        $results[$dta['speciesId']] = array('json' => $json);
        foreach ($petMap as $ours => $details)
        {
            if (!isset($dta[$details['name']]))
            {
                if ($details['required'])
                {
                    DebugMessage('Pet '.$dta['speciesId'].' did not have required column '.$details['name'], E_USER_WARNING);
                    unset($results[$dta['speciesId']]);
                    continue 2;
                }
                $dta[$details['name']] = null;
            }
            if (is_bool($dta[$details['name']]))
                $results[$dta['speciesId']][$ours] = $dta[$details['name']] ? 1 : 0;
            else
                $results[$dta['speciesId']][$ours] = $dta[$details['name']];
        }
    }

    return $results;
}
