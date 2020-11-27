<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Erorus\DB2\Reader;

$db2Path = __DIR__ . '/../DBFilesClient';
$outPath = __DIR__ . '/../out';

define('HIDE_SUBCLASS_AUCTION', 0x2);

define('CLASS_CONSUMABLE', 0);
define('CLASS_CONTAINER', 1);
define('CLASS_WEAPON', 2);
define('CLASS_GEM', 3);
define('CLASS_ARMOR', 4);
define('CLASS_TRADEGOODS', 7);
define('CLASS_ITEM_ENHANCEMENT', 8);
define('CLASS_RECIPE', 9);
define('CLASS_QUESTITEM', 12);
define('CLASS_MISCELLANEOUS', 15);
define('CLASS_GLYPH', 16);

define('SUBCLASS_WEAPON_AXE1H', 0);
define('SUBCLASS_WEAPON_AXE2H', 1);
define('SUBCLASS_WEAPON_BOWS', 2);
define('SUBCLASS_WEAPON_GUNS', 3);
define('SUBCLASS_WEAPON_MACE1H', 4);
define('SUBCLASS_WEAPON_MACE2H', 5);
define('SUBCLASS_WEAPON_POLEARM', 6);
define('SUBCLASS_WEAPON_SWORD1H', 7);
define('SUBCLASS_WEAPON_SWORD2H', 8);
define('SUBCLASS_WEAPON_WARGLAIVE', 9);
define('SUBCLASS_WEAPON_STAFF', 10);
define('SUBCLASS_WEAPON_UNARMED', 13);
define('SUBCLASS_WEAPON_GENERIC', 14);
define('SUBCLASS_WEAPON_DAGGER', 15);
define('SUBCLASS_WEAPON_THROWN', 16);
define('SUBCLASS_WEAPON_CROSSBOW', 18);
define('SUBCLASS_WEAPON_WAND', 19);
define('SUBCLASS_WEAPON_FISHINGPOLE', 20);

define('SUBCLASS_ARMOR_GENERIC', 0);
define('SUBCLASS_ARMOR_CLOTH', 1);
define('SUBCLASS_ARMOR_LEATHER', 2);
define('SUBCLASS_ARMOR_MAIL', 3);
define('SUBCLASS_ARMOR_PLATE', 4);
define('SUBCLASS_ARMOR_COSMETIC', 5);
define('SUBCLASS_ARMOR_SHIELD', 6);

define('SUBCLASS_RECIPE_BOOK', 0);
define('SUBCLASS_MISCELLANEOUS_JUNK', 0);
define('SUBCLASS_MISCELLANEOUS_REAGENT', 1);
define('SUBCLASS_MISCELLANEOUS_HOLIDAY', 3);
define('SUBCLASS_MISCELLANEOUS_OTHER', 4);
define('SUBCLASS_MISCELLANEOUS_MOUNT', 5);
define('SUBCLASS_MISCELLANEOUS_MOUNT_EQUIPMENT', 6);

define('INV_TYPE_HEAD', 1);
define('INV_TYPE_NECK', 2);
define('INV_TYPE_SHOULDERS', 3);
define('INV_TYPE_BODY', 4);
define('INV_TYPE_CHEST', 5);
define('INV_TYPE_WAIST', 6);
define('INV_TYPE_LEGS', 7);
define('INV_TYPE_FEET', 8);
define('INV_TYPE_WRISTS', 9);
define('INV_TYPE_HANDS', 10);
define('INV_TYPE_FINGER', 11);
define('INV_TYPE_TRINKET', 12);
define('INV_TYPE_SHIELD', 14);
define('INV_TYPE_CLOAK', 16);
define('INV_TYPE_ROBE', 20);
define('INV_TYPE_HOLDABLE', 23);

define('INV_TYPE_NAMES', [
    INV_TYPE_HEAD => 'INVTYPE_HEAD',
    INV_TYPE_NECK => 'INVTYPE_NECK',
    INV_TYPE_SHOULDERS => 'INVTYPE_SHOULDER',
    INV_TYPE_BODY => 'INVTYPE_BODY',
    INV_TYPE_CHEST => 'INVTYPE_CHEST',
    INV_TYPE_WAIST => 'INVTYPE_WAIST',
    INV_TYPE_LEGS => 'INVTYPE_LEGS',
    INV_TYPE_FEET => 'INVTYPE_FEET',
    INV_TYPE_WRISTS => 'INVTYPE_WRIST',
    INV_TYPE_HANDS => 'INVTYPE_HAND',
    INV_TYPE_FINGER => 'INVTYPE_FINGER',
    INV_TYPE_TRINKET => 'INVTYPE_TRINKET',
    INV_TYPE_SHIELD => 'INVTYPE_SHIELD',
    INV_TYPE_HOLDABLE => 'INVTYPE_HOLDABLE',
]);

