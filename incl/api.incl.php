<?php

function json_return($json)
{
    if (!is_string($json))
        $json = json_encode($json, JSON_NUMERIC_CHECK);

    header('Content-type: application/json');
    echo $json;
    exit;
}