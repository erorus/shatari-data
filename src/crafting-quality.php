<?php

require_once __DIR__ . '/incl.php';

$outPath = __DIR__ . '/../out';

echo "Opening CraftingQualityAtlasSet reader...\n";
$atlasSetReader = getReader('CraftingQualityAtlasSet');
$atlasSetReader->fetchColumnNames();
echo "Opening UiTextureAtlasElement reader...\n";
$uiElementReader = getReader('UiTextureAtlasElement');
$uiElementReader->fetchColumnNames();
echo "Opening CraftingQuality reader...\n";
$craftingQualityReader = getReader('CraftingQuality');
$craftingQualityReader->fetchColumnNames();

$craftingQualities = [];
foreach ($craftingQualityReader->generateRecords() as $id => $row) {
    $iconName = null;

    $atlasSetId = $row['CraftingQualityAtlasSetID'];
    $atlasSet = $atlasSetReader->getRecord($atlasSetId);
    if ($atlasSet) {
        $iconElementId = $atlasSet['IconChat'];
        $element = $uiElementReader->getRecord($iconElementId);
        if ($element) {
            $iconName = $element['Name'];
        }
    }

    $craftingQualities[$id] = [
        'tier' => $row['QualityTier'],
        'icon' => $iconName,
    ];
}

file_put_contents("{$outPath}/craftingQualities.json", json_encode($craftingQualities, OE_JSON_FLAGS));
