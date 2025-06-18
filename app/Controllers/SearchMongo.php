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

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use MongoDB\Client as MongoClient;
use Throwable;

/**
 * REST endpoint: /search/mongo?q=term&page=n
 * Returns paginated, full‑text Mongo results (keys + values) with highlighting handled client‑side.
 */
class SearchMongo extends ResourceController
{
    protected $format = 'json';   // respond() returns JSON

    public function index()
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $query = $this->request->getGet('q') ?? '';
        $page  = max(1, (int) $this->request->getGet('page'));
        $per   = 5;
        $dirs  = $this->request->getGet('dirs');
        $types = $this->request->getGet('types');

        $filter = [];

        if ($dirs && is_array($dirs)) {
            $filter['category'] = ['$in' => $dirs];
        }

        if ($types && is_array($types)) {
            $filter['extension'] = ['$in' => $types];
        }

        if ($query !== '') {
            $filter['$or'] = [
                ['raw' => ['$regex' => $query, '$options' => 'i']],
            ];
        }

        log_message('info', "Mongo search request: '{$query}' (page {$page})");

        try {
            $client      = new MongoClient('mongodb://bg3mmh-mongo:27017');
            $collection  = $client->bg3mmh->files;

            $opts = [
                'skip'       => ($page - 1) * $per,
                'limit'      => $per,
                'projection' => [
                'filepath' => 1,
                'raw'      => 1,
                ],
                'sort' => ['filepath' => 1], // fallback stable sort
            ];

            unset($opts['projection']['score']);

            $cursor  = $collection->find($filter, $opts);
            $total   = $collection->countDocuments($filter);
            $results = iterator_to_array($cursor, false);

            return $this->respond([
                'results'  => $results,
                'total'    => $total,
                'page'     => $page,
                'perPage'  => $per,
            ]);

        } catch (Throwable $e) {
            log_message('error', 'Mongo search failed: ' . $e->getMessage());
            return $this->failServerError('Mongo search failed.');
        }
    }

    public function filters()
    {
        try {
            $manager = new \MongoDB\Driver\Manager(env('mongo.default.uri'));
            $dbName = env('mongo.default.db', 'bg3mmh');

            $categoriesCmd = new \MongoDB\Driver\Command([
                'distinct' => 'files',
                'key' => 'category',
            ]);

            $extensionsCmd = new \MongoDB\Driver\Command([
                'distinct' => 'files',
                'key' => 'extension',
            ]);

            $categoriesCursor = $manager->executeCommand($dbName, $categoriesCmd);
            $extensionsCursor = $manager->executeCommand($dbName, $extensionsCmd);

            $categories = $categoriesCursor->toArray()[0]->values ?? [];
            $extensions = $extensionsCursor->toArray()[0]->values ?? [];

            // Normalize and clean
            $dirs = array_values(array_filter($categories));
            $exts = array_values(array_unique(array_map('strtolower', array_filter($extensions))));

            sort($dirs);
            sort($exts);

            return $this->response->setJSON([
                'dirs' => $dirs,
                'exts' => $exts,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Mongo filters failed: ' . $e->getMessage());
            return $this->failServerError('Failed to fetch filter options');
        }
    }
}
?>
