<?php
require_once('incl/old.incl.php');

$langs = ['enus','dede','eses','frfr','itit','ptbr','ruru'];
$jsons = [];

do_connect();
$rst = get_rst('select * from tblDBCItemSubClass');
while ($row = next_row($rst)) {
    $key = "{$row['class']}-{$row['subclass']}";
    foreach ($langs as $lang) {
        if (isset($row["name_$lang"])) {
            $jsons[$lang][$key] = $row["name_$lang"];
        }
    }
}

foreach ($langs as $lang) {
    if (!isset($jsons[$lang])) {
        continue;
    }
    $fn = __DIR__."/subclass.$lang.json";
    file_put_contents($fn, json_encode($jsons[$lang], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}