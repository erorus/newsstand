<?php
require_once('../incl/incl.php');

$langs = $VALID_LOCALES;
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
    $jsonChunk = preg_replace('/(^|\n)[ \t]+/', '$1        ', substr(json_encode($jsons[$lang], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 1, -1));

    $fn = __DIR__."/../public/js/locale/$lang.json";
    $localeFile = file_get_contents($fn);
    $localeFile = preg_replace('/("itemSubClasses": \{)[^\}]+\n(\s*\})/', '$1'.$jsonChunk.'$2', $localeFile);
    file_put_contents($fn, $localeFile);
}
