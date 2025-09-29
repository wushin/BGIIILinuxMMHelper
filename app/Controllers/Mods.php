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

use CodeIgniter\HTTP\ResponseInterface;
use App\Helpers\XmlHelper;
use App\Helpers\TextParserHelper;
use App\Libraries\LsxHelper;
use App\Libraries\LocalizationScanner;

class Mods extends BaseController
{
    /** @var \App\Services\SelectionService */
    protected $selection;

    public function __construct()
    {
        $this->selection = service('selection');
    }

    /* ======================== ROUTES ======================== */

    // GET /mods  → roots summary (HTML or JSON)
    public function list(): ResponseInterface
    {
        $paths = service('pathResolver');

        $rootsAbs = [
            'MyMods'       => $paths->myMods(),
            'UnpackedMods' => $paths->unpackedMods(),
            'GameData'     => $paths->gameData(),
        ];

        $summary = [];
        foreach ($rootsAbs as $key => $abs) {
            $summary[$key] = [
                'configured' => is_string($abs) && $abs !== '' && is_dir($abs),
                'path'       => $abs ?: null,
                'count'      => $this->countDirs($abs),
            ];
        }

        if ($this->wantsJson()) {
            return $this->response->setJSON(['ok' => true, 'roots' => $summary]);
        }

        return $this->response->setBody(view('mods/roots', [
            'pageTitle' => 'BG3LinuxHelper',
            'roots'     => $summary,
        ]));
    }

    // GET /mods/{Root} → (MyMods uses browse layout)
    public function listRoot(string $root): ResponseInterface
    {
        $rootKey = $this->normalizeRoot($root);
        $base    = $this->rootPath($rootKey);
        if (!$base || !is_dir($base)) {
            return $this->notFound("Root not configured: {$rootKey}");
        }

        // Special behavior: for MyMods show the combined "browse" page
        if ($rootKey === 'MyMods') {
            $myMods = $this->getMyModsList();  // array of directory names

            if ($this->wantsJson()) {
                return $this->response->setJSON([
                    'ok'    => true,
                    'root'  => $rootKey,
                    'mods'  => $myMods,
                    'slug'  => '',
                    'path'  => '',
                    'tree'  => [], // no mod selected yet
                ]);
            }

            return $this->response->setBody(view('mods/browse', [
                'pageTitle'    => 'MyMods — Directories',
                'root'         => $rootKey,
                'slug'         => '',
                'path'         => '',
                'tree'         => [],     // empty until a mod is chosen
                'myMods'       => $myMods, // top card content
                'baseKey'      => 'mods/' . $rootKey,
                'selectedFile' => null,
            ]));
        }

        // Default behavior for other roots (GameData/UnpackedMods): directory list
        $mods = $this->listDirs($base);

        if ($this->wantsJson()) {
            return $this->response->setJSON(['ok' => true, 'root' => $rootKey, 'mods' => $mods]);
        }

        return $this->response->setBody(view('mods/mods', [
            'pageTitle' => "BG3LinuxHelper — {$rootKey}",
            'root'      => $rootKey,
            'mods'      => $mods,
        ]));
    }

    /**
     * Returns a sorted list of top-level directory names under MyMods (no recursion, no hidden).
     */
    private function getMyModsList(): array
    {
        try {
            $base = service('pathResolver')->myMods();
        } catch (\Throwable $e) {
            return [];
        }

        if (!$base || !is_dir($base)) {
            return [];
        }

        $dirs = [];
        $dh = @opendir($base);
        if ($dh === false) {
            return [];
        }

        while (($name = readdir($dh)) !== false) {
            if ($name === '.' || $name === '..' || $name[0] === '.') {
                continue; // skip dot/hidden
            }
            $full = $base . DIRECTORY_SEPARATOR . $name;
            if (is_dir($full)) {
                $dirs[] = $name; // top-level dir only
            }
        }
        closedir($dh);

        sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
        return $dirs;
    }

    // Returns the names of immediate subdirectories under $base (dirs only, A→Z).
    private function listDirs(?string $base): array
    {
        if (!$base || !is_dir($base)) return [];
        $out = [];
        foreach (scandir($base) ?: [] as $name) {
            if ($name === '.' || $name === '..' || $name[0] === '.') continue;
            if (is_dir($base . DIRECTORY_SEPARATOR . $name)) {
                $out[] = $name;
            }
        }
        usort($out, static fn($a, $b) => strcasecmp($a, $b));
        return $out;
    }

