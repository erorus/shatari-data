<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Erorus\DB2\Reader;
use Erorus\DB2\HotfixedReader;

$outPath = __DIR__ . '/../out';

define('CLASS_CONSUMABLE', 0);
define('CLASS_CONTAINER', 1);
define('CLASS_WEAPON', 2);
define('CLASS_GEM', 3);
define('CLASS_ARMOR', 4);
define('CLASS_ITEM_ENHANCEMENT', 8);
define('CLASS_RECIPE', 9);
define('CLASS_BATTLE_PET', 17);
define('CLASS_WOW_TOKEN', 18);

define('BOND_WHEN_PICKED_UP', 1);
define('BOND_QUEST', 4);
define('BOND_QUEST_2', 5);

define('FLAGS_0_CONJURED', 0x2);
define('FLAGS_1_HORDE', 0x1);
define('FLAGS_1_ALLIANCE', 0x2);

define('SIDE_HORDE', 2);
define('SIDE_ALLIANCE', 1);

define('FORBIDDEN_CLASSES', [
    CLASS_BATTLE_PET,
    CLASS_WOW_TOKEN,
]);

function getReader(string $db2Name) {
    $db2Path = __DIR__ . '/../DBFilesClient';

    $hotfixPath = "{$db2Path}/DBCache.bin";
    $hotfixPath = file_exists($hotfixPath) ? $hotfixPath : null;

    return $hotfixPath ?
        new HotfixedReader("{$db2Path}/{$db2Name}.db2", $hotfixPath) :
        new Reader("{$db2Path}/{$db2Name}.db2");
}

$appearanceToIcon = [];
echo "Opening Appearance reader...\n";
$itemAppearanceReader = getReader('ItemAppearance');
$itemAppearanceReader->fetchColumnNames();
foreach ($itemAppearanceReader->generateRecords() as $id => $rec) {
    if ($rec['DefaultIconFileDataID']) {
        $appearanceToIcon[$id] = $rec['DefaultIconFileDataID'];
    }
}
unset($itemAppearanceReader);
echo sprintf("Found %d appearance records with icons.\n", count($appearanceToIcon));

$itemIcons = [];
echo "Opening Appearance Mods reader...\n";
$itemModifiedAppearanceReader = getReader('ItemModifiedAppearance');
$itemModifiedAppearanceReader->fetchColumnNames();
foreach ($itemModifiedAppearanceReader->generateRecords() as $rec) {
    $itemId = $rec['ItemID'];
    $appearanceId = $rec['ItemAppearanceID'];
    if (!($appearanceToIcon[$appearanceId] ?? 0)) {
        continue;
    }
    if ($rec['OrderIndex'] === 0 || !isset($itemIcons[$itemId])) {
        $itemIcons[$itemId] = $appearanceToIcon[$appearanceId];
    }
}
unset($itemModifiedAppearanceReader, $appearanceToIcon);
echo sprintf("Found %d icon associations for items.\n", count($itemIcons));


$items = [];
$names = [];
echo "Opening Item reader...\n";
$itemReader = getReader('Item');
$itemReader->fetchColumnNames();

echo "Opening ItemSparse reader...\n";
$itemSparseReader = getReader('ItemSparse');
$itemSparseReader->fetchColumnNames();

echo "Opening File List reader...\n";
$fileListReader = getReader('ManifestInterfaceData');
$fileListReader->fetchColumnNames();
$getIcon = function (int $id) use ($fileListReader): string {
    static $cache = [];
    if (isset($cache[$id])) {
        return $cache[$id];
    }

    $rec = $fileListReader->getRecord($id);

    return $cache[$id] = preg_replace('/\.blp$/', '', strtolower(str_replace(' ', '-', $rec['FileName'] ?? '')));
};

$itemCount = count($itemReader->getIds());
echo "Starting on {$itemCount} items.\n";
$processed = 0;
$saved = 0;
$lastReport = time();
foreach ($itemReader->generateRecords() as $id => $itemRec) {
    if (time() >= $lastReport + 5) {
        $lastReport = time();
        echo sprintf("Processed %d of %d items.\n", $processed, $itemCount);
    }
    $processed++;

    if (in_array($itemRec['ClassID'], FORBIDDEN_CLASSES)) {
        continue;
    }

    $sparseRec = $itemSparseReader->getRecord($id);
    if (!$sparseRec) {
        continue;
    }

    if (in_array($sparseRec['Bonding'], [BOND_WHEN_PICKED_UP, BOND_QUEST, BOND_QUEST_2])) {
        continue;
    }

    if ($sparseRec['Flags'][0] & FLAGS_0_CONJURED) {
        continue;
    }

    if ($sparseRec['DurationInInventory'] > 0) {
        continue;
    }

    $saved++;
    $names[$id] = $sparseRec['Display_lang'];
    $items[$id] = [
        'class' => $itemRec['ClassID'],
        'subclass' => $itemRec['SubclassID'],
        'icon' => $getIcon($itemIcons[$id] ?? $itemRec['IconFileDataID']),
        'quality' => $sparseRec['OverallQualityID'],
        //'vendorBuy' => $sparseRec['BuyPrice'],
        'vendorSell' => $sparseRec['SellPrice'],
    ];
    if ($sparseRec['Flags'][1] & FLAGS_1_HORDE) {
        $items[$id]['side'] = SIDE_HORDE;
    }
    if ($sparseRec['Flags'][1] & FLAGS_1_ALLIANCE) {
        $items[$id]['side'] = SIDE_ALLIANCE;
    }
    // Crafted legendaries.
    if ($sparseRec['LimitCategory'] === 473) {
        $items[$id]['extraFilters'][] = 11;
    }
    switch ($itemRec['ClassID']) {
        case CLASS_CONTAINER:
            $items[$id]['slots'] = $sparseRec['ContainerSlots'];
            break;
        case CLASS_CONSUMABLE:
            $items[$id]['reqLevel'] = $sparseRec['RequiredLevel'];
            break;
        case CLASS_RECIPE:
            $items[$id]['skill'] = $sparseRec['RequiredSkillRank'];
            break;
        case CLASS_ARMOR:
            $items[$id]['inventoryType'] = $itemRec['InventoryType'];
            // no break
        case CLASS_WEAPON:
        case CLASS_GEM:
        case CLASS_ITEM_ENHANCEMENT:
            $items[$id]['itemLevel'] = $sparseRec['ItemLevel'];
            break;
    }
}
echo "Finished saving {$saved} items.\n";

file_put_contents("{$outPath}/items.json", json_encode($items, JSON_UNESCAPED_SLASHES));
file_put_contents("{$outPath}/names.enus.json", json_encode($names, JSON_UNESCAPED_SLASHES));
