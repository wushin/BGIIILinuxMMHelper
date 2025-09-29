<?php

namespace App\Services;

use App\Services\PathResolver;

/**
 * Centralized, safe directory listing & tree building.
 * - Controllers pass {rootKey, slug}; this service does the filesystem work.
 * - Caches hot results for a short TTL (configurable).
 * - Never throws on normal "missing" cases; returns empty lists/false instead.
 */
class DirectoryScanner
{
    private PathResolver $paths;

    // Tunables
    private int $ttlTop      = 30;      // seconds: cache for top-level dir lists
    private int $ttlTree     = 30;      // seconds: cache for directory trees
    private int $maxEntries  = 20000;   // safety cap for giant folders
    private bool $useCache   = true;

    public function __construct(?PathResolver $paths = null)
    {
        $this->paths = $paths ?? service('pathResolver');
    }

    /** Returns true if the root is configured (non-empty path). */
    public function isRootConfigured(string $rootKey): bool
    {
        try {
            $base = $this->paths->root($rootKey);
        } catch (\Throwable $e) {
            return false;
        }
        return is_string($base) && $base !== '';
    }

    /** Returns true if the root path exists and is readable. */
    public function rootUsable(string $rootKey): bool
    {
        try {
            $base = $this->paths->root($rootKey);
        } catch (\Throwable $e) {
            return false;
        }
        return $base !== '' && @is_dir($base) && @is_readable($base);
    }

