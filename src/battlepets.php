<?php

require_once __DIR__ . '/incl.php';

$outPath = __DIR__ . '/../out';

define('DEFAULT_EXPANSION', 11);

define('FLAG_UNTRADEABLE',   0x010);
define('FLAG_UNTAMEABLE',    0x020);
define('FLAG_HORDE_ONLY',    0x100);
define('FLAG_ALLIANCE_ONLY', 0x200);

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

file_put_contents("{$outPath}/battlepets.json", json_encode($pets, OE_JSON_FLAGS));
file_put_contents("{$outPath}/battlepets.enus.json", json_encode($names, OE_JSON_FLAGS));

unset($speciesStateReader);

foreach (LOCALES_OTHER as $locale) {
    echo "Opening {$locale} Creatures...\n";
    $creatureReader = getReader('Creature', $locale);
    $creatureReader->fetchColumnNames();

    $localizedNames = [];
    foreach ($pets as $id => $pet) {
        $npc = $creatureReader->getRecord($pet['npc']);
        $localizedNames[$id] = $npc['Name_lang'] ?? $names[$id];
    }

    file_put_contents("{$outPath}/battlepets.{$locale}.json", json_encode($localizedNames, OE_JSON_FLAGS));
}

echo "Done.\n";
