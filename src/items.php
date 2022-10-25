<?php

require_once __DIR__ . '/incl.php';

$outPath = __DIR__ . '/../out';

define('BOND_WHEN_PICKED_UP', 1);
define('BOND_QUEST', 4);
define('BOND_QUEST_2', 5);

define('DEFAULT_EXPANSION', 10);

define('FLAGS_0_CONJURED', 0x2);
define('FLAGS_1_HORDE', 0x1);
define('FLAGS_1_ALLIANCE', 0x2);
define('FLAGS_1_OVERRIDE_GOLD_COST', 0x4000);

define('SUBCLASS_GEM_RELIC', 11);

define('FORBIDDEN_CLASSES', [
    CLASS_WOW_TOKEN,
]);

define('EXCLUDED_VENDOR_NPCS', [111838, 123124]);

echo "Opening import price readers...\n";
$itemClassReader = getReader('ItemClass');
$itemClassReader->fetchColumnNames();
$classPriceMods = [];
foreach ($itemClassReader->generateRecords() as $rec) {
    $classMod = $rec['PriceModifier'];
    if (!is_float($classMod)) {
        // Work around db2 reader type detection bug.
        switch ($classMod) {
            case 8388608:
                $classMod = 0.25;
                break;
            case 5033165:
                $classMod = 0.20;
                break;
            default:
                throw new Exception("Unknown class mod: {$classMod}");
        }
    }
    $classPriceMods[$rec['ClassID']] = $classMod;
}
unset($itemClassReader);
$priceArmorReader = getReader('ImportPriceArmor');
$priceArmorReader->fetchColumnNames();
$priceShieldReader = getReader('ImportPriceShield');
$priceShieldReader->fetchColumnNames();
$priceWeaponReader = getReader('ImportPriceWeapon');
$priceWeaponReader->fetchColumnNames();

$appearanceToIcon = [];
$appearanceToDisplay = [];
echo "Opening Appearance reader...\n";
$itemAppearanceReader = getReader('ItemAppearance');
$itemAppearanceReader->fetchColumnNames();
foreach ($itemAppearanceReader->generateRecords() as $id => $rec) {
    if ($rec['DefaultIconFileDataID']) {
        $appearanceToIcon[$id] = $rec['DefaultIconFileDataID'];
    }
    if ($rec['ItemDisplayInfoID']) {
        $appearanceToDisplay[$id] = $rec['ItemDisplayInfoID'];
    }
}
unset($itemAppearanceReader);
echo sprintf("Found %d appearance records with icons.\n", count($appearanceToIcon));
echo sprintf("Found %d appearance records with displays.\n", count($appearanceToDisplay));

$itemIcons = [];
$itemDisplays = [];
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
        $itemIcons[$itemId] = $appearanceToIcon[$appearanceId] ?? $itemIcons[$itemId] ?? null;
    }
    if ($rec['OrderIndex'] === 0 || !isset($itemDisplays[$itemId])) {
        $itemDisplays[$itemId] = $appearanceToDisplay[$appearanceId] ?? $itemDisplays[$itemId] ?? null;
    }
}
unset($itemModifiedAppearanceReader, $appearanceToIcon, $appearanceToDisplay);
echo sprintf("Found %d icon associations, %d displays for items.\n", count($itemIcons), count($itemDisplays));

