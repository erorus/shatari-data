<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Erorus\DB2\Reader;
use Erorus\DB2\HotfixedReader;

$outPath = __DIR__ . '/../out';

define('DEFAULT_EXPANSION', 9);

define('FLAG_UNTRADEABLE',   0x010);
define('FLAG_UNTAMEABLE',    0x020);
define('FLAG_HORDE_ONLY',    0x100);
define('FLAG_ALLIANCE_ONLY', 0x200);

define('SIDE_HORDE', 2);
define('SIDE_ALLIANCE', 1);

function getReader(string $db2Name) {
    $db2Path = __DIR__ . '/../DBFilesClient';

    $hotfixPath = "{$db2Path}/DBCache.bin";
    $hotfixPath = file_exists($hotfixPath) ? $hotfixPath : null;

    return $hotfixPath ?
        new HotfixedReader("{$db2Path}/{$db2Name}.db2", $hotfixPath) :
        new Reader("{$db2Path}/{$db2Name}.db2");
}

echo "Opening Creature reader...\n";
$creatureReader = getReader('Creature');
$creatureReader->fetchColumnNames();

echo "Opening Species reader...\n";
$speciesReader = getReader('BattlePetSpecies');
$speciesReader->fetchColumnNames();

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

$petExpansions = json_decode(file_get_contents(__DIR__ . '/../expansion-pets.json'), true);

echo "Reading species...\n";

$pets = [];
$names = [];
$badFlags = FLAG_UNTRADEABLE | FLAG_UNTAMEABLE;
foreach ($speciesReader->generateRecords() as $id => $rec) {
    if ($rec['Flags'] & $badFlags) {
        continue;
    }

    $npc = $creatureReader->getRecord($rec['CreatureID']);
    if (is_null($npc)) {
        echo "Could not find creature {$rec['CreatureID']}\n";
        continue;
    }

    $pet = [
        'npc' => $rec['CreatureID'],
        'icon' => $getIcon($rec['IconFileDataID']),
        'type' => $rec['PetTypeEnum'] + 1,
        'display' => $npc['DisplayID'][0],
        'expansion' => $petExpansions[$id] ?? DEFAULT_EXPANSION,
        'power' => 8,
        'stamina' => 8,
        'speed' => 8,
    ];
    if ($rec['Flags'] & FLAG_HORDE_ONLY) {
        $pet['side'] = SIDE_HORDE;
    }
    if ($rec['Flags'] & FLAG_ALLIANCE_ONLY) {
        $pet['side'] = SIDE_ALLIANCE;
    }

    $pets[$id] = $pet;
    $names[$id] = $npc['Name_lang'];
}

unset($creatureReader, $fileListReader, $speciesReader);

echo "Opening Species State reader...\n";
$speciesStateReader = getReader('BattlePetSpeciesState');
$speciesStateReader->fetchColumnNames();

foreach ($speciesStateReader->generateRecords() as $rec) {
    if (!isset($pets[$rec['BattlePetSpeciesID']])) {
        continue;
    }

    switch ($rec['BattlePetStateID']) {
        case 18:
            $pets[$rec['BattlePetSpeciesID']]['power'] = 8 + $rec['Value'] * 0.005;
            break;
        case 19:
            $pets[$rec['BattlePetSpeciesID']]['stamina'] = 8 + $rec['Value'] * 0.005;
            break;
        case 20:
            $pets[$rec['BattlePetSpeciesID']]['speed'] = 8 + $rec['Value'] * 0.005;
            break;
    }
}

file_put_contents("{$outPath}/battlepets.json", json_encode($pets, JSON_UNESCAPED_SLASHES));
file_put_contents("{$outPath}/battlepets.enus.json", json_encode($names, JSON_UNESCAPED_SLASHES));