    /**
     * List top-level directory names (slugs) under a root.
     * - Skips dot/hidden directories.
     * - Sorted natural, case-insensitive.
     */
    public function listTopLevel(string $rootKey): array
    {
        if (!$this->rootUsable($rootKey)) {
            return [];
        }

        $cacheKey = $this->makeCacheKey('ds_top', [$rootKey]);
        if ($this->useCache) {
            $cached = cache($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $base = $this->paths->root($rootKey);
        $out  = [];

        $dh = @opendir($base);
        if ($dh === false) {
            return [];
        }

        try {
            while (($name = readdir($dh)) !== false) {
                if ($name === '.' || $name === '..' || $name[0] === '.') {
                    continue;
                }
                // Only include directories
                $abs = $this->paths->join($rootKey, $name, null);
                if (@is_dir($abs)) {
                    $out[] = $name;
                    if (count($out) > $this->maxEntries) {
                        break; // safety cap
                    }
                }
            }
        } finally {
            @closedir($dh);
        }

        sort($out, SORT_NATURAL | SORT_FLAG_CASE);

        if ($this->useCache) {
            cache()->save($cacheKey, $out, $this->ttlTop);
        }

        return $out;
    }

    /** Count top-level directories under a root (uses listTopLevel, so cached). */
    public function countTopLevel(string $rootKey): int
    {
        return count($this->listTopLevel($rootKey));
    }

    /** True if {rootKey}/{slug} exists and is a directory. */
    public function modExists(string $rootKey, string $slug): bool
    {
        try {
            $abs = $this->paths->join($rootKey, $slug, null);
        } catch (\Throwable $e) {
            return false;
        }
        return @is_dir($abs);
    }

    /**
     * True if relPath inside a mod is a directory.
     * Empty relPath means the mod root.
     */
    public function isDirectory(string $rootKey, string $slug, string $relPath = ''): bool
    {
        try {
            $abs = $this->paths->join($rootKey, $slug, $relPath ?: null);
        } catch (\Throwable $e) {
            return false;
        }
        return @is_dir($abs);
    }

    /**
     * Build a directory tree for a given mod.
     * opts:
     *   - depth   (int|null) max recursion depth; null = unlimited
     *   - hidden  (bool) include dot files/dirs (default false)
     *   - exts    (string[]|null) only include files with these lowercase extensions (no dot)
     *   - cache   (bool) enable cache (default true)
     */
    public function tree(string $rootKey, string $slug, array $opts = []): array
    {
        $depth    = $opts['depth']   ?? null;
        $hidden   = (bool)($opts['hidden'] ?? false);
        $exts     = $opts['exts']    ?? null;
        $useCache = (bool)($opts['cache'] ?? $this->useCache);

        if (!$this->modExists($rootKey, $slug)) {
            // Non-existent mod: return an empty dir node to keep callers simple.
            return ['name' => $slug, 'type' => 'dir', 'rel' => '', 'children' => []];
        }

        $cacheKey = $this->makeCacheKey('ds_tree', [
            $rootKey,
            $slug,
            md5(json_encode([$depth, $hidden, $exts])),
        ]);
        if ($useCache) {
            $cached = cache($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $abs     = $this->paths->join($rootKey, $slug, null);
        $baseRel = '';

        $seen = 0;
        $node = $this->scanNode($abs, $baseRel, $depth, $hidden, $exts, $seen);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];

        if ($useCache) {
            cache()->save($cacheKey, $children, $this->ttlTree);
        }

        return $children;

    }

    // ---------- Internal recursive scan ----------

    private function scanNode(
        string $abs,
        string $rel,
        ?int $depth,
        bool $hidden,
        ?array $exts,
        int &$seen
    ): array {
        // Directory
        if (@is_dir($abs)) {
            $node = [
                'name'     => ($rel === '' ? '.' : basename($abs)),
                'isDir'    => true,
                'rel'      => $rel,      // e.g., "Mods/<slug>/Story/..."
                'children' => [],
            ];

            // stop at depth
            if ($depth !== null && $depth <= 0) {
                return $node;
            }
            $nextDepth = $depth !== null ? $depth - 1 : null;

            $dh = @opendir($abs);
            if ($dh === false) {
                return $node;
            }

            try {
                $dirs  = [];
                $files = [];

                while (($name = readdir($dh)) !== false) {
                    if ($name === '.' || $name === '..') {
                        continue;
                    }
                    if (!$hidden && $name[0] === '.') {
                        continue;
                    }

                    $childAbs = $abs . DIRECTORY_SEPARATOR . $name;
                    $childRel = ($rel === '' ? $name : ($rel . '/' . $name));

                    if (@is_dir($childAbs)) {
                        $dirs[] = $this->scanNode($childAbs, $childRel, $nextDepth, $hidden, $exts, $seen);
                    } else {
                        // extension filter (lowercased, no dot)
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if ($exts !== null) {
                            if ($ext === '' || !in_array($ext, $exts, true)) {
                                continue;
                            }
                        }
                        $files[] = [
                            'name'  => $name,
                            'isDir' => false,
                            'ext'   => $ext,
                            'rel'   => $childRel,
                        ];
                    }

                    $seen++;
                    if ($seen > $this->maxEntries) {
                        break; // safety cap
                    }
                }

                // sort: dirs first, then files; both natural, case-insensitive
                usort($dirs,  fn($a,$b) => strnatcasecmp($a['name'], $b['name']));
                usort($files, fn($a,$b) => strnatcasecmp($a['name'], $b['name']));

                $node['children'] = array_merge($dirs, $files);
                return $node;
            } finally {
                @closedir($dh);
            }
        }

        // File node
        return [
            'name'  => basename($abs),
            'isDir' => false,
            'ext'   => strtolower(pathinfo($abs, PATHINFO_EXTENSION)),
            'rel'   => $rel,
        ];
    }

    /** Build a CI4-safe cache key: only [A-Za-z0-9_-], joined by underscores. */
    private function makeCacheKey(string $prefix, array $parts = []): string
    {
        $san = [$prefix];
        foreach ($parts as $p) {
            $seg   = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $p);
            $san[] = trim($seg, '_');
        }
        $key = preg_replace('/_+/', '_', implode('_', $san));
        return $key;
    }

    // ---------- Optional: legacy wrapper (remove once all callers updated) ----------

    /**
     * Legacy signature: treeFromAbsolute($abs, $baseRel = '', $opts = []).
     * Prefer: tree($rootKey, $slug, $opts).
     */
    public function treeFromAbsolute(string $abs, string $baseRel = '', array $opts = []): array
    {
        try {
            $abs = rtrim($abs, DIRECTORY_SEPARATOR);
            foreach (['GameData', 'MyMods', 'UnpackedMods'] as $rk) {
                $root = rtrim($this->paths->root($rk), DIRECTORY_SEPARATOR);
                if ($root !== '' && str_starts_with($abs, $root . DIRECTORY_SEPARATOR)) {
                    // Extract first segment after root as slug
                    $tail  = substr($abs, strlen($root) + 1);
                    $parts = explode(DIRECTORY_SEPARATOR, $tail, 2);
                    $slug  = $parts[0] ?? '';
                    return $this->tree($rk, $slug, $opts);
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        // Fallback: best effort — return a *list* of nodes
        $seen = 0;
        $node = $this->scanNode(
            $abs,
            $baseRel,
            $opts['depth'] ?? null,
            (bool)($opts['hidden'] ?? false),
            $opts['exts'] ?? null,
            $seen
        );
        return is_array($node['children'] ?? null) ? $node['children'] : [$node];
    }
     
}
?>