$result = [];

echo "Opening Global Strings reader...\n";
$globalStringsReader = new Reader("{$db2Path}/GlobalStrings.db2");
$globalStringsReader->fetchColumnNames();
$globalStrings = [];
foreach ($globalStringsReader->generateRecords() as $rec) {
    $globalStrings[$rec['BaseTag']] = $rec['TagText_lang'];
}
unset($globalStringsReader);

echo "Opening subclass reader...\n";
$subClassReader = new Reader("{$db2Path}/ItemSubClass.db2");
$subClassReader->fetchColumnNames();
$getSubclassCategories = function (int $classId) use ($subClassReader): array {
    // Some weirdness in the lua.
    $sortOrderOverride = [CLASS_RECIPE => [SUBCLASS_RECIPE_BOOK => 100]];

    $result = [];
    foreach ($subClassReader->generateRecords() as $rec) {
        if ($rec['ClassID'] === $classId && ($rec['DisplayFlags'] & HIDE_SUBCLASS_AUCTION) === 0) {
            $subCat = [
                'name' => $rec['VerboseName_lang'] ?: $rec['DisplayName_lang'],
                'class' => $classId,
                'subClass' => $rec['SubClassID'],
                'id' => $rec['ID'],
                'sortOrder' => $sortOrderOverride[$rec['ClassID']][$rec['SubClassID']] ?? $rec['AuctionHouseSortOrder'],
            ];

            $result[] = $subCat;
        }
    }
    usort($result, function (array $a, array $b): int {
        return ($a['sortOrder'] <=> $b['sortOrder']) ?: ($a['id'] <=> $b['id']);
    });
    foreach ($result as &$subCat) {
        unset($subCat['id'], $subCat['sortOrder']);
    }
    unset($subCat);

    return $result;
};
$makeSubclassCategory = function (int $classId, int $subclassId) use ($subClassReader): array {
    foreach ($subClassReader->generateRecords() as $rec) {
        if ($rec['ClassID'] === $classId && $rec['SubClassID'] === $subclassId) {
            return [
                'name' => $rec['VerboseName_lang'] ?: $rec['DisplayName_lang'],
                'class' => $classId,
                'subClass' => $rec['SubClassID'],
            ];
        }
    }

    echo "Could not find subclass category {$classId}x{$subclassId}!\n";

    return [];
};

// Weapons
$weaponsCategory = [
    'name' => $globalStrings['AUCTION_CATEGORY_WEAPONS'],
    'detailColumn' => [
        'prop' => 'itemLevel',
        'name' => $globalStrings['ITEM_LEVEL_ABBR'],
    ],
    'class' => CLASS_WEAPON,
    'subcategories' => [
        [
            'name' => $globalStrings['AUCTION_SUBCATEGORY_ONE_HANDED'],
            'class' => CLASS_WEAPON,
            'subClasses' => [
                SUBCLASS_WEAPON_AXE1H,
                SUBCLASS_WEAPON_MACE1H,
                SUBCLASS_WEAPON_SWORD1H,
                SUBCLASS_WEAPON_WARGLAIVE,
                SUBCLASS_WEAPON_DAGGER,
                SUBCLASS_WEAPON_UNARMED,
                SUBCLASS_WEAPON_WAND,
            ],
        ],
        [
            'name' => $globalStrings['AUCTION_SUBCATEGORY_TWO_HANDED'],
            'class' => CLASS_WEAPON,
            'subClasses' => [
                SUBCLASS_WEAPON_AXE2H,
                SUBCLASS_WEAPON_MACE2H,
                SUBCLASS_WEAPON_SWORD2H,
                SUBCLASS_WEAPON_POLEARM,
                SUBCLASS_WEAPON_STAFF,
            ],
        ],
        [
            'name' => $globalStrings['AUCTION_SUBCATEGORY_RANGED'],
            'class' => CLASS_WEAPON,
            'subClasses' => [
                SUBCLASS_WEAPON_BOWS,
                SUBCLASS_WEAPON_CROSSBOW,
                SUBCLASS_WEAPON_GUNS,
                SUBCLASS_WEAPON_THROWN,
            ],
        ],
        [
            'name' => $globalStrings['AUCTION_SUBCATEGORY_MISCELLANEOUS'],
            'class' => CLASS_WEAPON,
            'subClasses' => [
                SUBCLASS_WEAPON_FISHINGPOLE,
                SUBCLASS_WEAPON_GENERIC,
            ],
        ],
    ],
];

