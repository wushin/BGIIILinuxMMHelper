<?php
/**
 * This file is part of BGIII Mod Manager Linux Helper.
 *
 * Copyright (C) 2025 Wushin.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Rebuild / refresh Mongo index for UnpackedMods and/or GameData
 *
 * Usage:
 *   php spark mongoindex:scan
 *   php spark mongoindex:scan --root GameData
 *   php spark mongoindex:scan --root UnpackedMods
 *   php spark mongoindex:scan --root all --rebuild
 */
class MongoIndex extends BaseCommand
{
    protected $group       = 'BG3';
    protected $name        = 'mongoindex:scan';
    protected $description = 'Scan configured roots into MongoDB (UnpackedMods, GameData).';
    protected $usage       = 'mongoindex:scan [--root=all|GameData|UnpackedMods] [--rebuild]';

    public function run(array $params)
    {
        $root    = CLI::getOption('root')    ?? 'all';
        $rebuild = (bool) CLI::getOption('rebuild');

        /** @var \App\Services\MongoIndexer $indexer */
        $indexer = service('mongoIndexer');

        $progress = function (int $n) {
            CLI::write("  indexed: {$n}", 'green');
        };

        switch (strtolower($root)) {
            case 'alldata':
            case 'all':
                CLI::write('Scanning: UnpackedMods', 'yellow');
                $indexer->scanRoot('UnpackedMods', $rebuild, $progress);
                CLI::write('Scanning: GameData', 'yellow');
                $indexer->scanRoot('GameData', $rebuild, $progress);
                break;

            case 'gamedata':
                CLI::write('Scanning: GameData', 'yellow');
                $indexer->scanRoot('GameData', $rebuild, $progress);
                break;

            case 'unpackedmods':
                CLI::write('Scanning: UnpackedMods', 'yellow');
                $indexer->scanRoot('UnpackedMods', $rebuild, $progress);
                break;

            default:
                CLI::error("Unknown --root value: {$root}");
                return;
        }

        CLI::write('Done.', 'green');
    }
}
?>
