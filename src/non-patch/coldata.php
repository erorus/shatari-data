<?php

function main() {
    $json = stream_get_contents(STDIN);
    $data = json_decode($json, true);
    $colCount = [];
    foreach ($data as $row) {
        foreach ($row as $key => $value) {
            $colCount[$key] ??= 0;
            $colCount[$key]++;
        }
    }

    $colOrder = array_keys($colCount);
    usort($colOrder, static function (string $a, string $b) use ($colCount): int {
        return ($colCount[$b] <=> $colCount[$a]) ?: strcmp($a, $b);
    });

    $result = ['columns' => $colOrder, 'data' => []];
    foreach ($data as $key => $row) {
        $newRow = [];
        foreach ($colOrder as $colName) {
            $newRow[] = $row[$colName] ?? null;
        }
        while ($newRow && end($newRow) === null) {
            array_pop($newRow);
        }
        $result['data'][$key] = $newRow;
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

main();
