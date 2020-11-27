<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Erorus\DB2\Reader;
use Erorus\DB2\HotfixedReader;

$outPath = __DIR__ . '/../out';

function getReader(string $db2Name) {
    $db2Path = __DIR__ . '/../DBFilesClient';

    $hotfixPath = "{$db2Path}/DBCache.bin";
    $hotfixPath = file_exists($hotfixPath) ? $hotfixPath : null;

    return $hotfixPath ?
        new HotfixedReader("{$db2Path}/{$db2Name}.db2", $hotfixPath) :
        new Reader("{$db2Path}/{$db2Name}.db2");
}

echo "Opening Bonus reader...\n";
$bonusReader = getReader('ItemBonus');
$colNames = $bonusReader->fetchColumnNames();
$bonusReader->setFieldsSigned([
    array_search('Value', $colNames) => true,
]);

$seenNames = [];
$seenCurves = [];
$bonusNames = [];
$bonusCurves = [];
$bonusLevels = [];

echo "Scanning bonuses...\n";
foreach ($bonusReader->generateRecords() as $rec) {
    if (!isset($rec['ParentItemBonusListID'])) {
        print_r($rec);
        exit;
    }
    $bonusId = $rec['ParentItemBonusListID'];
    switch ($rec['Type']) {
        case 1: // Adjust item level
            $bonusLevels[$bonusId] = $rec['Value'][0];
            break;
        case 5: // Set name suffix
            list($nameId, $priority) = $rec['Value'];
            $seenNames[$nameId] = true;
            $bonusNames[$bonusId] = [$priority, $nameId];
            break;
        case 13: // Scale item level
            list($oldDist, $priority, $contentTuningId, $curveId) = $rec['Value'];
            $seenCurves[$curveId] = true;
            $bonusCurves[$bonusId] = [$priority, $curveId];
            break;
    }
}
unset($bonusReader);

echo "Opening Name reader...\n";
$nameReader = getReader('ItemNameDescription');
$nameReader->fetchColumnNames();
$nameSuffixes = [];
foreach (array_keys($seenNames) as $nameId) {
    $nameRow = $nameReader->getRecord($nameId);
    if (!isset($nameRow)) {
        echo "Could not get name {$nameId}\n";
        continue;
    }
    $nameSuffixes[$nameId] = $nameRow['Description_lang'];
}
unset($nameReader, $seenNames);

echo "Opening Curve reader...\n";
$curveReader = getReader('CurvePoint');
$colNames = $curveReader->fetchColumnNames();
$curveReader->setFieldsSigned([
    array_search('Pos', $colNames) => true,
]);
$curvePoints = [];
foreach ($curveReader->generateRecords() as $rec) {
    if (!isset($seenCurves[$rec['CurveID']])) {
        continue;
    }
    $curvePoints[$rec['CurveID']][$rec['OrderIndex']] = $rec['Pos'];
}
foreach ($curvePoints as &$points) {
    ksort($points, SORT_NUMERIC);
}
unset($points);
unset($curveReader, $seenCurves);

file_put_contents("{$outPath}/bonuses.json", json_encode([
    'levels' => $bonusLevels,
    'curves' => $bonusCurves,
    'names' => $bonusNames,
    'curvePoints' => $curvePoints,
], JSON_UNESCAPED_SLASHES));
file_put_contents("{$outPath}/name-suffixes.enus.json", json_encode($nameSuffixes, JSON_UNESCAPED_SLASHES));