// Make subsubcategories for weapons.
foreach ($weaponsCategory['subcategories'] as &$subcat) {
    foreach ($subcat['subClasses'] as $subClass) {
        $subSubCat = $makeSubclassCategory($subcat['class'], $subClass);
        if ($subClass === SUBCLASS_WEAPON_GENERIC) {
            $subSubCat['name'] = $globalStrings['AUCTION_SUBCATEGORY_OTHER'];
        }
        $subcat['subcategories'][] = $subSubCat;
    }
}
unset($subcat);

$result[] = $weaponsCategory;

$armorCategory = [
    'name' => $globalStrings['AUCTION_CATEGORY_ARMOR'],
    'detailColumn' => [
        'prop' => 'itemLevel',
        'name' => $globalStrings['ITEM_LEVEL_ABBR'],
    ],
    'class' => CLASS_ARMOR,
    'subcategories' => [],
];
$inventoryTypes = [
    [INV_TYPE_HEAD],
    [INV_TYPE_SHOULDERS],
    [INV_TYPE_CHEST, INV_TYPE_ROBE],
    [INV_TYPE_WAIST],
    [INV_TYPE_LEGS],
    [INV_TYPE_FEET],
    [INV_TYPE_WRISTS],
    [INV_TYPE_HANDS],
];
$armorSubclasses = [
    SUBCLASS_ARMOR_PLATE,
    SUBCLASS_ARMOR_MAIL,
    SUBCLASS_ARMOR_LEATHER,
    SUBCLASS_ARMOR_CLOTH,
];
$makeInvTypeSubCategory = function (int $subclass, array $invTypes) use ($globalStrings): array {
    return [
        'name' => $globalStrings[INV_TYPE_NAMES[$invTypes[0]]],
        'class' => CLASS_ARMOR,
        'subClass' => $subclass,
        'invTypes' => $invTypes,
    ];
};

foreach ($armorSubclasses as $armorSubclass) {
    $subcat = $makeSubclassCategory(CLASS_ARMOR, $armorSubclass);
    $subcat['subcategories'][] = [
        'name' => $globalStrings['AUCTION_HOUSE_FILTER_RUNECARVING'],
        'class' => CLASS_ARMOR,
        'subClass' => $armorSubclass,
        'extraFilters' => [11],
    ];
    foreach ($inventoryTypes as $inventoryTypeSet) {
        $subcat['subcategories'][] = $makeInvTypeSubCategory($armorSubclass, $inventoryTypeSet);
    }
    $armorCategory['subcategories'][] = $subcat;
}
$subcat = $makeSubclassCategory(CLASS_ARMOR, SUBCLASS_ARMOR_GENERIC);
$subcat['subcategories'][] = [
    'name' => $globalStrings['AUCTION_HOUSE_FILTER_RUNECARVING'],
    'class' => CLASS_ARMOR,
    'subClass' => SUBCLASS_ARMOR_GENERIC,
    'extraFilters' => [11],
];
$subcat['subcategories'][] = $makeInvTypeSubCategory(SUBCLASS_ARMOR_GENERIC, [INV_TYPE_NECK]);
$subcat['subcategories'][] = [
    'name' => $globalStrings['AUCTION_SUBCATEGORY_CLOAK'],
    'class' => CLASS_ARMOR,
    'subClass' => SUBCLASS_ARMOR_CLOTH,
    'invTypes' => [INV_TYPE_CLOAK],
];
$subcat['subcategories'][] = $makeInvTypeSubCategory(SUBCLASS_ARMOR_GENERIC, [INV_TYPE_FINGER]);
$subcat['subcategories'][] = $makeInvTypeSubCategory(SUBCLASS_ARMOR_GENERIC, [INV_TYPE_TRINKET]);
$subcat['subcategories'][] = $makeInvTypeSubCategory(SUBCLASS_ARMOR_GENERIC, [INV_TYPE_HOLDABLE]);
$subcat['subcategories'][] = $makeSubclassCategory(CLASS_ARMOR, SUBCLASS_ARMOR_SHIELD);
$subcat['subcategories'][] = $makeInvTypeSubCategory(SUBCLASS_ARMOR_GENERIC, [INV_TYPE_BODY]);
$subcat['subcategories'][] = $makeInvTypeSubCategory(SUBCLASS_ARMOR_GENERIC, [INV_TYPE_HEAD]);
$armorCategory['subcategories'][] = $subcat;

