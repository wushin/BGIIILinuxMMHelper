<?php

namespace App\Services;

use Config\Mongo as MongoCfg;
use Config\BG3Paths;
use MongoDB\Client as MongoClient;
use MongoDB\Collection;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use XMLReader;
use Throwable;
use CodeIgniter\Config\Services;

/**
 * Indexes MyMods, UnpackedMods & GameData into MongoDB.
 *
 * Filters supported by the API (and thus what we index for):
 * - Directories:  first-level subdir under each root => "top"
 * - Extensions:   lowercase extension (no dot)       => "ext"
 * - LSX Regions:  lsx.regions[]
 * - LSX Groups:   lsx.groupings[]
 *
 * We also store 'raw' (trimmed) for full-text and snippet display.
 * Hidden files (dotfiles anywhere in the path) are ignored.
 */
class MongoIndexer
{
    private LoggerInterface $log;

    public function __construct(
        private readonly MongoClient $client,
        private readonly BG3Paths    $paths,
        private readonly MongoCfg    $mongoCfg,
    ) {
        $this->log = Services::logger();
    }

    private function col(): Collection
    {
        $db  = $this->client->selectDatabase($this->mongoCfg->db);
        $col = $db->selectCollection($this->mongoCfg->collection ?? 'bg3_files');

        // Indexes (idempotent)
        $existing = [];
        foreach ($col->listIndexes() as $ix) {
            $existing[$ix->getName()] = true;
        }
        $ensure = function (string $name, array $keys, array $opts = []) use ($col, $existing) {
            if (!isset($existing[$name])) {
                $opts['name'] ??= $name;
                $col->createIndex($keys, $opts);
            }
        };

        $ensure('uniq_abs',   ['abs' => 1], ['unique' => true]);
        $ensure('root',       ['root' => 1]);
        $ensure('top',        ['top'  => 1]);
        $ensure('ext',        ['ext'  => 1]);
        $ensure('mtime_desc', ['mtime' => -1]);

        // LSX peek fields to support filters
        $ensure('lsx_regions',   ['lsx.regions'   => 1]);
        $ensure('lsx_groupings', ['lsx.groupings' => 1]);

        // Full-text (raw + names) — 1 text index per collection
        $ensure('text_all', ['raw' => 'text', 'names' => 'text']);

        return $col;
    }