    // GET /mods/{Root}/{slug} → full recursive tree (left column)
    public function mod(string $root, string $slug): ResponseInterface
    {
        $rootKey = $this->normalizeRoot($root);
        $abs     = service('pathResolver')->join($rootKey, $slug);
        if (!is_dir($abs)) return $this->notFound('Mod not found');

        $tree = service('directoryScanner')->tree($abs, '');

        $sel = $this->selection->recall(['root' => $rootKey, 'slug' => $slug]);

        if ($this->wantsJson()) {
            return $this->response->setJSON([
                'ok'        => true,
                'root'      => $rootKey,
                'slug'      => $slug,
                'path'      => '',
                'tree'      => $tree,
                'selection' => $this->selection->recall(['root' => $rootKey, 'slug' => $slug]),
            ]);        
        }

        return $this->response->setBody(view('mods/browse', [
            'pageTitle'    => $slug,
            'root'         => $rootKey,
            'slug'         => $slug,
            'path'         => '',
            'tree'         => $tree,
            'myMods'       => $rootKey === 'MyMods' ? $this->getMyModsList() : [],
            'baseKey'      => 'mods/' . $rootKey . '/' . $slug,
            'selectedFile' => $sel['relPath'] ?? null,
        ]));
    }

    // GET /mods/{Root}/{slug}/{path...} → directory (tree) or file (content)
    public function view(string $root, string $slug, string $relPath): ResponseInterface
    {
        $rootKey = $this->normalizeRoot($root);
        $relPath = ltrim($relPath, '/');
        $abs     = service('pathResolver')->join($rootKey, $slug . ($relPath ? '/' . $relPath : ''));

        if (is_dir($abs)) {
            $tree = service('directoryScanner')->tree($abs, $relPath);

            $sel = $this->selection->recall(['root' => $rootKey, 'slug' => $slug]);

            if ($this->wantsJson()) {
                return $this->response->setJSON([
                    'ok'        => true,
                    'root'      => $rootKey,
                    'slug'      => $slug,
                    'path'      => $relPath,
                    'tree'      => $tree,
                    'selection' => $this->selection->recall(['root' => $rootKey, 'slug' => $slug]),
                ]);
            }

            return $this->response->setBody(view('mods/browse', [
                'pageTitle'    => "{$slug}/{$relPath}",
                'root'         => $rootKey,
                'slug'         => $slug,
                'path'         => $relPath,
                'tree'         => $tree,
                'myMods'       => $rootKey === 'MyMods' ? $this->getMyModsList() : [],
                'baseKey'      => 'mods/' . $rootKey . '/' . $slug,
                'selectedFile' => $sel['relPath'] ?? null,
            ]));
        }

        // File
        try {
            $bytes = service('contentService')->read($abs);
        } catch (\Throwable $e) {
            return $this->notFound('File not found');
        }

        $ext   = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $kinds = service('mimeGuesser');
        $kind  = $kinds->kindFromExt($ext);

        $payload = null;
        $meta    = [];

        switch ($kind) {
            case 'txt':
            case 'khn':
                $payload = $this->tryTxtToJson($bytes);
                break;

            case 'xml':
                $payload = $this->tryXmlToJson($bytes);
                break;

            case 'lsx': {
                // Detect region + group and parse with handle-map enrichment
                $modAbs    = service('pathResolver')->join($rootKey, $slug);
                $scanner   = new LocalizationScanner(true, false);
                $langXmls  = $scanner->findLocalizationXmlsForMod($modAbs);
                $handleMap = $scanner->buildHandleMapFromFiles($langXmls, true);

                $region      = LsxHelper::detectRegionFromHead($bytes) ?? 'unknown';
                $regionGroup = LsxHelper::regionGroupFromId($region);

                $jsonTree = $scanner->parseLsxWithHandleMapToJson($bytes, $handleMap);
                $payload  = json_decode($jsonTree, true);

                $meta['region']      = $region;
                $meta['regionGroup'] = $regionGroup;
                break;
            }

            case 'image':
                if ($ext === 'dds') {
                    $uri = service('imageTransformer')->ddsToPngDataUri($abs);
                    $payload = ['dataUri' => $uri, 'converted' => (bool)$uri, 'from' => 'dds'];
                } else {
                    $payload = [
                        'dataUri'   => 'data:' . $kinds->mimeFromExt($ext) . ';base64,' . base64_encode($bytes),
                        'converted' => false,
                    ];
                }
                break;

            default:
                $payload = ['raw' => $bytes];
                break;
        }

        if ($this->wantsJson()) {
            $rawOut = in_array($kind, ['txt','khn','xml','lsx','unknown'], true) ? $bytes : null;

            $this->selection->remember([
                'root'    => $rootKey,
                'slug'    => $slug,
                'relPath' => $relPath,
                'ext'     => $ext,
                'kind'    => $kind,
            ]);

            return $this->response->setJSON([
                'ok'          => true,
                'root'        => $rootKey,
                'slug'        => $slug,
                'path'        => $relPath,
                'ext'         => $ext,
                'kind'        => $kind,
                'result'      => $payload,
                'raw'         => $rawOut,
                'region'      => $meta['region']      ?? null,
                'regionGroup' => $meta['regionGroup'] ?? null,
                'selection'   => $this->selection->recall(['root' => $rootKey, 'slug' => $slug]),
            ]);
        }

        $this->selection->remember([
            'root'    => $rootKey,
            'slug'    => $slug,
            'relPath' => $relPath,
            'ext'     => $ext,
            'kind'    => $kind,
        ]);

        // HTML view fallback (inline rendering is normally in browse.php)
        return $this->response->setBody(view('mods/file', [
            'pageTitle' => "{$slug}",
            'root'      => $rootKey,
            'slug'      => $slug,
            'path'      => $relPath,
            'ext'       => $ext,
            'kind'      => $kind,
            'result'    => $payload,
            'raw'       => $bytes,
            'selection'   => $this->selection->recall(['root' => $rootKey, 'slug' => $slug]),
        ]));
    }