$armorCategory['subcategories'][] = $makeSubclassCategory(CLASS_ARMOR, SUBCLASS_ARMOR_COSMETIC);

$result[] = $armorCategory;

// Containers
$result[] = [
    'name' => $globalStrings['AUCTION_CATEGORY_CONTAINERS'],
    'detailColumn' => [
        'prop' => 'slots',
        'name' => $globalStrings['AUCTION_HOUSE_BROWSE_HEADER_CONTAINER_SLOTS'],
    ],
    'class' => CLASS_CONTAINER,
    'subcategories' => $getSubclassCategories(CLASS_CONTAINER),
];

// Gems
$result[] = [
    'name' => $globalStrings['AUCTION_CATEGORY_GEMS'],
    'detailColumn' => [
        'prop' => 'itemLevel',
        'name' => $globalStrings['ITEM_LEVEL_ABBR'],
    ],
    'class' => CLASS_GEM,
    'subcategories' => $getSubclassCategories(CLASS_GEM),
];

// Item Enhancement
$result[] = [
    'name' => $globalStrings['AUCTION_CATEGORY_ITEM_ENHANCEMENT'],
    'detailColumn' => [
        'prop' => 'itemLevel',
        'name' => $globalStrings['ITEM_LEVEL_ABBR'],
    ],
    'class' => CLASS_ITEM_ENHANCEMENT,
    'subcategories' => $getSubclassCategories(CLASS_ITEM_ENHANCEMENT),
];

// Consumables
$result[] = [
    'name' => $globalStrings['AUCTION_CATEGORY_CONSUMABLES'],
    'detailColumn' => [
        'prop' => 'reqLevel',
        'name' => $globalStrings['AUCTION_HOUSE_BROWSE_HEADER_REQUIRED_LEVEL'],
    ],
    'class' => CLASS_CONSUMABLE,
    'subcategories' => $getSubclassCategories(CLASS_CONSUMABLE),
];

// Glyphs
$result[] = [
    'name' => $globalStrings['AUCTION_CATEGORY_GLYPHS'],
    'class' => CLASS_GLYPH,
    'subcategories' => $getSubclassCategories(CLASS_GLYPH),
];

// Trade Goods
$result[] = [
    'name' => $globalStrings['AUCTION_CATEGORY_TRADE_GOODS'],
    'class' => CLASS_TRADEGOODS,
    'subcategories' => $getSubclassCategories(CLASS_TRADEGOODS),
];

// Recipes
$result[] = [
    'name' => $globalStrings['AUCTION_CATEGORY_RECIPES'],
    'detailColumn' => [
        'prop' => 'skill',
        'name' => $globalStrings['AUCTION_HOUSE_BROWSE_HEADER_RECIPE_SKILL'],
    ],
    'class' => CLASS_RECIPE,
    'subcategories' => $getSubclassCategories(CLASS_RECIPE),
];

// Quest Items
$result[] = [
    'name' => $globalStrings['AUCTION_CATEGORY_QUEST_ITEMS'],
    'class' => CLASS_QUESTITEM,
    'subcategories' => $getSubclassCategories(CLASS_QUESTITEM),
];

// Miscellaneous
$result[] = [
    'name' => $globalStrings['AUCTION_CATEGORY_MISCELLANEOUS'],
    'class' => CLASS_MISCELLANEOUS,
    'subcategories' => [
        $makeSubclassCategory(CLASS_MISCELLANEOUS, SUBCLASS_MISCELLANEOUS_JUNK),
        $makeSubclassCategory(CLASS_MISCELLANEOUS, SUBCLASS_MISCELLANEOUS_REAGENT),
        $makeSubclassCategory(CLASS_MISCELLANEOUS, SUBCLASS_MISCELLANEOUS_HOLIDAY),
        $makeSubclassCategory(CLASS_MISCELLANEOUS, SUBCLASS_MISCELLANEOUS_OTHER),
        $makeSubclassCategory(CLASS_MISCELLANEOUS, SUBCLASS_MISCELLANEOUS_MOUNT),
        $makeSubclassCategory(CLASS_MISCELLANEOUS, SUBCLASS_MISCELLANEOUS_MOUNT_EQUIPMENT),
    ],
];

foreach ($result as &$cat) {
    if (count($cat['subcategories'] ?? []) < 2) {
        unset($cat['subcategories']);
    }
}
unset($cat);

file_put_contents("{$outPath}/categories.enus.json", json_encode($result, JSON_UNESCAPED_SLASHES));