    /**
     * @param 'MyMods'|'UnpackedMods'|'GameData' $rootKey
     */
    public function scanRoot(string $rootKey, bool $rebuild = false, ?callable $progress = null): void
    {
        $valid = ['MyMods', 'UnpackedMods', 'GameData'];
        if (!in_array($rootKey, $valid, true)) {
            throw new \InvalidArgumentException("Invalid rootKey: {$rootKey}");
        }

        $rootPath = rtrim($this->paths->{$rootKey} ?? '', '/');
        if (!$rootPath || !is_dir($rootPath)) {
            $this->log->warning('[MongoIndexer] Root path for {root} not found: {path}', ['root' => $rootKey, 'path' => $rootPath]);
            return;
        }

        $col = $this->col();

        if ($rebuild) {
            $res = $col->deleteMany(['root' => $rootKey]);
            $this->log->info('[MongoIndexer] Rebuild: removed {n} docs for {root}', [
                'n' => $res->getDeletedCount(), 'root' => $rootKey
            ]);
        }

        $this->log->info('[MongoIndexer] Scanning {root} at {path}…', ['root' => $rootKey, 'path' => $rootPath]);

        $count = 0;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            try {
                if (!$file->isFile()) continue;

                $abs   = $file->getPathname();
                $name  = $file->getBasename();

                // --- Ignore dotfiles anywhere in the path (".anything")
                // basename starts with '.' OR any path segment starts with '.'
                if ($name[0] === '.' || preg_match('~(?:^|[\\/])\\.[^\\/]+~', $abs)) {
                    continue;
                }

                $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $size  = $file->getSize();
                $mtime = $file->getMTime();

                // Rel path & top directory
                $rel   = ltrim(str_replace($rootPath, '', $abs), DIRECTORY_SEPARATOR);
                $parts = explode(DIRECTORY_SEPARATOR, $rel);
                $top   = $parts[0] ?? '';
                $slug  = strtolower(preg_replace('/[^a-zA-Z0-9_.-]+/', '-', $name));

                // Only attempt full-text on text-like files
                $isTextLike = in_array($ext, ['txt','khn','xml','lsx','json','ini','cfg','csv','yaml','yml']);
                $raw   = '';
                $uuids = [];
                $cuids = [];
                $names = [];
                $lsx   = ['regions' => [], 'groupings' => [], 'nodeNames' => []];

                if ($isTextLike) {
                    $raw = @file_get_contents($abs);
                    if ($raw === false) $raw = '';
                    if (strlen($raw) > 5_000_000) $raw = substr($raw, 0, 5_000_000);

                    if ($raw !== '') {
                        // GUIDs / ContentUUIDs (for completeness; not used as filters now)
                        preg_match_all('/\b[0-9a-fA-F]{8}(?:-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}\b/', $raw, $m1);
                        $uuids = array_values(array_unique($m1[0] ?? []));
                        preg_match_all('/\bh[0-9a-fA-F]{8}(?:-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}\b/', $raw, $m2);
                        $cuids = array_values(array_unique($m2[0] ?? []));

                        // Any "Name" fields for text scoring/snippets
                        preg_match_all('/\b([A-Za-z0-9_]*name[A-Za-z0-9_]*)\s*=\s*["\']([^"\']{1,200})["\']/i', $raw, $m3);
                        if (!empty($m3[2])) {
                            foreach ($m3[2] as $v) { $v = trim($v); if ($v !== '') $names[] = $v; }
                        }
                    }

                    // LSX/XML peek via XMLReader — collect regions & groupings for filters
                    if ($ext === 'lsx' || $ext === 'xml') {
                        try {
                            $reader = new XMLReader();
                            if (@$reader->open($abs, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOWARNING | LIBXML_NOERROR)) {
                                while ($reader->read()) {
                                    if ($reader->nodeType !== XMLReader::ELEMENT) continue;
                                    $tag = strtolower($reader->name);

                                    if ($tag === 'region') {
                                        $id = $reader->getAttribute('id') ?? $reader->getAttribute('name');
                                        if ($id) $lsx['regions'][] = $id;
                                    }
                                    if (in_array($tag, ['group','grouping','category','folder'], true)) {
                                        $gid = $reader->getAttribute('id') ?? $reader->getAttribute('name');
                                        if ($gid) $lsx['groupings'][] = $gid;
                                    }

                                    // Also pick up any *name* attributes for scoring/snippets
                                    for ($i = 0; $i < $reader->attributeCount; $i++) {
                                        $reader->moveToAttributeNo($i);
                                        $an = strtolower($reader->name);
                                        $av = trim((string) $reader->value);
                                        if ($av === '') continue;
                                        if (str_contains($an, 'name')) $names[] = $av;
                                    }
                                    $reader->moveToElement();
                                }
                                $reader->close();
                            }
                        } catch (Throwable $e) {
                            $this->log->debug('[MongoIndexer] XMLReader failed on {file}: {msg}', [
                                'file' => $abs, 'msg' => $e->getMessage(),
                            ]);
                        }

                        // Normalize peek arrays
                        $lsx['regions']   = array_values(array_unique(array_filter($lsx['regions'])));
                        $lsx['groupings'] = array_values(array_unique(array_filter($lsx['groupings'])));
                    }
                }

                $names = array_values(array_unique(array_filter($names, static fn($s) => $s !== '')));
                $uuids = array_values(array_unique($uuids));
                $cuids = array_values(array_unique($cuids));

                $doc = [
                    'root'  => $rootKey,
                    'slug'  => $slug,
                    'abs'   => $abs,
                    'rel'   => $rel,
                    'top'   => $top,
                    'name'  => $name,
                    'ext'   => $ext,
                    'size'  => $size,
                    'mtime' => $mtime,
                    'raw'   => $isTextLike ? $raw : '',
                    'uuids' => $uuids,
                    'cuids' => $cuids,
                    'names' => $names,
                ];
                if ($ext === 'lsx' || $ext === 'xml') {
                    $doc['lsx'] = $lsx;
                }

                $this->col()->updateOne(['abs' => $abs], ['$set' => $doc], ['upsert' => true]);

                $count++;
                if ($progress && ($count % 100 === 0)) $progress($count);
                if (($count % 1000) === 0) {
                    $this->log->info('[MongoIndexer] {root} indexed {n} files…', ['root' => $rootKey, 'n' => $count]);
                }
            } catch (Throwable $e) {
                $this->log->error('[MongoIndexer] Failed on {file}: {msg}', [
                    'file' => (string) ($file->getPathname() ?? ''), 'msg'  => $e->getMessage(),
                ]);
            }
        }

        $this->log->info('[MongoIndexer] Done {root}. Files indexed/updated: {n}', ['root' => $rootKey, 'n' => $count]);
        if ($progress) $progress($count);
    }

    public function scanAll(bool $rebuild = false, ?callable $progress = null): void
    {
        $this->scanRoot('MyMods',       $rebuild, $progress);
        $this->scanRoot('UnpackedMods', $rebuild, $progress);
        $this->scanRoot('GameData',     $rebuild, $progress);
    }
}
?>
