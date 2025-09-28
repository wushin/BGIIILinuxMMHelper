<?php

namespace App\Services;

use App\Libraries\LsxHelper;
use Config\Mongo as MongoCfg;
use Config\BG3Paths;
use MongoDB\Client as MongoClient;
use MongoDB\Collection;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use CodeIgniter\Config\Services;

/**
 * Indexes MyMods, UnpackedMods & GameData.
 * Filters: top (dirs), ext, lsx.region, lsx.group
 * Indexed-only (not filter-exposed): name, uuids, cuids
 * Ignores dotfiles anywhere in the path.
 */
class MongoIndexer {
    /** @var string[] */
    private array $allowedTextExts = ['txt','khn','xml','lsx','ann','anc','cln','clc'];
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

        // Ensure indexes (idempotent)
        $existing = [];
        foreach ($col->listIndexes() as $ix) { $existing[$ix->getName()] = true; }
        $ensure = function (string $name, array $keys, array $opts = []) use ($col, $existing) {
            if (!isset($existing[$name])) { $opts['name'] ??= $name; $col->createIndex($keys, $opts); }
        };

        $ensure('uniq_abs', ['abs' => 1], ['unique' => true]);

        // Filters
        $ensure('root',       ['root'        => 1]);
        $ensure('top',        ['top'         => 1]);
        $ensure('ext',        ['ext'         => 1]);
        $ensure('lsx_region', ['lsx.region'  => 1]);
        $ensure('lsx_group',  ['lsx.group'   => 1]);
        $ensure('mtime_desc', ['mtime'       => -1]);

        // Search helpers (not filter-exposed)
        $ensure('name',  ['name'  => 1]);
        $ensure('uuids', ['uuids' => 1]);
        $ensure('cuids', ['cuids' => 1]);

        // Text index
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

                $abs  = $file->getPathname();
                $name = $file->getBasename();

                // Ignore dotfiles anywhere in the path
                if ($name[0] === '.' || preg_match('~(?:^|[\\/])\\.[^\\/]+~', $abs)) continue;

                $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $size  = $file->getSize();
                $mtime = $file->getMTime();

                // Rel + top (first segment only when there IS a segment)
                $rel   = ltrim(str_replace($rootPath, '', $abs), DIRECTORY_SEPARATOR);
                $parts = explode(DIRECTORY_SEPARATOR, $rel);
                $top   = count($parts) > 1 ? ($parts[0] ?? '') : '';

                $slug  = strtolower(preg_replace('/[^a-zA-Z0-9_.-]+/', '-', $name));

                // Text-like?
                $isTextLike = in_array($ext, $this->allowedTextExts, true);
                $raw   = '';
                $uuids = [];
                $cuids = [];
                $names = [];
                $lsx   = ['region' => null, 'group' => null];

                if ($isTextLike) {
                    $raw = @file_get_contents($abs);
                    if ($raw === false) $raw = '';
                    if (strlen($raw) > 5_000_000) $raw = substr($raw, 0, 5_000_000);
                    // Skip likely-binary files by heuristic (NULs/control chars)
                    if ($this->isProbablyBinary($raw)) {
                        continue; // do not index; also keeps ext out of filters
                    }

                    if ($raw !== '') {
                        // UUIDs / ContentUUIDs
                        if (preg_match_all('/\b[0-9a-fA-F]{8}(?:-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}\b/', $raw, $m1)) {
                            $uuids = array_values(array_unique($m1[0]));
                        }
                        if (preg_match_all('/\bh[0-9a-fA-F]{8}(?:-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}\b/', $raw, $m2)) {
                            $cuids = array_values(array_unique($m2[0]));
                        }
                        // Generic "*name*" assignments for text index
                        if (preg_match_all('/\b([A-Za-z0-9_]*name[A-Za-z0-9_]*)\s*=\s*["\']([^"\']{1,200})["\']/i', $raw, $m3)) {
                            foreach ($m3[2] as $v) { $v = trim($v); if ($v !== '') $names[] = $v; }
                        }
                    }

                    // LSX region/group (derived) — fast "head" scan
                    if ($ext === 'lsx' || $ext === 'xml') {
                        $region = LsxHelper::detectRegionFromHead($raw, 16384); // ~16KB
                        $group  = LsxHelper::regionGroupFromId($region);
                        $lsx['region'] = $region;
                        $lsx['group']  = $group;
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
                    'top'   => $top,      // '' for root files
                    'name'  => $name,
                    'ext'   => $ext,
                    'size'  => $size,
                    'mtime' => $mtime,
                    'raw'   => $isTextLike ? $raw : '',
                    'uuids' => $uuids,    // indexed-only
                    'cuids' => $cuids,    // indexed-only
                    'names' => $names,
                ];
                if ($lsx['region'] !== null || $lsx['group'] !== null) {
                    $doc['lsx'] = $lsx; // { region: string|null, group: string|null }
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

    /**
     * Heuristic: treat as binary if contains many NULs or excessive control characters.
     */
    private function isProbablyBinary(string $buf): bool
    {
        if ($buf === '') return false;
        $len = strlen($buf);
        $sample = $len > 1024 ? substr($buf, 0, 1024) : $buf;
        $nul = substr_count($sample, "\0");
        if ($nul > max(1, (int)($len * 0.01))) return true; // >1% NULs in first KB
        // count non-printable (excluding common whitespace)
        $nonPrintable = 0;
        for ($i = 0, $L = strlen($sample); $i < $L; $i++) {
            $ord = ord($sample[$i]);
            if ($ord === 9 || $ord === 10 || $ord === 13) continue; // 	 
 
            if ($ord < 32 || $ord === 127) $nonPrintable++;
        }
        return $nonPrintable > 60; // heuristic threshold
    }
}
?>
