<?php
require_once('../incl/incl.php');

$langs = ['enus','dede','eses','frfr','itit','ptbr','ruru'];
$jsons = [];

DBConnect();
$stmt = $db->prepare('select * from tblDBCItemSubClass');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $key = "{$row['class']}-{$row['subclass']}";
    foreach ($langs as $lang) {
        if (isset($row["name_$lang"])) {
            $jsons[$lang][$key] = $row["name_$lang"];
        }
    }
}
$result->close();
$stmt->close();

foreach ($langs as $lang) {
    if (!isset($jsons[$lang])) {
        continue;
    }
    $fn = __DIR__."/subclass.$lang.json";
    file_put_contents($fn, json_encode($jsons[$lang], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}