<?php

require_once __DIR__ . '/incl.php';

$outPath = __DIR__ . '/../out';

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
$nameToBonus = [];
$excludeNameBonus = [];

echo "Scanning bonuses...\n";
foreach ($bonusReader->generateRecords() as $rec) {
    if (!isset($rec['ParentItemBonusListID'])) {
        print_r($rec);
        exit;
    }
    $bonusId = $rec['ParentItemBonusListID'];
    if ($rec['Type'] !== 5) {
        $excludeNameBonus[$bonusId] = $bonusId;
    }
    switch ($rec['Type']) {
        case 1: // Adjust item level
            $bonusLevels[$bonusId] = $rec['Value'][0];
            break;
        case 5: // Set name suffix
            list($nameId, $priority) = $rec['Value'];
            $seenNames[$nameId] = true;
            $bonusNames[$bonusId] = [$priority, $nameId];
            $nameToBonus[$nameId][] = $bonusId;
            break;
        case 13: // Scale item level
            list($oldDist, $priority, $contentTuningId, $curveId) = $rec['Value'];
            $seenCurves[$curveId] = true;
            $bonusCurves[$bonusId] = [$priority, $curveId];
            break;
    }
}
unset($bonusReader);

// Determine bonus IDs which onnly add a name and do nothing else.
foreach ($nameToBonus as $nameId => &$bonuses) {
    $validBonuses = array_values(array_diff($bonuses, $excludeNameBonus));
    if ($validBonuses) {
        $bonuses = $validBonuses[0];
    } else {
        $bonuses = false;
    }
};
unset($bonuses);
$nameToBonus = array_filter($nameToBonus);
unset($excludeNameBonus);

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
    $nameSuffixes[$nameId] = ['name' => $nameRow['Description_lang'], 'bonus' => $nameToBonus[$nameId] ?? null];
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
], OE_JSON_FLAGS));
file_put_contents("{$outPath}/name-suffixes.enus.json", json_encode($nameSuffixes, OE_JSON_FLAGS));
