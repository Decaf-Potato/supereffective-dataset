<?php

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

//
// Splits the big pokemon.json file into smaller files, and generates other kinds of lists, and other tasks.
//
// Do not edit manually the .min.json or .build.json files, use the build.php script instead.
//

(static function () {
    error_reporting(-1);

    $dataSet = sgg_get_merged_pkm_entries();
    $dataSetById = [];
    foreach ($dataSet as $data) {
        $dataSetById[$data['id']] = $data;
    }

    $saveMergedPokemonEntries = static function () use ($dataSet, $dataSetById) {
        sgg_data_save(SGG_PKM_ENTRIES_BASE_FILENAME . '.json', $dataSet, minify: false);
        sgg_data_save(SGG_PKM_ENTRIES_BASE_FILENAME . '-map.json', $dataSetById, minify: false);
    };

    $generatePokemonEntriesMinimal = static function () use ($dataSet) {
        $minimalDataSet = [];
        foreach ($dataSet as $pkm) {
            $minimalDataSet[] = [
                'id' => $pkm['id'],
                'dexNum' => $pkm['dexNum'],
                'name' => $pkm['name'],
                'type1' => $pkm['type1'],
                'type2' => $pkm['type2'],
                'isForm' => $pkm['isForm'],
                'baseSpecies' => $pkm['baseSpecies'],
                'baseForms' => $pkm['baseForms'],
            ];
        }
        sgg_data_save(SGG_PKM_ENTRIES_BASE_FILENAME . '-minimal.json', $minimalDataSet, minify: false);
    };

    $generateStorablePokemonList = static function () use ($dataSet): void {
        $storableByGame = [];

        foreach (SGG_SUPPORTED_GAMES as $game) {
            foreach ($dataSet as $pkm) {
                if (in_array($game, $pkm['storableIn'], true)) {
                    $storableByGame[$game][] = $pkm['id'];
                }
            }
        }

        foreach ($storableByGame as $game => $pkmIds) {
            sgg_data_save("builds/pokemon/storable/storable-pokemon-{$game}.json", $pkmIds, minify: false);
        }
    };

    $generateMegaPokemonList = static function () use ($dataSet): void {
        $newDataSet = [];

        foreach ($dataSet as $pkm) {
            if (!$pkm['isMega']) {
                continue;
            }
            $newDataSet[] = $pkm['id'];
        }

        sgg_data_save('builds/pokemon/mega-pokemon.json', $newDataSet, minify: false);
    };

    $generateGmaxPokemonList = static function () use ($dataSet, $dataSetById): void {
        $newDataSet = [];

        foreach ($dataSet as $pkm) {
            if (!$pkm['canGmax']) {
                continue;
            }
            if (!$pkm['canDynamax']) {
                echo "WARNING: {$pkm['id']} can gmax but not dynamax\n";
            }

            $gmaxableName = $pkm['id'] . '-gmax';
            if (!isset($dataSetById[$gmaxableName]) && !str_ends_with($gmaxableName, '-f-gmax')) {
                echo "WARNING: Gigantamax pokemon '$gmaxableName' not found\n";
            }
            $newDataSet[] = $pkm['id'];
        }

        sgg_data_save('builds/pokemon/gigantamaxable-pokemon.json', $newDataSet, minify: false);
    };

    $generateAlphaPokemonList = static function () use ($dataSetById): void {
        $newDataSet = [];
        $hisuiPkm = sgg_get_dex_pokemon_ids('hisui');

        foreach ($hisuiPkm as $pkmId) {
            $pkm = $dataSetById[$pkmId];
            if ($pkm['canBeAlpha']) {
                $newDataSet[] = $pkm['id'];
            }
        }

        sgg_data_save('builds/pokemon/alpha-pokemon.json', $newDataSet, minify: false);
    };

    $prettifyAllJsonFiles = static function (): void {
        $files = sgg_json_files_in_dir_tree('/sources/', false);

        foreach ($files as $fileName) {
            if (
                str_contains($fileName, 'min.json')
                || str_contains($fileName, 'build.json')) {
                continue;
            }

            sgg_json_encode(sgg_json_decode_file($fileName), false, $fileName); // prettify
        }
    };

    $minifyAllJsonFiles = static function (): void {
        $files = sgg_json_files_in_dir_tree('/builds/', false);

        foreach ($files as $fileName) {
            if (
                str_contains($fileName, 'min.json')) {
                continue;
            }
            // $dataPath = rtrim(sgg_get_data_path(), '/');
            $newFilename = str_replace(['.json'], ['.min.json'], $fileName);
            sgg_json_encode(sgg_json_decode_file($fileName), true, $newFilename); // minify
        }
    };

    $generateGameGamesList = static function (): void {
        $gameSets = sgg_data_load('sources/games/game-sets.json');
        $gameList = [];

        foreach ($gameSets as $data) {
            foreach ($data['games'] as $gameId => $gameName) {
                $gameList[] = [
                    'id' => $gameId,
                    'name' => $gameName,
                    'setId' => $data['id'],
                    'supersetId' => $data['superset'],
                ];
            }
        }
        sgg_data_save('builds/games.json', $gameList, minify: false);
    };

//    $generateFullySortedHomePreset = static function () use ($dataSet): void {
//        $preset = [
//            'id' => 'fully-sorted',
//            'name' => 'Fully Sorted',
//            'version' => 2,
//            'gameSet' => 'home',
//            //'shortDescription' => 'Sorted by Species and their Forms, in their HOME order.',
//            "description" => "Pokémon Boxes sorted by Species and Forms, following original Pokémon HOME order.\n"
//                . "Every newly introduced form will alter the order of all the following Pokémon.",
//            "boxes" => [],
//
//        ];
//        $maxPkmPerBox = 30;
//        $currentBox = 0;
//        foreach ($dataSet as $i => $pkm) {
//            if (!in_array('home', $pkm['storableIn'], true)) {
//                continue;
//            }
//            if (
//                isset($preset['boxes'][$currentBox])
//                && (count($preset['boxes'][$currentBox]['pokemon']) >= $maxPkmPerBox)
//            ) {
//                $currentBox++;
//            }
//            if (!isset($preset['boxes'][$currentBox])) {
//                $preset['boxes'][$currentBox] = [
//                    'pokemon' => [],
//                ];
//            }
//            $preset['boxes'][$currentBox]['pokemon'][] = $pkm['id'];
//        }
//        sgg_data_save('builds/box-presets/home/100-fully-sorted.json', $preset, minify: false); // prettified
//    };

    $generateHisuiBoxesPreset = static function () use ($dataSetById): void {
        $hisuiDex = sgg_data_load('sources/pokedexes/hisui.json');
        $preset = [
            'id' => 'fully-sorted',
            'name' => 'Regional: Sorted by Forms',
            'version' => 1,
            'gameSet' => 'la',
            //'shortDescription' => 'Sorted by Species and their Forms, in their HOME order.',
            "description" => "(Recommended) Pokémon Boxes are sorted by Species and Forms together, following original Legends: Arceus Pokédex order.",
            "boxes" => [],
        ];
        $maxPkmPerBox = 30;
        $currentBox = 0;
        foreach ($hisuiDex as $dexPkm) {
            foreach ($dexPkm['forms'] as $pkmId) {
                $pkm = $dataSetById[$pkmId];
                if (!in_array('la', $pkm['storableIn'], true)) {
                    continue;
                }
                if (
                    isset($preset['boxes'][$currentBox])
                    && (count($preset['boxes'][$currentBox]['pokemon']) >= $maxPkmPerBox)
                ) {
                    $currentBox++;
                }
                if (!isset($preset['boxes'][$currentBox])) {
                    $preset['boxes'][$currentBox] = [
                        'pokemon' => [],
                    ];
                }
                $preset['boxes'][$currentBox]['pokemon'][] = $pkm['id'];
            }
        }
        sgg_data_save('builds/box-presets/la/100-fully-sorted.json', $preset, minify: false);
    };

    $generateGamesetBoxesPreset = static function (
        string $gameSetId,
        int $maxPkmPerBox = 30,
        bool $createMinimal = true
    ) use ($dataSetById): void {
        $storables = sgg_data_load("builds/pokemon/storable/storable-pokemon-{$gameSetId}.json");
        $preset = [
            'id' => 'fully-sorted',
            'name' => 'National: Sorted by Forms',
            'version' => 1,
            'gameSet' => $gameSetId,
            "description" => "Pokémon Boxes are sorted following HOME's National Dex order, mixing Species and Forms together.",
            "boxes" => [],
        ];
        $presetMinimal = [
            'id' => 'fully-sorted-minimal',
            'name' => 'National: Sorted by Forms (Minimal)',
            'version' => 1,
            'gameSet' => $gameSetId,
            "description" => "Pokémon Boxes are sorted following HOME's National Dex order mixing Species and Forms, but Legendary or Mythical Pokémon forms (like Hoopa Unbound, etc) will be excluded.",
            "boxes" => [],
        ];
        $presetBySpecies = [
            'id' => 'sorted-species',
            'name' => 'National: Sorted by Species',
            'version' => 1,
            'gameSet' => $gameSetId,
            "description" => "(Recommended) Pokémon Boxes are sorted following HOME's National Dex order, separating Species and Forms. First you will find all the species, then all the forms starting in a new box (leaving a gap with the species).",
            "boxes" => [],
        ];
        $presetBySpeciesMinimal = [
            'id' => 'sorted-species-minimal',
            'name' => 'National: Sorted by Species (Minimal)',
            'version' => 1,
            'gameSet' => $gameSetId,
            "description" => "Pokémon Boxes are sorted following HOME's National Dex order separating Species and Forms, but Legendary or Mythical Pokémon forms (like Magearna Original Color, etc) will be excluded. First you will find all the species, then all the forms starting in a new box (leaving a gap with the species).",
            "boxes" => [],
        ];
        $currentBox = 0;
        $currentBoxMinimal = 0;
        $total = 0;
        $minimalTotal = 0;

        foreach ($storables as $pkmId) {
            $pkm = $dataSetById[$pkmId];
            if (!in_array($gameSetId, $pkm['storableIn'], true)) {
                continue;
            }
            if (
                isset($preset['boxes'][$currentBox])
                && (count($preset['boxes'][$currentBox]['pokemon']) >= $maxPkmPerBox)
            ) {
                $currentBox++;
            }
            if (
                isset($presetMinimal['boxes'][$currentBoxMinimal])
                && (count($presetMinimal['boxes'][$currentBoxMinimal]['pokemon']) >= $maxPkmPerBox)
            ) {
                $currentBoxMinimal++;
            }
            if (!isset($preset['boxes'][$currentBox])) {
                $preset['boxes'][$currentBox] = [
                    'pokemon' => [],
                ];
            }
            if (!isset($preset['boxes'][$currentBoxMinimal])) {
                $preset['boxes'][$currentBoxMinimal] = [
                    'pokemon' => [],
                ];
            }
            $preset['boxes'][$currentBox]['pokemon'][] = $pkm['id'];
            $total++;
            $isLegendaryForm = ($pkm['isLegendary'] || $pkm['isMythical']) && $pkm['isForm'];
            if (!$isLegendaryForm) {
                $minimalTotal++;
                $presetMinimal['boxes'][$currentBoxMinimal]['pokemon'][] = $pkm['id'];
            }
        }

        // Loop for separated species

        $species = [];
        $forms = [];
        foreach ($storables as $pkmId) {
            $pkm = $dataSetById[$pkmId];
            if (!in_array($gameSetId, $pkm['storableIn'], true)) {
                continue;
            }
            if ($pkm['isForm']) {
                $forms[] = $pkmId;
            } else {
                $species[] = $pkmId;
            }
        }

        // TODO: refactor this big chunk of code    :blush:

        $currentBox = 0;
        $currentBoxMinimal = 0;
        $total2 = 0;
        $minimalTotal2 = 0;

        // Add species first
        foreach ($species as $pkmId) {
            $pkm = $dataSetById[$pkmId];
            if (!isset($presetBySpecies['boxes'][$currentBox])) {
                $presetBySpecies['boxes'][$currentBox] = [
                    'pokemon' => [],
                ];
            }
            if (!isset($presetBySpeciesMinimal['boxes'][$currentBoxMinimal])) {
                $presetBySpeciesMinimal['boxes'][$currentBoxMinimal] = [
                    'pokemon' => [],
                ];
            }
            if (count($presetBySpecies['boxes'][$currentBox]['pokemon']) >= $maxPkmPerBox) {
                $currentBox++;
            }
            if (count($presetBySpeciesMinimal['boxes'][$currentBoxMinimal]['pokemon']) >= $maxPkmPerBox) {
                $currentBoxMinimal++;
            }
            $presetBySpecies['boxes'][$currentBox]['pokemon'][] = $pkm['id'];
            $presetBySpeciesMinimal['boxes'][$currentBoxMinimal]['pokemon'][] = $pkm['id'];
            $total2++;
            $minimalTotal2++;
        }

        // Leave a gap
        $currentBox++;
        $currentBoxMinimal++;

        // Continue with forms
        foreach ($forms as $pkmId) {
            $pkm = $dataSetById[$pkmId];
            if (!isset($presetBySpecies['boxes'][$currentBox])) {
                $presetBySpecies['boxes'][$currentBox] = [
                    'pokemon' => [],
                ];
            }
            if (!isset($presetBySpeciesMinimal['boxes'][$currentBoxMinimal])) {
                $presetBySpeciesMinimal['boxes'][$currentBoxMinimal] = [
                    'pokemon' => [],
                ];
            }
            if (count($presetBySpecies['boxes'][$currentBox]['pokemon']) >= $maxPkmPerBox) {
                $currentBox++;
            }
            if (count($presetBySpeciesMinimal['boxes'][$currentBoxMinimal]['pokemon']) >= $maxPkmPerBox) {
                $currentBoxMinimal++;
            }
            $presetBySpecies['boxes'][$currentBox]['pokemon'][] = $pkm['id'];
            $total2++;
            $isLegendaryForm = ($pkm['isLegendary'] || $pkm['isMythical']) && $pkm['isForm'];
            if (!$isLegendaryForm) {
                $minimalTotal2++;
                $presetBySpeciesMinimal['boxes'][$currentBoxMinimal]['pokemon'][] = $pkm['id'];
            }
        }

        // Save files

        sgg_data_save("builds/box-presets/{$gameSetId}/101-sorted-species.json", $presetBySpecies, minify: false);
        if ($createMinimal && ($total2 !== $minimalTotal2)) {
            sgg_data_save(
                        "builds/box-presets/{$gameSetId}/102-sorted-species-minimal.json",
                        $presetBySpeciesMinimal,
                minify: false
            );
        }

        sgg_data_save("builds/box-presets/{$gameSetId}/103-fully-sorted.json", $preset, minify: false);
        if ($createMinimal && ($total !== $minimalTotal)) {
            sgg_data_save(
                        "builds/box-presets/{$gameSetId}/104-fully-sorted-minimal.json",
                        $presetMinimal,
                minify: false
            );
        }
    };

    $mergeAllBoxPresets = static function (): void {
        $buildBoxPresetFiles = sgg_json_files_in_dir_tree('builds/box-presets', false);
        $sourceBoxPresetFiles = sgg_json_files_in_dir_tree('sources/box-presets', false);
        $presetsByGameSet = [];
        $unsortedFiles = [];
        $sortedFiles = [];

        // Get all preset files from sources
        foreach ($sourceBoxPresetFiles as $fileName) {
            $presetKey = basename($fileName, '.json');
            $gameSet = basename(dirname($fileName));
            $unsortedFiles[$gameSet][$presetKey] = $fileName;
        }

        // Get all preset files from builds
        foreach ($buildBoxPresetFiles as $fileName) {
            $presetKey = basename($fileName, '.json');
            $gameSet = basename(dirname($fileName));
            $unsortedFiles[$gameSet][$presetKey] = $fileName;
        }

        // Sort preset key alphabetically on every game
        foreach ($unsortedFiles as $gameSet => $presets) {
            $presetKeys = array_keys($presets);
            sort($presetKeys);
            foreach ($presetKeys as $presetKey) {
                $sortedFiles[$gameSet][] = $presets[$presetKey];
            }
        }

        // Merge all presets
        foreach ($sortedFiles as $gameSet => $presetFiles) {
            foreach ($presetFiles as $presetFile) {
                $data = sgg_json_decode_file($presetFile);
                $data['gameSet'] = $gameSet;
                $presetsByGameSet[$gameSet][$data['id']] = $data;
            }
        }
        sgg_data_save('builds/box-presets-full.json', $presetsByGameSet, minify: false); // prettified
    };

    $generateNationalPokedex = static function (): void {
        $pokemonIds = sgg_get_sorted_pokemon_ids();
        $dex = [];

        $dexNum = 0;
        foreach ($pokemonIds as $pokemonId) {
            $fileName = 'sources/pokemon/entries/' . $pokemonId . '.json';
            $data = sgg_data_load($fileName);
            if ($data['id'] !== $pokemonId) {
                throw new \RuntimeException('ID mismatch: ' . $pokemonId . ' vs ' . $data['id']);
            }
            if ($data['isDefault'] && !$data['isForm']) {
                $dexNum++;
                $dex[] = [
                    'id' => $pokemonId,
                    'dexNum' => $dexNum,
                    'forms' => $data['forms'],
                ];
            }
        }
        sgg_data_save('builds/pokedexes/national.json', $dex, minify: false); // prettified
    };

    $generatePokemonAvailabilities = static function () use ($dataSet): void {
        $availability = [];
        $unobtainable = [];
        $unobtainableShiny = [];

        foreach ($dataSet as $pkm) {
            $availability[$pkm['id']] = [
                'obtainableIn' => $pkm['obtainableIn'],
                'storableIn' => $pkm['storableIn'],
                'shinyReleased' => $pkm['shinyReleased'],
            ];
            if (empty($pkm['obtainableIn'])) {
                $unobtainable[] = $pkm['id'];
            }
            if (!$pkm['shinyReleased']) {
                $unobtainableShiny[] = $pkm['id'];
            }
        }

        sgg_data_save('builds/pokemon/pokemon-availability.json', $availability, minify: false);
        sgg_data_save('builds/pokemon/pokemon-unobtainable.json', $unobtainable, minify: false);
        sgg_data_save('builds/pokemon/pokemon-unobtainable-shiny.json', $unobtainableShiny, minify: false);
    };

    // TASKS runner:
    // TODO generate national dex

    $saveMergedPokemonEntries();
    $generatePokemonEntriesMinimal();

    $generateStorablePokemonList();
    //$generateMegaPokemonList();
    $generateGmaxPokemonList();
    $generateAlphaPokemonList();

   // $generateFullySortedHomePreset();
    $generateHisuiBoxesPreset();
    $generateGamesetBoxesPreset('home', 30);
    $generateGamesetBoxesPreset('bdsp', 30);
    $generateGamesetBoxesPreset('lgpe', 1000);
    $generateGamesetBoxesPreset('swsh', 30);
    $generateGamesetBoxesPreset('go', 9999);
    $generatePokemonAvailabilities();

    $generateGameGamesList();
    $mergeAllBoxPresets();
    $generateNationalPokedex();

    $prettifyAllJsonFiles();
    $minifyAllJsonFiles();

    echo "[OK] Build finished!\n";
})();
