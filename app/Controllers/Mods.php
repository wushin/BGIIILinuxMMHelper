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
use App\Services\PathResolver;

class Mods extends BaseController
{
    /** @var PathResolver */
    protected $pathResolver;

    /** @var \App\Services\SelectionService */
    protected $selection;

    public function __construct()
    {
        $this->selection = service('selection');
        $this->pathResolver = service('pathResolver');
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
                'configured' => service('directoryScanner')->isRootConfigured($key),
                'path'       => $abs ?: null,
                'count'      => service('directoryScanner')->countTopLevel($key),
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
        $rootKey = $this->pathResolver->canonicalKey($root);

        // Ensure the configured root exists/usable
        if (!service('directoryScanner')->rootUsable($rootKey)) {
            return service('responseBuilder')->notFound(
                $this->response,
                $this->request,
                "Root '{$rootKey}' is not configured or not accessible"
            );
        }

        // Special behavior: for MyMods show the combined "browse" page
        if ($rootKey === 'MyMods') {
            $myMods = $this->getMyModsList();  // array of directory names

            return service('responseBuilder')->ok(
                $this->response,
                $this->request,
                [
                    'ok'   => true,
                    'root' => $rootKey,
                    'mods' => $myMods,
                    'slug' => '',
                    'path' => '',
                    'tree' => [], // no mod selected yet
                ],
                'mods/browse',
                [
                    'pageTitle'    => 'MyMods — Directories',
                    'root'         => $rootKey,
                    'slug'         => '',
                    'path'         => '',
                    'tree'         => [],      // empty until a mod is chosen
                    'myMods'       => $myMods, // top card content
                    'baseKey'      => 'mods/' . $rootKey,
                    'selectedFile' => null,
                ]
            );
        }

        // Default behavior for other roots (GameData/UnpackedMods): directory list
        $mods = service('directoryScanner')->listTopLevel($rootKey);

        return service('responseBuilder')->ok(
            $this->response,
            $this->request,
            ['ok' => true, 'root' => $rootKey, 'mods' => $mods],
            'mods/mods',
            [
                'pageTitle' => "BG3LinuxHelper — {$rootKey}",
                'root'      => $rootKey,
                'mods'      => $mods,
            ]
        );
    }

    /**
     * Returns a sorted list of top-level directory names under MyMods (no recursion, no hidden).
     */
    private function getMyModsList(): array
    {
        try {
            return service('directoryScanner')->listTopLevel('MyMods');
        } catch (\Throwable $e) {
            return [];
        }
    }

    // GET /mods/{Root}/{slug} → full recursive tree (left column)
    public function mod(string $root, string $slug): ResponseInterface
    {
        $rootKey = $this->pathResolver->canonicalKey($root);

        // 404 if the mod folder doesn't exist under this root
        if (!service('directoryScanner')->modExists($rootKey, $slug)) {
            return service('responseBuilder')->notFound(
                $this->response,
                $this->request,
                "Mod '{$slug}' not found in '{$rootKey}'"
            );
        }

        $tree = service('directoryScanner')->tree($rootKey, $slug);
        $sel  = $this->selection->recall(['root' => $rootKey, 'slug' => $slug]);

        return service('responseBuilder')->ok(
            $this->response,
            $this->request,
            [
                'ok'        => true,
                'root'      => $rootKey,
                'slug'      => $slug,
                'path'      => '',
                'tree'      => $tree,
                'selection' => $sel,
            ],
            'mods/browse',
            [
                'pageTitle'    => $slug,
                'root'         => $rootKey,
                'slug'         => $slug,
                'path'         => '',
                'tree'         => $tree,
                'myMods'       => $rootKey === 'MyMods' ? $this->getMyModsList() : [],
                'baseKey'      => 'mods/' . $rootKey . '/' . $slug,
                'selectedFile' => $sel['relPath'] ?? null,
            ]
        );
    }

    // GET /mods/{Root}/{slug}/{path...} → directory (tree) or file (content)
    public function view(string $root, string $slug, string $relPath): ResponseInterface
    {
        $rootKey = $this->pathResolver->canonicalKey($root);
        $relPath = ltrim($relPath, '/');
        $abs = $this->pathResolver->join($rootKey, $slug, $relPath);

        if (service('directoryScanner')->isDirectory($rootKey, $slug, $relPath)) {
            $tree = service('directoryScanner')->treeFromAbsolute($abs, $relPath);

        $sel = $this->selection->recall(['root' => $rootKey, 'slug' => $slug]);

        return service('responseBuilder')->ok(
            $this->response,
            $this->request,
            [
                'ok'        => true,
                'root'      => $rootKey,
                'slug'      => $slug,
                'path'      => $relPath,
                'tree'      => $tree,
                'selection' => $sel,
            ],
            'mods/browse',
            [
                'pageTitle'    => "{$slug}/{$relPath}",
                'root'         => $rootKey,
                'slug'         => $slug,
                'path'         => $relPath,
                'tree'         => $tree,
                'myMods'       => $rootKey === 'MyMods' ? $this->getMyModsList() : [],
                'baseKey'      => 'mods/' . $rootKey . '/' . $slug,
                'selectedFile' => $sel['relPath'] ?? null,
            ]
        );

        }

        // File
        try {
            $file = service('contentService')->open($abs, [
                'rootKey' => $rootKey,
                'slug'    => $slug,
                'relPath' => $relPath,
            ]);
            $kind    = $file['kind']    ?? 'unknown';
            $ext     = $file['ext']     ?? '';
            $payload = $file['payload'] ?? null;
            $meta    = $file['meta']    ?? [];
            $rawOut  = $file['raw']     ?? null;   // see step 3 below
        } catch (\Throwable $e) {
            return service('responseBuilder')->notFound($this->response, $this->request, 'File not found');
        }

        // remember selection (unchanged)
        $this->selection->remember([
            'root'    => $rootKey,
            'slug'    => $slug,
            'relPath' => $relPath,
            'ext'     => $ext,
            'kind'    => $kind,
        ]);

        return service('responseBuilder')->ok(
            $this->response,
            $this->request,
            [
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
            ],
            'mods/file',
            [
                'pageTitle' => "{$slug}",
                'root'      => $rootKey,
                'slug'      => $slug,
                'path'      => $relPath,
                'ext'       => $ext,
                'kind'      => $kind,
                'result'    => $payload,
                'raw'       => $rawOut,
                'selection' => $this->selection->recall(['root' => $rootKey, 'slug' => $slug]),
            ]
        );
    }

