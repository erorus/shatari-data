<?php

require_once __DIR__ . '/incl.php';

$outPath = __DIR__ . '/../out';

echo "Opening Bonus reader...\n";
$bonusReader = getReader('ItemBonus');
$colNames = $bonusReader->fetchColumnNames();
$bonusReader->setFieldsSigned([
    array_search('Value', $colNames) => true,
]);

echo "Opening Item Scaling Config reader...\n";
$itemScalingConfigReader = getReader('ItemScalingConfig');
$itemScalingConfigReader->fetchColumnNames();

echo "Opening Item Offset Curve reader...\n";
$itemOffsetCurveReader = getReader('ItemOffsetCurve');
$colNames = $itemOffsetCurveReader->fetchColumnNames();
$bonusReader->setFieldsSigned([
    array_search('Offset', $colNames) => true,
]);

echo "Opening Content Tuning reader...\n";
$contentTuningReader = getReader('ContentTuning');
$contentTuningReader->fetchColumnNames();

echo "Opening Curve reader...\n";
$curveReader = getReader('CurvePoint');
$colNames = $curveReader->fetchColumnNames();
$curveReader->setFieldsSigned([
    array_search('Pos', $colNames) => true,
]);
$curvePoints = [];
foreach ($curveReader->generateRecords() as $rec) {
    $curvePoints[$rec['CurveID']][$rec['OrderIndex']] = $rec['Pos'];
}
foreach ($curvePoints as &$points) {
    ksort($points, SORT_NUMERIC);
    $points = array_values($points);
}
unset($points);

$tertiaryStats = [STAT_SPEED_RATING, STAT_LEECH_RATING, STAT_AVOIDANCE_RATING, STAT_INDESTRUCTIBLE_RATING];

$seenNames = [];
$seenCurves = [];
$bonusNames = [];
$nameToBonus = [];
$excludeNameBonus = [];
$statBonuses = [];

$levelData = [
    'legacyAdjust'           => [], //  1
    'contentTuning'          => [], // 13
    'legacySet'              => [], // 42
    'eraCurveSet'            => [], // 48
    'itemScalingSet'         => [], // 49
    'itemScalingSetByPlayer' => [], // 51
    'eraAdjust'              => [], // 52
    'adjust'                 => [], // 53
];

echo "Getting squish eras...\n";
$squishEras = getSquishEras();
$currentEra = 0;
foreach ($squishEras as $era) {
    if ($era['target'] ?? false) {
        $currentEra = $era['id'];
        break;
    }
}

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
            $levelData['legacyAdjust'][$bonusId] = $rec['Value'][0];
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

        case 13: // Player level curve
            [$oldDist, $priority, $contentTuningId, $curveId] = $rec['Value'];
            if ($curveId !== 0) {
                $tuningRec = $contentTuningReader->getRecord($contentTuningId);
                $playerMax = $tuningRec['MaxLevelSquish'] ?? 0;
                $seenCurves[$curveId] = true;

                $levelData['contentTuning'][$bonusId] = [$priority, $curveId, $playerMax];
            }
            break;

        case 42: // Set item level
            [$level, $priority] = $rec['Value'];
            $levelData['legacySet'][$bonusId] = [$priority, $level];
            break;

        case 48: // Item level curve
            [$curveId, $level, $itemSquishEra] = $rec['Value'];
            $seenCurves[$curveId] = true;
            $levelData['eraCurveSet'][$bonusId] = [$curveId, $level, $itemSquishEra];
            break;

        case 49: // Item scaling config
        case 51: // Item scaling config by drop level
            $dict = $rec['Type'] === 49 ? 'itemScalingSet' : 'itemScalingSetByPlayer';
            [$scalingConfigId, $priority] = $rec['Value'];
            if ($configRec = $itemScalingConfigReader->getRecord($scalingConfigId)) {
                if ($itemOffsetCurveRec = $itemOffsetCurveReader->getRecord($configRec['ItemOffsetCurveID'])) {
                    if ($configRec['ItemLevel'] && $curve = $curvePoints[$itemOffsetCurveRec['CurveID']] ?? []) {
                        $level = applyCurve($configRec['ItemLevel'], $curve) + $itemOffsetCurveRec['Offset'];
                        $curve = 0;
                        $offset = 0;
                    } else {
                        $level = $configRec['ItemLevel'];
                        $curve = $itemOffsetCurveRec['CurveID'];
                        $offset = $itemOffsetCurveRec['Offset'];
                    }
                    $seenCurves[$curve] = true;
                    $era = ($configRec['Flags'] & 0x1) ? $currentEra : $configRec['ItemSquishEraID'];
                    $levelData[$dict][$bonusId] = [$priority, $level, $curve, $offset, $era];
                }
            }
            break;

        case 52: // Era adjust
            [$amount, $era, $fallbackAmount] = $rec['Value'];
            $levelData['eraAdjust'][$bonusId] = [$amount, $fallbackAmount, $era];
            break;

        case 53: // Adjust
            [$amount, $priority] = $rec['Value'];
            $levelData['adjust'][$bonusId] = [$priority, $amount];
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

$saveCurves = [];
foreach (array_keys($seenCurves) as $curveId) {
    if (isset($curvePoints[$curveId])) {
        $saveCurves[$curveId] = $curvePoints[$curveId];
    }
}

file_put_contents("{$outPath}/bonuses.json", json_encode([
    'levelData' => $levelData,
    'names' => $bonusNames,
    'curvePoints' => $saveCurves,
    'statBonuses' => $statBonuses,
    'squishEras' => $squishEras,
], OE_JSON_FLAGS));

$bonusToStats = [];
foreach ($statBonuses as $statId => $bonuses) {
    foreach ($bonuses as $bonus) {
        $bonusToStats[$bonus][] = $statId;
    }
}
file_put_contents("{$outPath}/bonusToStats.json", json_encode($bonusToStats, OE_JSON_FLAGS));
