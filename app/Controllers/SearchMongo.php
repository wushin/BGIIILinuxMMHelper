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
use Throwable;

/**
 * REST endpoint: /search/mongo?q=term&page=n&dirs[]=slugA&dirs[]=slugB&exts[]=xml&exts[]=lsx&roots[]=GameData
 * - Matches current schema fields: root, slug, rel, abs, ext, kind, size, mtime, raw
 * - Full-text-ish search via case-insensitive regex on raw + path fields
 */
class SearchMongo extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $q      = (string) ($this->request->getGet('q') ?? '');
        $page   = max(1, (int) $this->request->getGet('page'));
        $per    = max(1, (int) ($this->request->getGet('per') ?? 10)); // default 10
        $dirs   = $this->request->getGet('dirs');   // array of slugs
        $exts   = $this->request->getGet('exts');   // array of extensions
        $roots  = $this->request->getGet('roots');  // array of roots e.g. ['GameData','UnpackedMods']

        // Build filter to match current schema
        $filter = [];

        if (is_array($roots) && !empty($roots)) {
            $filter['root'] = ['$in' => array_values(array_filter(array_map('strval', $roots)))];
        }

        if (is_array($dirs) && !empty($dirs)) {
            // "dirs" maps to "slug" in the new schema
            $filter['slug'] = ['$in' => array_values(array_filter(array_map('strval', $dirs)))];
        }

        if (is_array($exts) && !empty($exts)) {
            $filter['ext'] = ['$in' => array_values(array_filter(array_map(fn($e)=> strtolower((string)$e), $exts)))];
        }

        if ($q !== '') {
            // Case-insensitive regex search across raw + path fields
            $filter['$or'] = [
                ['raw' => ['$regex' => $q, '$options' => 'i']],
                ['rel' => ['$regex' => $q, '$options' => 'i']],
                ['abs' => ['$regex' => $q, '$options' => 'i']],
            ];
        }

        log_message('info', 'Mongo search request', [
            'q'     => $q,
            'page'  => $page,
            'per'   => $per,
            'filt'  => $filter,
        ]);

        try {
            /** @var \MongoDB\Database $db */
            $col  = service('mongoCollection');

            $opts = [
                'skip'       => ($page - 1) * $per,
                'limit'      => $per,
                'projection' => [
                    '_id'   => 0,
                    'root'  => 1,
                    'slug'  => 1,
                    'rel'   => 1,
                    'abs'   => 1,
                    'ext'   => 1,
                    'kind'  => 1,
                    'size'  => 1,
                    'mtime' => 1,
                    'raw'   => 1,   // keep for client-side highlighting
                ],
                'sort' => [
                    'root' => 1,
                    'slug' => 1,
                    'rel'  => 1,
                ],
            ];

            $cursor  = $col->find($filter, $opts);
            $total   = $col->countDocuments($filter);
            $results = iterator_to_array($cursor, false);

            return $this->respond([
                'ok'       => true,
                'query'    => $q,
                'filters'  => [
                    'roots' => $roots ?: [],
                    'dirs'  => $dirs  ?: [],
                    'exts'  => $exts  ?: [],
                ],
                'page'     => $page,
                'perPage'  => $per,
                'total'    => $total,
                'results'  => $results,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Mongo search failed: ' . $e->getMessage());
            return $this->failServerError('Mongo search failed.');
        }
    }

    /**
     * GET /search/mongo/filters
     * Distinct lists matching the new schema.
     * Returns:
     * {
     *   roots: [...],    // GameData, UnpackedMods
     *   dirs: [...],     // slugs (top-level mod folders under UnpackedMods or pseudo "GameData")
     *   exts: [...]      // file extensions
     * }
     */
    public function filters()
    {
        try {
            /** @var \MongoDB\Database $db */
            $col  = service('mongoCollection');

            $roots = $col->distinct('root');
            $slugs = $col->distinct('slug');
            $exts  = $col->distinct('ext');

            // Normalize & sort
            $roots = array_values(array_filter(array_map('strval', $roots)));
            $slugs = array_values(array_filter(array_map('strval', $slugs)));
            $exts  = array_values(array_unique(array_map('strtolower', array_filter($exts))));

            sort($roots, SORT_NATURAL | SORT_FLAG_CASE);
            sort($slugs, SORT_NATURAL | SORT_FLAG_CASE);
            sort($exts,  SORT_NATURAL | SORT_FLAG_CASE);

            return $this->response->setJSON([
                'roots' => $roots,
                'dirs'  => $slugs, // keep key name "dirs" for the UI; values are slugs in new schema
                'exts'  => $exts,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Mongo filters failed: ' . $e->getMessage());
            return $this->failServerError('Failed to fetch filter options');
        }
    }
}
?>
