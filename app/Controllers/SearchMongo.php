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

use CodeIgniter\API\ResponseTrait;
use MongoDB\Client as MongoClient;
use MongoDB\Collection;
use App\Libraries\LsxHelper;

class SearchMongo extends BaseController {
    /** @var string[] */
    private array $allowedTextExts = ['txt','khn','xml','lsx','ann','anc','cln','clc'];
    protected $format = 'json';

    private function col(): Collection
    {
        $cfg = config('Mongo');
        $uri = $cfg->uri ?? 'mongodb://127.0.0.1:27017';
        $db  = $cfg->db  ?? ($cfg->database ?? 'bg3');
        $col = $cfg->collection ?? 'bg3_files';

        $clientOptions = is_array($cfg->clientOptions ?? null) ? $cfg->clientOptions : [];
        $driverOptions = is_array($cfg->driverOptions ?? null) ? $cfg->driverOptions : [];

        $client = new MongoClient($uri, $clientOptions, $driverOptions);
        return $client->selectDatabase($db)->selectCollection($col);
    }

    /** GET /search/mongo — q, page, dirs[], exts[], regions[], groups[] */
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
        $groups  = is_array($groups)  ? array_values(array_unique(array_map('strtolower', array_filter($groups)) )) : [];

        $and = [];
        // Always restrict results to allowed text extensions
        $and[] = ['ext' => ['$in' => $this->allowedTextExts]];

        if ($q !== '') {
            $and[] = ['$or' => [
                ['raw'   => ['$regex' => $q, '$options' => 'i']],
                ['names' => ['$regex' => $q, '$options' => 'i']],
                ['name'  => ['$regex' => $q, '$options' => 'i']],
                ['uuids' => $q],
                ['cuids' => $q],
            ]];
        }

        if ($dirs) {
            $and[] = ['top' => ['$in' => $dirs]];
        }

        if ($exts) {
            $exts = array_values(array_filter(array_map('strtolower', $exts), fn($e) => in_array($e, $this->allowedTextExts, true)));
            if ($exts) { $and[] = ['ext' => ['$in' => $exts]]; }
        }

        // Regions (support BOTH schemas: lsx.region (str) and lsx.regions (arr))
        if ($regions) {
            $and[] = ['$or' => [
                ['lsx.region'  => ['$in' => $regions]],
                ['lsx.regions' => ['$in' => $regions]],
            ]];
        }

        // Groups: if we have lsx.group on docs, match it; otherwise derive regions-by-group and match those
        if ($groups) {
            $ors = [];

            // 1) direct match if collection has lsx.group (lowercase)
            $ors[] = ['lsx.group' => ['$in' => $groups]];

            // 2) derive regions that belong to requested groups,
            //    then match docs where lsx.region/lsx.regions are in that set.
            $allRegions = $this->allRegionIds();
            $wantRegions = [];
            foreach ($allRegions as $r) {
                $g = strtolower(LsxHelper::regionGroupFromId($r));
                if (in_array($g, $groups, true)) {
                    $wantRegions[$r] = true;
                }
            }
            $wantList = array_keys($wantRegions);
            if ($wantList) {
                $ors[] = ['lsx.region'  => ['$in' => $wantList]];
                $ors[] = ['lsx.regions' => ['$in' => $wantList]];
            }

            if ($ors) $and[] = ['$or' => $ors];
        }

        $filter = $and ? ['$and' => $and] : [];

        $opts = [
            'skip'       => ($page - 1) * $perPage,
            'limit'      => $perPage,
            'sort'       => ['mtime' => -1],
            'projection' => ['raw' => 1, 'abs' => 1, 'rel' => 1, 'name' => 1, 'top' => 1],
        ];

        $col     = $this->col();
        $cursor  = $col->find($filter, $opts);
        $results = [];
        foreach ($cursor as $doc) {
            $results[] = [
                'filepath' => $doc['rel'] ?? $doc['abs'] ?? $doc['name'] ?? '(unknown)',
                'raw'      => $doc['raw'] ?? '',
            ];
        }

