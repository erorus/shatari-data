<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Erorus\DB2\Reader;
use Erorus\DB2\HotfixedReader;

define('LOCALES_OTHER', ['dede', 'eses', 'frfr', 'itit', 'kokr', 'ptbr', 'ruru', 'zhtw']);
define('OE_JSON_FLAGS', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

define('SIDE_HORDE', 2);
define('SIDE_ALLIANCE', 1);

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
define('INV_TYPE_ONE_HAND', 13);
define('INV_TYPE_SHIELD', 14);
define('INV_TYPE_RANGED', 15);
define('INV_TYPE_CLOAK', 16);
define('INV_TYPE_TWO_HAND', 17);
define('INV_TYPE_BAG', 18);
define('INV_TYPE_TABARD', 19);
define('INV_TYPE_ROBE', 20);
define('INV_TYPE_MAIN_HAND', 21);
define('INV_TYPE_OFF_HAND', 22);
define('INV_TYPE_HOLDABLE', 23);
define('INV_TYPE_PROJECTILE', 24);
define('INV_TYPE_THROWN', 25);
define('INV_TYPE_RANGED_RIGHT', 26);
define('INV_TYPE_QUIVER', 27);
define('INV_TYPE_RELIC', 28);

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
define('CLASS_BATTLE_PET', 17);
define('CLASS_WOW_TOKEN', 18);

define('SUBCLASS_ARMOR_GENERIC', 0);
define('SUBCLASS_ARMOR_CLOTH', 1);
define('SUBCLASS_ARMOR_LEATHER', 2);
define('SUBCLASS_ARMOR_MAIL', 3);
define('SUBCLASS_ARMOR_PLATE', 4);
define('SUBCLASS_ARMOR_COSMETIC', 5);
define('SUBCLASS_ARMOR_SHIELD', 6);

/**
 * @param string $db2Name
 * @param string $locale
 *
 * @return Reader
 */
function getReader(string $db2Name, string $locale = 'enus') {
    $locale = strtolower(substr($locale, 0, 2)) . strtoupper(substr($locale, 2, 2));
    $db2Path = __DIR__ . "/../current/{$locale}/DBFilesClient";

    $hotfixPath = "{$db2Path}/DBCache.bin";
    $hotfixPath = file_exists($hotfixPath) ? $hotfixPath : null;

    return $hotfixPath ?
        new HotfixedReader("{$db2Path}/{$db2Name}.db2", $hotfixPath) :
        new Reader("{$db2Path}/{$db2Name}.db2");
}
