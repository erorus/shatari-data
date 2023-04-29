<?php

require_once __DIR__ . '/incl.php';

$outPath = __DIR__ . '/../out';

echo "Opening Bonus reader...\n";
$bonusReader = getReader('ItemBonus');
$colNames = $bonusReader->fetchColumnNames();
$bonusReader->setFieldsSigned([
    array_search('Value', $colNames) => true,
]);

$tertiaryStats = [STAT_SPEED_RATING, STAT_LEECH_RATING, STAT_AVOIDANCE_RATING, STAT_INDESTRUCTIBLE_RATING];

$seenNames = [];
$seenCurves = [];
$bonusNames = [];
$bonusCurves = [];
$bonusLevels = [];
$bonusSetLevels = [];
$nameToBonus = [];
$excludeNameBonus = [];
$statBonuses = [];

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

        case 2: // Adjust stat
        case 25: // Crafting stat
            $statId = $rec['Value'][0];
            if (in_array($statId, $tertiaryStats, strict: true)) {
                $statBonuses[$statId][] = $bonusId;
            }
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
        case 42: // Set item level
            [$level, $priority] = $rec['Value'];
            $bonusSetLevels[$bonusId] = [$priority, $level];
            break;
    }
}
unset($bonusReader);

// Determine bonus IDs which only add a name and do nothing else.
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

foreach (LOCALES as $locale) {
    echo "Opening {$locale} Name reader...\n";
    $nameReader = getReader('ItemNameDescription', $locale);
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
    unset($nameReader);
    file_put_contents("{$outPath}/name-suffixes.{$locale}.json", json_encode($nameSuffixes, OE_JSON_FLAGS));
}
unset($seenNames, $nameSuffixes);

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
    'setLevels' => $bonusSetLevels,
    'curves' => $bonusCurves,
    'names' => $bonusNames,
    'curvePoints' => $curvePoints,
    'statBonuses' => $statBonuses,
], OE_JSON_FLAGS));
