<?php

function json_return($json)
{
    if (!is_string($json))
        $json = json_encode($json, JSON_NUMERIC_CHECK);

    header('Content-type: application/json');
    // expires
    echo $json;
    exit;
}

function GetRegion($house)
{
    global $db;

    $house = abs($house);
    if (($tr = MCGet('getregion_'.$house)) !== false)
        return $tr;

    DBConnect();

    $sql = 'SELECT max(region) from `tblRealm` where house=?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();
    $tr = array_pop($tr);

    MCSet('getregion_'.$house, $tr, 24*60*60);

    return $tr;
}

function GetHouse($realm)
{
    global $db;

    if (($tr = MCGet('gethouse_'.$realm)) !== false)
        return $tr;

    DBConnect();

    $sql = 'SELECT house from `tblRealm` where id=?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $realm);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();
    $tr = array_pop($tr);

    MCSet('gethouse_'.$realm, $tr);

    return $tr;
}