$itemExpansions = json_decode(file_get_contents(__DIR__ . '/../expansion-items.json'), true);
$vendorItems = json_decode(file_get_contents(__DIR__ . '/../vendor-items.json'), true);

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
        'expansion' => $itemExpansions[$id] ?? DEFAULT_EXPANSION,
    ];
    if ($sparseRec['Stackable'] > 1) {
        $items[$id]['stack'] = $sparseRec['Stackable'];
    }
    if (isset($vendorItems[$id]['price']) && !in_array($vendorItems[$id]['npc'] ?? 0, EXCLUDED_VENDOR_NPCS)) {
        $items[$id]['vendorBuy'] = $vendorItems[$id]['price'];
    }
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
            if (isset($itemDisplays[$id])) {
                $items[$id]['display'] = $itemDisplays[$id];
            }
            // no break
        case CLASS_GEM:
        case CLASS_ITEM_ENHANCEMENT:
        case CLASS_PROFESSION:
            $items[$id]['itemLevel'] = $sparseRec['ItemLevel'];
            break;
    }

    // Pricing
    $priceBaseType = null;
    $invType = $itemRec['InventoryType'];
    switch ($itemRec['ClassID']) {
        case CLASS_ARMOR:
            $priceBaseType = CLASS_ARMOR;
            break;
        case CLASS_WEAPON:
            $priceBaseType = CLASS_WEAPON;
            break;
        case CLASS_GEM:
            if ($itemRec['SubclassID'] === SUBCLASS_GEM_RELIC) {
                $priceBaseType = CLASS_WEAPON;
                $invType = INV_TYPE_ONE_HAND;
            }
            break;
    }
    if ($sparseRec['Flags'][1] & FLAGS_1_OVERRIDE_GOLD_COST) {
        $priceBaseType = null;
    }
    if (!is_null($priceBaseType)) {
        $priceMod = 0;
        $weaponType = null;
        switch ($invType) {
            case INV_TYPE_HEAD:
            case INV_TYPE_NECK:
            case INV_TYPE_SHOULDERS:
            case INV_TYPE_CHEST:
            case INV_TYPE_WAIST:
            case INV_TYPE_LEGS:
            case INV_TYPE_FEET:
            case INV_TYPE_WRISTS:
            case INV_TYPE_HANDS:
            case INV_TYPE_FINGER:
            case INV_TYPE_TRINKET:
            case INV_TYPE_CLOAK:
            case INV_TYPE_ROBE:
            case INV_TYPE_HOLDABLE:
                $row = $priceArmorReader->getRecord($invType) ?? [];
                switch ($itemRec['SubclassID']) {
                    case SUBCLASS_ARMOR_PLATE:
                        $priceMod = $row['PlateModifier'] ?? 0;
                        break;
                    case SUBCLASS_ARMOR_MAIL:
                        $priceMod = $row['ChainModifier'] ?? 0;
                        break;
                    case SUBCLASS_ARMOR_LEATHER:
                        $priceMod = $row['LeatherModifier'] ?? 0;
                        break;
                    case SUBCLASS_ARMOR_CLOTH:
                    default:
                        $priceMod = $row['ClothModifier'] ?? 0;
                        break;
                }
                break;
            case INV_TYPE_MAIN_HAND:
                $weaponType = 1;
                break;
            case INV_TYPE_OFF_HAND:
                $weaponType = 2;
                break;
            case INV_TYPE_ONE_HAND:
                $weaponType = 3;
                break;
            case INV_TYPE_TWO_HAND:
                $weaponType = 4;
                break;
            case INV_TYPE_RANGED:
            case INV_TYPE_RANGED_RIGHT:
            case INV_TYPE_RELIC:
                $weaponType = 5;
                break;
            case INV_TYPE_SHIELD:
                $priceMod = ($priceShieldReader->getRecord(2) ?? [])['Data'] ?? 0;
                break;
        }
        if (!is_null($weaponType)) {
            $priceMod = ($priceWeaponReader->getRecord($weaponType) ?? [])['Data'] ?? 0;
        }

        $classMod = $classPriceMods[$itemRec['ClassID']] ?? 0;
        if ($itemRec['ClassID'] === CLASS_GEM) {
            // Artifact relic adjustment.
            $classMod *= 0.333333;
        }

        $fudge = 0.99999875;

        unset($items[$id]['vendorSell']);
        $items[$id]['vendorSellFactor'] = array_product([
            $priceMod,
            $classMod,
            $sparseRec['PriceVariance'],
            $sparseRec['PriceRandomValue'],
            $fudge,
        ]);
        if ($priceBaseType !== $items[$id]['class']) {
            $items[$id]['vendorSellBase'] = $priceBaseType;
        }
    }
}
echo "Finished saving {$saved} items.\n";

file_put_contents("{$outPath}/items.json", json_encode($items, OE_JSON_FLAGS));
file_put_contents("{$outPath}/names.enus.json", json_encode($names, OE_JSON_FLAGS));

unset($items, $fileListReader, $itemSparseReader, $itemReader, $priceArmorReader, $priceShieldReader, $priceWeaponReader);

foreach (LOCALES_OTHER as $locale) {
    echo "Opening {$locale} ItemSparse reader...\n";
    $itemSparseReader = getReader('ItemSparse', $locale);
    $itemSparseReader->fetchColumnNames();

    $localizedNames = [];
    foreach ($names as $id => $origName) {
        $sparseRec = $itemSparseReader->getRecord($id);
        $localizedNames[$id] = $sparseRec['Display_lang'] ?? $origName;
    }

    file_put_contents("{$outPath}/names.{$locale}.json", json_encode($localizedNames, OE_JSON_FLAGS));
}
unset($names, $localizedNames, $itemSparseReader);

$vendorSellData = [
    'quality' => [],
    CLASS_ARMOR => [
        0 => 0,
    ],
    CLASS_WEAPON => [
        0 => 0,
    ],
];
echo "Opening client-side price readers...\n";
$priceQualityReader = getReader('ImportPriceQuality');
$priceQualityReader->fetchColumnNames();
foreach ($priceQualityReader->generateRecords() as $id => $rec) {
    $vendorSellData['quality'][$id - 1] = $rec['Data'];
}
unset($priceQualityReader);

$priceBaseReader = getReader('ItemPriceBase');
$priceBaseReader->fetchColumnNames();
foreach ($priceBaseReader->generateRecords() as $rec) {
    $vendorSellData[CLASS_ARMOR][$rec['ItemLevel']] = $rec['Armor'];
    $vendorSellData[CLASS_WEAPON][$rec['ItemLevel']] = $rec['Weapon'];
}
unset($priceBaseReader);
foreach ($vendorSellData as $k => &$values) {
    ksort($values);
}
unset($values);

file_put_contents("{$outPath}/vendor.json", json_encode($vendorSellData, OE_JSON_FLAGS));
