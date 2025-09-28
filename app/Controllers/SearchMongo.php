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

class SearchMongo extends ResourceController
{
    protected $format = 'json';

    public function __construct(private readonly MongoClient $client)
    {
    }

    private function col()
    {
        $db = $this->client->selectDatabase(config('Mongo')->database);
        return $db->selectCollection(config('Mongo')->collection ?? 'bg3_files');
    }

    /**
     * GET /search/mongo
     * q, page, dirs[], exts[], regions[], groups[]
     */
    public function index()
    {
        $q       = trim((string) $this->request->getGet('q'));
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 20;

        $dirs    = $this->request->getGet('dirs')    ?? $this->request->getGet('dirs[]');
        $exts    = $this->request->getGet('exts')    ?? $this->request->getGet('exts[]');
        $regions = $this->request->getGet('regions') ?? $this->request->getGet('regions[]');
        $groups  = $this->request->getGet('groups')  ?? $this->request->getGet('groups[]');

        $dirs    = is_array($dirs)    ? array_values(array_unique(array_filter($dirs)))    : [];
        $exts    = is_array($exts)    ? array_values(array_unique(array_filter($exts)))    : [];
        $regions = is_array($regions) ? array_values(array_unique(array_filter($regions))) : [];
        $groups  = is_array($groups)  ? array_values(array_unique(array_filter($groups)))  : [];

        $filterAnd = [];

        if ($q !== '') {
            // Prefer the text index; falls back to case-insensitive regex on raw if needed
            $filterAnd[] = ['$or' => [
                ['$text' => ['$search' => $q]],
                ['raw'   => ['$regex' => $q, '$options' => 'i']],
                ['names' => ['$regex' => $q, '$options' => 'i']],
            ]];
        }

        if ($dirs)    $filterAnd[] = ['top'           => ['$in' => $dirs]];
        if ($exts)    $filterAnd[] = ['ext'           => ['$in' => array_map('strtolower', $exts)]];
        if ($regions) $filterAnd[] = ['lsx.regions'   => ['$in' => $regions]];
        if ($groups)  $filterAnd[] = ['lsx.groupings' => ['$in' => $groups]];

        $filter = $filterAnd ? ['$and' => $filterAnd] : [];

        $opts = [
            'skip'  => ($page - 1) * $perPage,
            'limit' => $perPage,
            'sort'  => ['mtime' => -1],
        ];

        // If $text is used, sort by textScore primarily
        if ($q !== '') {
            $opts['projection'] = ['score' => ['$meta' => 'textScore'], 'raw' => 1, 'abs' => 1, 'rel' => 1, 'name' => 1, 'top' => 1];
            $opts['sort']       = ['score' => ['$meta' => 'textScore'], 'mtime' => -1];
        } else {
            $opts['projection'] = ['raw' => 1, 'abs' => 1, 'rel' => 1, 'name' => 1, 'top' => 1];
        }

        $col = $this->col();
        $cursor = $col->find($filter, $opts);
        $results = [];
        foreach ($cursor as $doc) {
            $filepath = ($doc['top'] ?? '') !== ''
                ? ($doc['top'] . '/' . ltrim($doc['rel'] ?? $doc['name'], '/'))
                : ($doc['rel'] ?? $doc['name']);

            $results[] = [
                'filepath' => $filepath ?? ($doc['abs'] ?? '(unknown)'),
                'raw'      => $doc['raw'] ?? '',
            ];
        }

        $total = $col->countDocuments($filter);

        return $this->respond([
            'page'    => $page,
            'perPage' => $perPage,
            'total'   => $total,
            'results' => $results,
        ]);
    }

    /**
     * GET /search/mongo-filters
     * Returns only: dirs, exts, regions, groups
     */
    public function filters()
    {
        $col = $this->col();

        $dirs    = $col->distinct('top', []);
        $exts    = $col->distinct('ext', []);
        $regions = $col->distinct('lsx.regions',   [['lsx.regions' => ['$exists' => true]]]);
        $groups  = $col->distinct('lsx.groupings', [['lsx.groupings' => ['$exists' => true]]]);

        // normalize + sort
        $dirs    = $this->cleanList($dirs);
        $exts    = array_map('strtolower', $this->cleanList($exts));
        $regions = $this->cleanList($regions);
        $groups  = $this->cleanList($groups);

        sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
        sort($exts, SORT_NATURAL | SORT_FLAG_CASE);
        sort($regions, SORT_NATURAL | SORT_FLAG_CASE);
        sort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        return $this->respond([
            'dirs'    => $dirs,
            'exts'    => $exts,
            'regions' => $regions,
            'groups'  => $groups,
        ]);
    }

    private function cleanList($arr): array
    {
        $out = [];
        foreach ((array) $arr as $v) {
            if ($v === null) continue;
            $s = trim((string) $v);
            if ($s === '') continue;
            // Drop dot-prefixed names from filters defensively
            if ($s[0] === '.') continue;
            $out[$s] = true;
        }
        return array_keys($out);
    }
}
?>
