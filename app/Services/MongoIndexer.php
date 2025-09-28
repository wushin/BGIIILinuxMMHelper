<?php

namespace App\Services;

use Config\Mongo as MongoCfg;
use Config\BG3Paths;
use MongoDB\Client as MongoClient;
use MongoDB\Collection;

/**
 * Scans UnpackedMods & GameData into MongoDB.
 */
class MongoIndexer
{
    public function __construct(
        private readonly MongoClient  $client,
        private readonly PathResolver $paths,
        private readonly MimeGuesser  $kinds,
        private readonly MongoCfg     $cfg,
        private readonly BG3Paths     $bg3
    ) {}

    public function collection(): Collection
    {
        $col = $this->client
            ->selectDatabase($this->cfg->db)
            ->selectCollection($this->cfg->collection);

        if ($this->cfg->ensureIndexes) {
            // Idempotent; created once
            $indexes = $col->listIndexes();
            $names   = [];
            foreach ($indexes as $ix) { $names[] = (string)$ix->getName(); }

            $ensure = function(string $name, array $keys, array $opts = []) use ($col, $names) {
                if (!in_array($name, $names, true)) {
                    $col->createIndex($keys, array_merge(['name' => $name], $opts));
                }
            };

            $ensure('u_cat_rel',  ['category' => 1, 'rel' => 1], ['unique' => true]); // canonical uniqueness
            $ensure('i_category', ['category' => 1]);
            $ensure('i_extension',['extension'=> 1]);
            $ensure('i_abs',      ['abs' => 1]); // non-unique helper for lookups
            $ensure('root',    ['root' => 1]);
            $ensure('slug',    ['slug' => 1]);
            $ensure('name',    ['name' => 1]);
            $ensure('ext',     ['ext'  => 1]);
            $ensure('uuids',   ['uuids' => 1]);
            $ensure('cuids',   ['cuids' => 1]);
            $ensure('updated', ['mtime' => -1]);
        }

        return $col;
    }

    /**
     * Scan a single root.
     * @param 'GameData'|'UnpackedMods' $rootKey
     */
    public function scanRoot(string $rootKey, bool $rebuild = false, ?callable $progress = null): void
    {
        $base = match ($rootKey) {
            'GameData'     => $this->paths->gameData(),
            'UnpackedMods' => $this->paths->unpackedMods(),
            default        => null,
        };
        if (!$base || !is_dir($base)) {
            throw new \RuntimeException("Root not configured or missing: {$rootKey}");
        }

        $col = $this->collection();
        if ($rebuild) {
            $col->deleteMany(['root' => $rootKey]);
        }

        // Allowed text-ish for content extraction
        $textExts = ['xml','lsx','txt','khn','json','ini','md'];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $count = 0;
        foreach ($it as $info) {
            /** @var \SplFileInfo $info */
            if (!$info->isFile()) continue;

            $abs = $info->getPathname();

            // Ignore binary .lsf (we work with .lsx)
            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
            if ($ext === 'lsf') continue;
            if ($ext === 'loca') continue;

            // Derive fields
            $rel  = ltrim(substr($abs, strlen(rtrim($base, DIRECTORY_SEPARATOR)) + 1), DIRECTORY_SEPARATOR);
            $name = $info->getFilename();
            $kind = $this->kinds->kindFromExt($ext);
            $size = $info->getSize();
            $mtime= $info->getMTime();

            // slug = first path segment
            $parts = preg_split('#[\\/]+#', $rel) ?: [];
            $slug  = $parts[0] ?? null;

            // Hash only when size is not gigantic (sane default)
            $sha1 = null;
            if ($size <= 64 * 1024 * 1024) { // 64MB safety
                $sha1 = @sha1_file($abs) ?: null;
            }

            // Extract text (for text-ish files only, cap size)
            $text   = null;
            $uuids  = [];
            $cuids  = [];
            if (in_array($ext, $textExts, true) && $size <= 8 * 1024 * 1024) {
                $raw = @file_get_contents($abs);
                if ($raw !== false) {
                    // Normalize newlines for storage
                    $text = str_replace(["\r\n","\r"], "\n", $raw);
                    // Extract GUIDs and Content-UUIDs (leading 'h')
                    preg_match_all('/\b[0-9a-fA-F]{8}(?:-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}\b/', $text, $m1);
                    preg_match_all('/\bh[0-9a-fA-F]{8}(?:-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}\b/', $text, $m2);
                    $uuids = array_values(array_unique($m1[0] ?? []));
                    $cuids = array_values(array_unique($m2[0] ?? []));
                }
            }

            // Upsert by absolute path
            $doc = [
                'root'   => $rootKey,
                'slug'   => $slug,
                'abs'    => $abs,
                'rel'    => $rel,
                'dir'    => dirname($rel),
                'name'   => $name,
                'ext'    => $ext,
                'kind'   => $kind,
                'size'   => $size,
                'mtime'  => $mtime,
                'sha1'   => $sha1,
                'uuids'  => $uuids,
                'cuids'  => $cuids,
                'updatedAt' => new \MongoDB\BSON\UTCDateTime((int)$mtime * 1000),
            ];

            // Avoid storing huge text in Mongo for binaries/images
            if ($text !== null) {
                // Optional: truncate very large text blobs
                if (strlen($text) > 1024 * 1024) {
                    $text = substr($text, 0, 1024 * 1024);
                }
                $doc['text'] = $text;
            }

            $col->updateOne(
                ['abs' => $abs],
                ['$set' => $doc, '$setOnInsert' => ['createdAt' => new \MongoDB\BSON\UTCDateTime()]],
                ['upsert' => true]
            );

            $count++;
            if ($progress && ($count % 250 === 0)) {
                $progress($count);
            }
        }

        if ($progress) $progress($count);
    }

    /**
     * Convenience: scan both roots.
     */
    public function scanAll(bool $rebuild = false, ?callable $progress = null): void
    {
        $this->scanRoot('UnpackedMods', $rebuild, $progress);
        $this->scanRoot('GameData',     $rebuild, $progress);
    }
}
?>
