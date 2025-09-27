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
use App\Libraries\LocalizationScanner;

class Mods extends BaseController
{
    // -------- GET /mods
    public function list(): ResponseInterface
    {
        $paths = service('pathResolver');
        $roots = [
            'MyMods'       => $paths->myMods(),
            'UnpackedMods' => $paths->unpackedMods(),
            'GameData'     => $paths->gameData(),
        ];

        $summary = [];
        foreach ($roots as $name => $abs) {
            $summary[$name] = [
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

    // -------- GET /mods/{Root}
    public function listRoot(string $root): ResponseInterface
    {
        $rootKey = $this->normalizeRoot($root);
        $base    = $this->rootPath($rootKey);
        if (!$base || !is_dir($base)) {
            return $this->notFound("Root not configured: {$rootKey}");
        }

        $mods = $this->listDirs($base);

        if ($this->wantsJson()) {
            return $this->response->setJSON([
                'ok'   => true,
                'root' => $rootKey,
                'mods' => $mods,
            ]);
        }

        return $this->response->setBody(view('mods/mods', [
            'pageTitle' => "BG3LinuxHelper — {$rootKey}",
            'root'      => $rootKey,
            'mods'      => $mods,
        ]));
    }

    // -------- GET /mods/{Root}/{slug}  (left column = FULL TREE)
    public function mod(string $root, string $slug): ResponseInterface
    {
        $rootKey = $this->normalizeRoot($root);
        $abs     = service('pathResolver')->join($rootKey, $slug);
        if (!is_dir($abs)) {
            return $this->notFound('Mod not found');
        }

        $tree = $this->buildTree($abs, '');

        if ($this->wantsJson()) {
            return $this->response->setJSON([
                'ok'    => true,
                'root'  => $rootKey,
                'slug'  => $slug,
                'path'  => '',
                'tree'  => $tree,
            ]);
        }

        return $this->response->setBody(view('mods/browse', [
            'pageTitle' => $slug,
            'root'      => $rootKey,
            'slug'      => $slug,
            'path'      => '',
            'tree'      => $tree,
        ]));
    }

    // -------- GET /mods/{Root}/{slug}/{path...} (dir or file)
    public function view(string $root, string $slug, string $relPath): ResponseInterface
    {
        $rootKey = $this->normalizeRoot($root);
        $relPath = ltrim($relPath, '/');
        $abs     = service('pathResolver')->join($rootKey, $slug . '/' . $relPath);

        if (is_dir($abs)) {
            // Directory: return FULL tree for this subtree as well
            $tree = $this->buildTree($abs, $relPath);

            if ($this->wantsJson()) {
                return $this->response->setJSON([
                    'ok'    => true,
                    'root'  => $rootKey,
                    'slug'  => $slug,
                    'path'  => $relPath,
                    'tree'  => $tree,
                ]);
            }

            return $this->response->setBody(view('mods/browse', [
                'pageTitle' => "{$slug}/{$relPath}",
                'root'      => $rootKey,
                'slug'      => $slug,
                'path'      => $relPath,
                'tree'      => $tree,
            ]));
        }

        // File
        try {
            $bytes = service('contentService')->read($abs);
        } catch (\Throwable $e) {
            return $this->notFound('File not found');
        }

        $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $kind = $this->kindFromExt($ext);

        $payload = null;

        switch ($kind) {
            case 'txt':
            case 'khn':
                $payload = json_decode(TextParserHelper::txtToJson($bytes, true), true);
                break;

            case 'xml':
                $payload = json_decode(XmlHelper::createJson($bytes, true), true);
                break;

            case 'lsx':
                $scanner   = new LocalizationScanner(true, false);
                $langXmls  = $scanner->findLocalizationXmlsForMod(service('pathResolver')->join($rootKey, $slug));
                $handleMap = $scanner->buildHandleMapFromFiles($langXmls, true);
                $jsonTree  = $scanner->parseLsxWithHandleMapToJson($bytes, $handleMap);
                $payload   = json_decode($jsonTree, true);
                break;

            case 'image':
                $payload = [
                    'dataUri' => 'data:' . $this->mimeFromExt($ext) . ';base64,' . base64_encode($bytes),
                ];
                break;

            default:
                $payload = ['raw' => $bytes];
                break;
        }

        if ($this->wantsJson()) {
            // Include raw text for txt/xml/lsx too, so middle column can display source when desired.
            $rawOut = in_array($kind, ['txt','khn','xml','lsx','unknown'], true) ? $bytes : null;

            return $this->response->setJSON([
                'ok'     => true,
                'root'   => $rootKey,
                'slug'   => $slug,
                'path'   => $relPath,
                'ext'    => $ext,
                'kind'   => $kind,
                'result' => $payload,
                'raw'    => $rawOut,
            ]);
        }

        // HTML file view (unchanged)
        return $this->response->setBody(view('mods/file', [
            'pageTitle' => "{$slug}",
            'root'      => $rootKey,
            'slug'      => $slug,
            'path'      => $relPath,
            'ext'       => $ext,
            'kind'      => $kind,
            'result'    => $payload,
            'raw'       => $bytes,
        ]));
    }

    // -------- POST /mods/{Root}/{slug}/file/{path...}
    public function save(string $root, string $slug, string $relPath): ResponseInterface
    {
        try {
            $rootKey = $this->normalizeRoot($root);
            $relPath = ltrim($relPath, '/');
            $abs     = service('pathResolver')->join($rootKey, $slug . '/' . $relPath);

            $raw      = $this->request->getPost('data');       // optional raw text
            $dataJson = $this->request->getPost('data_json');  // optional JSON string
            $ext      = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
            $kind     = $this->kindFromExt($ext);

            $payload = (string) ($raw ?? '');

            if (is_string($dataJson) && $dataJson !== '') {
                switch ($kind) {
                    case 'txt':
                    case 'khn':
                        $payload = TextParserHelper::jsonToTxt($dataJson);
                        break;
                    case 'xml':
                        $assoc = json_decode($dataJson, true);
                        if (!is_array($assoc)) throw new \InvalidArgumentException('data_json must be valid JSON');
                        if (count($assoc) === 1 && isset($assoc[array_key_first($assoc)])) {
                            $assoc = $assoc[array_key_first($assoc)];
                        }
                        $payload = XmlHelper::createXML('contentList', $assoc)->saveXML();
                        break;
                    case 'lsx':
                        // Expect raw XML; if JSON provided, store it as JSON text
                        $payload = $raw ?? json_encode(json_decode($dataJson, true), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        break;
                    default:
                        $payload = (string) ($raw ?? $dataJson);
                }
            }

            service('contentService')->write($abs, $payload, true);

            return $this->response->setJSON([
                'ok'    => true,
                'path'  => $abs,
                'bytes' => strlen($payload),
                'ext'   => $ext,
                'kind'  => $kind,
                'from'  => is_string($dataJson) && $dataJson !== '' ? 'json' : 'raw',
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /* ==================== helpers ==================== */

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

    /**
     * Build a fully-recursive tree for the given absolute path.
     * Each node: { name, isDir, ext?, rel, children?[] }
     */
    private function buildTree(string $abs, string $baseRel): array
    {
        $entries = @scandir($abs) ?: [];
        $dirs = [];
        $files = [];

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') continue;

            $p   = $abs . DIRECTORY_SEPARATOR . $name;
            $rel = ltrim($baseRel . '/' . $name, '/');

            if (is_dir($p)) {
                $dirs[] = [
                    'name'     => $name,
                    'isDir'    => true,
                    'rel'      => $rel . '/',
                    'children' => $this->buildTree($p, $rel),
                ];
            } else {
                // Ignore *pure* .lsf files (binary), but DO NOT ignore .lsf.lsx
                if (preg_match('/\.lsf$/i', $name)) {
                    continue;
                }

                $files[] = [
                    'name'  => $name,
                    'isDir' => false,
                    'ext'   => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
                    'rel'   => $rel,
                ];
            }
        }

        usort($dirs,  fn($a,$b)=> strcasecmp($a['name'],$b['name']));
        usort($files, fn($a,$b)=> strcasecmp($a['name'],$b['name']));

        return array_merge($dirs, $files);
    }

    private function listDirs(string $base): array
    {
        $out = [];
        foreach (scandir($base) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $abs = $base . DIRECTORY_SEPARATOR . $name;
            if (is_dir($abs)) {
                $out[] = ['slug' => $name, 'mtime' => @filemtime($abs) ?: null];
            }
        }
        usort($out, fn($a, $b) => strcasecmp($a['slug'], $b['slug']));
        return $out;
    }

    private function kindFromExt(string $ext): string
    {
        return match ($ext) {
            'txt'        => 'txt',
            'khn'        => 'khn',
            'xml'        => 'xml',
            'lsx'        => 'lsx',
            'png','jpg','jpeg','gif','webp','dds' => 'image',
            default      => 'unknown',
        };
    }

    private function mimeFromExt(string $ext): string
    {
        return match ($ext) {
            'png'  => 'image/png',
            'jpg','jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'dds'  => 'application/octet-stream',
            default=> 'text/plain',
        };
    }

    private function countDirs(?string $base): int
    {
        if (!$base || !is_dir($base)) return 0;
        $n = 0;
        foreach (scandir($base) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            if (is_dir($base . DIRECTORY_SEPARATOR . $name)) $n++;
        }
        return $n;
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
}
?>