        $total = $col->countDocuments($filter);

        return $this->response->setJSON([
            'page'    => $page,
            'perPage' => $perPage,
            'total'   => $total,
            'results' => $results,
        ]);
    }

    /** GET /search/mongo-filters — returns dirs, exts, regions, groups (backward-compatible) */
    public function filters()
    {
        $col = $this->col();

        // Basic filters
        $dirs = $this->cleanList($col->distinct('top', []));
        $exts = array_map('strtolower', $this->cleanList($col->distinct('ext', [])));
        $exts = array_values(array_filter($exts, fn($e) => in_array($e, $this->allowedTextExts, true)));
        if (!$exts) { $exts = $this->allowedTextExts; }

        // Regions from BOTH schemas:
        //   - singular: lsx.region (string)
        //   - legacy:   lsx.regions (array)
        $regionsSingular = $this->cleanList($col->distinct('lsx.region', ['lsx.region' => ['$exists' => true, '$ne' => '']]));
        $regionsArray    = $this->distinctFromArrayField($col, 'lsx.regions');

        $regions = $this->uniqMerge($regionsSingular, $regionsArray);

        // Groups: prefer stored lsx.group, else derive from region ids via LsxHelper
        $storedGroups = $this->cleanList($col->distinct('lsx.group', ['lsx.group' => ['$exists' => true, '$ne' => '']]));
        $derivedGroups = [];
        foreach ($regions as $r) {
            $derivedGroups[] = strtolower(LsxHelper::regionGroupFromId($r));
        }
        $groups = $this->uniqMerge(array_map('strtolower', $storedGroups), $derivedGroups);

        sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
        sort($exts, SORT_NATURAL | SORT_FLAG_CASE);
        sort($regions, SORT_NATURAL | SORT_FLAG_CASE);
        sort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        return $this->response->setJSON([
            'dirs'    => $dirs,
            'exts'    => $exts,
            'regions' => $regions,        // region ids like "Templates", "Progressions", "DialogBank", …
            'groups'  => $groups,         // "assets", "dialog", "gameplay", "meta", "unknown"
        ]);
    }

    /** Collect ALL distinct region ids from both schemas */
    private function allRegionIds(): array
    {
        $col = $this->col();
        $a = $this->cleanList($col->distinct('lsx.region', ['lsx.region' => ['$exists' => true, '$ne' => '']]));
        $b = $this->distinctFromArrayField($col, 'lsx.regions');
        return $this->uniqMerge($a, $b);
    }

    /** Distinct values from an array field via aggregation (handles legacy lsx.regions) */
    private function distinctFromArrayField(Collection $col, string $path): array
    {
        $pipeline = [
            ['$match'  => [$path => ['$exists' => true, '$ne' => []]]],
            ['$project'=> ['v' => '$' . $path]],
            ['$unwind' => '$v'],
            ['$match'  => ['v' => ['$type' => 'string', '$ne' => '']]],
            ['$group'  => ['_id' => null, 'vals' => ['$addToSet' => '$v']]],
        ];
        $vals = [];
        foreach ($col->aggregate($pipeline) as $doc) {
            $vals = (array)($doc['vals'] ?? []);
        }
        return $this->cleanList($vals);
    }

    /** Utilities */
    private function cleanList($arr): array
    {
        $out = [];
        foreach ((array) $arr as $v) {
            if ($v === null) continue;
            $s = trim((string) $v);
            if ($s === '' || $s[0] === '.') continue;
            $out[$s] = true;
        }
        return array_keys($out);
    }

    private function uniqMerge(array ...$lists): array
    {
        $set = [];
        foreach ($lists as $list) {
            foreach ($list as $v) {
                $set[$v] = true;
            }
        }
        return array_keys($set);
    }
}
?>