    // POST /mods/{Root}/{slug}/file/{path...} → save
    public function save(string $root, string $slug, string $relPath): ResponseInterface
    {
        try {
            $rootKey = $this->normalizeRoot($root);
            $relPath = ltrim($relPath, '/');
            $abs     = service('pathResolver')->join($rootKey, $slug . ($relPath ? '/' . $relPath : ''));

            $raw      = $this->request->getPost('data');       // optional raw text
            $dataJson = $this->request->getPost('data_json');  // optional JSON string

            $ext   = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
            $kind  = service('mimeGuesser')->kindFromExt($ext);
            $bytes = $this->composePayloadForWrite($kind, $raw, $dataJson);

            service('contentService')->write($abs, $bytes, true);

            $this->selection->remember([
                'root'    => $rootKey,
                'slug'    => $slug,
                'relPath' => $relPath,
                'ext'     => $ext,
                'kind'    => $kind,
            ]);

            return $this->response->setJSON([
                'ok'        => true,
                'root'      => $rootKey,
                'slug'      => $slug,
                'path'      => $abs,
                'rel'       => $relPath,
                'bytes'     => strlen($bytes),
                'ext'       => $ext,
                'kind'      => $kind,
                'from'      => is_string($dataJson) && $dataJson !== '' ? 'json' : 'raw',
                'selection' => $this->selection->recall(['root' => $rootKey, 'slug' => $slug]),
            ]);

        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /* ======================== HELPERS ======================== */

    private function wantsJson(): bool
    {
        if (strtolower((string)$this->request->getGet('format')) === 'json') return true;
        $accept = (string) ($this->request->getHeaderLine('Accept') ?? '');
        return str_contains($accept, 'application/json');
    }

    private function rootPath(string $rootKey): ?string
    {
        $paths = service('pathResolver');
        return match ($rootKey) {
            'MyMods'       => $paths->myMods(),
            'UnpackedMods' => $paths->unpackedMods(),
            'GameData'     => $paths->gameData(),
            default        => null,
        };
    }

    private function normalizeRoot(string $root): string
    {
        return match (strtolower($root)) {
            'mymods','mods'           => 'MyMods',
            'unpackedmods','allmods'  => 'UnpackedMods',
            'gamedata'                => 'GameData',
            default                   => $root,
        };
    }

    private function notFound(string $msg): ResponseInterface
    {
        if ($this->wantsJson()) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => $msg]);
        }
        return $this->response->setStatusCode(404)->setBody(
            view('mods/error', ['pageTitle' => 'Not Found', 'message' => $msg])
        );
    }

    private function countDirs(?string $base): int
    {
        if (!$base || !is_dir($base)) return 0;
        $n = 0;
        foreach (scandir($base) ?: [] as $name) {
            if ($name === '.' || $name === '..' || $name[0] === '.') continue;
            if (is_dir($base . DIRECTORY_SEPARATOR . $name)) $n++;
        }
        return $n;
    }

    private function tryTxtToJson(string $bytes): array
    {
        try {
            $json = TextParserHelper::txtToJson($bytes, true); // pretty
            $arr  = json_decode($json, true);
            return is_array($arr) ? $arr : ['raw' => $bytes];
        } catch (\Throwable $__) {
            return ['raw' => $bytes];
        }
    }

    private function tryXmlToJson(string $bytes): array
    {
        try {
            $json = XmlHelper::createJson($bytes, true); // pretty
            $arr  = json_decode($json, true);
            return is_array($arr) ? $arr : ['raw' => $bytes];
        } catch (\Throwable $__) {
            return ['raw' => $bytes];
        }
    }

    private function composePayloadForWrite(string $kind, ?string $raw, ?string $dataJson): string
    {
        // Prefer JSON when provided; fall back to raw
        if (is_string($dataJson) && $dataJson !== '') {
            switch ($kind) {
                case 'txt':
                case 'khn':
                    return TextParserHelper::jsonToTxt($dataJson);
                case 'xml': {
                    // JSON must be an object with a single root
                    $assoc = json_decode($dataJson, true);
                    if (!is_array($assoc)) throw new \InvalidArgumentException('data_json must be valid JSON');
                    if (count($assoc) === 1 && isset($assoc[array_key_first($assoc)])) {
                        $assoc = $assoc[array_key_first($assoc)];
                    }
                    return XmlHelper::createXML('contentList', $assoc)->saveXML();
                }
                case 'lsx':
                    // For LSX we currently accept raw XML; if JSON provided, store as JSON text
                    return (string) ($raw ?? $dataJson);
                default:
                    return (string) ($raw ?? $dataJson);
            }
        }
        return (string) ($raw ?? '');
    }
}