    // POST /mods/{Root}/{slug}/file/{path...} → save
    public function save(string $root, string $slug, string $relPath): ResponseInterface
    {
        try {
            $rootKey = $this->pathResolver->canonicalKey($root);
            $relPath = ltrim($relPath, '/');
            $abs     = service('pathResolver')->join($rootKey, $slug, $relPath ?: null);

            $raw      = $this->request->getPost('data');       // optional raw text
            $dataJson = $this->request->getPost('data_json');  // optional JSON string

            $result = service('contentService')->save($abs, $raw, $dataJson); // new service method
            $ext    = $result['ext']  ?? strtolower(pathinfo($abs, PATHINFO_EXTENSION));
            $kind   = $result['kind'] ?? service('mimeGuesser')->kindFromExt($ext);

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

    public function saveSelection(): \CodeIgniter\HTTP\ResponseInterface
    {
        // ---- Parse payload robustly (JSON or form/query), without throwing on bad JSON
        $payload = null;
        $ct = strtolower($this->request->getHeaderLine('Content-Type') ?? '');
        if (str_contains($ct, 'application/json')) {
            try {
                $payload = $this->request->getJSON(true); // may throw; we catch
            } catch (\Throwable $__) {
                $payload = null;
            }
        }
        if (!is_array($payload) || $payload === []) $payload = $this->request->getPost();
        if (!is_array($payload) || $payload === []) $payload = $this->request->getGet();
        if (!is_array($payload)) $payload = [];

        // Allow nested { selection: {...} }
        if (isset($payload['selection']) && is_array($payload['selection'])) {
            $payload = $payload['selection'];
        }

        // ---- Accept both legacy {root, slug, relPath} and current {base, path}
        // Normalize fields
        $root    = trim((string)($payload['root']    ?? ''));
        $slug    = trim((string)($payload['slug']    ?? ''));
        $relPath = trim((string)($payload['relPath'] ?? ''));
        $base    = trim((string)($payload['base']    ?? '')); // e.g. "MyMods/<slug>"
        $path    = trim((string)($payload['path']    ?? '')); // e.g. "Mods/<slug>/.../file.ext"
        $ext     = trim((string)($payload['ext']     ?? ''));
        $kind    = trim((string)($payload['kind']    ?? ''));

        // If we weren’t given root/slug but did get base, split it
        if (($root === '' || $slug === '') && $base !== '') {
            // decode just in case it was URL-encoded
            $baseDecoded = rawurldecode($base);
            // split on first "/"
            $parts = explode('/', $baseDecoded, 2);
            $root  = $root !== '' ? $root : ($parts[0] ?? '');
            $slug  = $slug !== '' ? $slug : ($parts[1] ?? '');
        }

        // If relPath missing but we have "path", use it
        if ($relPath === '' && $path !== '') {
            $relPath = rawurldecode($path);
        }

        // Derive ext/kind if needed
        if ($ext === '' && $relPath !== '') {
            $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
        }
        if ($kind === '' && $ext !== '') {
            try {
                $kinds = service('mimeGuesser');
                $kind  = $kinds->kindFromExt($ext);
            } catch (\Throwable $__) {
                $kind = 'unknown';
            }
        }

        // Basic validation: need at least root and slug to store a per-mod selection
        if ($root === '' || $slug === '') {
            return $this->response->setStatusCode(400)->setJSON([
                'ok'    => false,
                'error' => 'Missing root/slug (provide {root,slug} or {base})',
            ]);
        }

        // Persist
        try {
            $this->selection->remember(array_filter([
                'root'    => $root,
                'slug'    => $slug,
                'relPath' => $relPath,
                'ext'     => $ext,
                'kind'    => $kind,
            ], fn($v) => $v !== '' && $v !== null));
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'ok'    => false,
                'error' => 'Failed to save selection',
            ]);
        }

        // Return the latest selection for this mod
        return $this->response->setJSON([
            'ok'        => true,
            'selection' => $this->selection->recall(['root' => $root, 'slug' => $slug]),
        ]);
    }


}
?>
