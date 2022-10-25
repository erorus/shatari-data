<?php

$ids = [];

$f = fopen($argv[1], 'r');
while (!feof($f)) {
    $row = fgetcsv($f);
    if (!$row || !is_numeric($row[0])) {
        continue;
    }
    $ids[] = $row[0];
}
fclose($f);

$json = json_decode(file_get_contents($argv[2]), true);
foreach ($ids as $id) {
    $json[$id] ??= 9;
}
ksort($json, SORT_NUMERIC);
file_put_contents($argv[2], json_encode($json, JSON_PRETTY_PRINT));

