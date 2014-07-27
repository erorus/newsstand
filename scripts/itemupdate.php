<?php

require_once('../incl/incl.php');

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
);

$ids = NewItems(100);
if (count($ids))
    SaveItems(FetchItems($ids));

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
    global $itemMap;

    $results = array();

    foreach ($items as &$id)
    {
        DebugMessage('Fetching item '.$id);
        $url = 'http://us.battle.net/api/wow/item/'.$id;
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

        $results[$dta['id']] = array('json' => $json);
        foreach ($itemMap as $ours => $details)
        {
            if (!isset($dta[$details['name']]))
            {
                if ($details['required'])
                {
                    DebugMessage('Item '.$dta['id'].' did not have required column '.$details['name'], E_USER_WARNING);
                    unset($results[$dta['id']]);
                    continue 2;
                }
                $dta[$details['name']] = null;
            }
            if (is_bool($dta[$details['name']]))
                $results[$dta['id']][$ours] = $dta[$details['name']] ? 1 : 0;
            else
                $results[$dta['id']][$ours] = $dta[$details['name']];
        }
    }

    return $results;
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
    if (preg_match('/Max Stack: (\d+)/', $item->htmlTooltip, $res) > 0)
        $json['stackable'] = intval($res[1],10);
    if (preg_match('/"sellprice":(\d+)/', $item->jsonEquip, $res) > 0)
        $json['sellPrice'] = intval($res[1],10);

    return json_encode($json);
}