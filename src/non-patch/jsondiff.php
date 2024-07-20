<?php

if (count($argv) != 3) {
    echo 'jsondiff.php currentFile.json fileWithChanges.json';
    exit;
}

$oldFile = json_decode(file_get_contents($argv[1]), true);
$changeFile = json_decode(file_get_contents($argv[2]), true);
if (json_last_error() != JSON_ERROR_NONE) {
    echo "Json error for ", $argv[2], " ", json_last_error_msg(), "\n";
    return;
}

RecurseDiff($oldFile, $changeFile);

function RecurseDiff($old, $new, $levels = '') {
    foreach ($old as $k => $v) {
        if (!isset($new[$k])) {
            continue;
        }
        if (is_array($v)) {
            RecurseDiff($v, $new[$k], "$levels/$k");
            continue;
        }
        if ($v != $new[$k]) {
            echo "$levels/$k was [{$v}] now [{$new[$k]}].\n";
        }
    }
}
