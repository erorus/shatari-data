<?php

require_once __DIR__ . '/../incl.php';

const OUTPATH = __DIR__ . '/../../out';

(static function (): void {
    $bonusReader = getReader('ItemBonus');
    $colNames = $bonusReader->fetchColumnNames();
    $bonusReader->setFieldsSigned([
        array_search('Value', $colNames) => true,
    ]);

    $data = [];
    foreach ($bonusReader->generateRecords() as $id => $record) {
        $data[$id] = $record;
    }

    file_put_contents(OUTPATH . '/ItemBonus.json', json_encode($data));
})();
