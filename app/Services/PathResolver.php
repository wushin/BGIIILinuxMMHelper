<?php

namespace App\Services;

use Config\BG3Paths;

/**
 * PathResolver centralizes all path resolution and containment checks
 * for GameData / MyMods / UnpackedMods directories.
 */
class PathResolver
{
    private string $gameData;
    private string $myMods;
    private string $unpackedMods;
    /** @var string[] */
    private array $roots;

    public function __construct(?BG3Paths $cfg = null)
    {
        $cfg ??= config(BG3Paths::class);

        // normalizeRoot() is whatever you already use to canonicalize/trim
        $this->gameData     = $this->normalizeRoot($cfg->GameData ?? '');
        $this->myMods       = $this->normalizeRoot($cfg->MyMods ?? '');
        $this->unpackedMods = $this->normalizeRoot($cfg->UnpackedMods ?? '');

        $this->roots = [
            'GameData'     => $this->gameData,
            'MyMods'       => $this->myMods,
            'UnpackedMods' => $this->unpackedMods,
        ];
    }

    public function gameData(): ?string     { return $this->gameData     ?: null; }
    public function myMods(): ?string       { return $this->myMods       ?: null; }
    public function unpackedMods(): ?string { return $this->unpackedMods ?: null; }

    /**
     * Join {rootKey}/{slug}/{relPath?} into a sanitized absolute path under the configured root.
     * - rootKey: 'GameData' | 'MyMods' | 'UnpackedMods'
     * - slug: a single directory segment (no slashes)
     * - relPath: optional relative path (may contain slashes)
     *
     * Guarantees:
     *  - Canonicalize via realpath on existing portions
     *  - Reject traversal and escapes outside the root
     *  - Allow non-existent leaf (useful for writes): parent must exist
     *
     * @throws \RuntimeException on invalid input or escape
     */
    public function join(string $rootKey, string $slug, ?string $relPath = null): string
    {
        $root = match ($rootKey) {
            'GameData'     => $this->gameData,
            'MyMods'       => $this->myMods,
            'UnpackedMods' => $this->unpackedMods,
            default        => null,
        };
        if (!$root) {
            throw new \RuntimeException("Root '{$rootKey}' is not configured.");
        }

        // slug must be a single safe segment
        $slug = $this->assertSafeSegment($slug, 'slug');

        // Normalize relPath (may be null/empty or contain slashes)
        $relPath = $relPath !== null ? ltrim($relPath, "\\/") : '';
        if ($relPath !== '') {
            $this->assertSafeRelative($relPath);
        }

        // Build candidate path
        $joined = $root . DIRECTORY_SEPARATOR . $slug;
        if ($relPath !== '') {
            $joined .= DIRECTORY_SEPARATOR . $relPath;
        }

        // Canonicalize: realpath on existing parent; allow non-existent leaf
        $parent = is_dir($joined) ? $joined : dirname($joined);
        $parentReal = realpath($parent);
        if ($parentReal === false) {
            throw new \RuntimeException("Parent path does not exist or is not accessible: {$parent}");
        }

        $baseReal = realpath($root);
        if ($baseReal === false) {
            throw new \RuntimeException("Configured root not accessible: {$rootKey}");
        }

        // Reconstruct absolute target under canonical parent
        if (is_dir($joined)) {
            $abs = $parentReal; // whole target exists and is dir
        } else {
            $abs = $parentReal . DIRECTORY_SEPARATOR . basename($joined);
        }

        // Final containment check (prevents escapes/symlink hops)
        return $this->assertContained($abs, $baseReal);
    }


    /**
     * Verify a path is inside any allowed root. Returns absolute path or throws.
     */
    public function assertInAnyRoot(string $path): string
    {
        $abs = $this->absPath($path, mustExist: false);
        foreach ($this->roots as $root) {
            if ($this->isContained($abs, $root)) {
                return $abs;
            }
        }
        throw new \RuntimeException("Path not within an allowed root: {$path}");
    }

    // ---- internals --------------------------------------------------------

    private function readEnvOrDefault(string $envName, ?string $fallback): ?string
    {
        $v = getenv($envName);
        if ($v === false || $v === '') {
            $v = $fallback;
        }
        return $v ?: null;
    }

    private function normalizeRoot(?string $path): string
    {
        if (!$path) return '';
        $abs = $this->absPath($path, mustExist: false);
        return rtrim($abs, DIRECTORY_SEPARATOR);
    }

    private function absPath(string $path, bool $mustExist = true): string
    {
        if (file_exists($path)) {
            $rp = realpath($path);
            if ($rp === false) {
                throw new \RuntimeException("Failed to resolve path: {$path}");
            }
            return $rp;
        }
        // Non-existent: make absolute against cwd & normalize slashes
        $abs = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : (getcwd() . DIRECTORY_SEPARATOR . $path);
        $abs = preg_replace('#[\\/]+#', DIRECTORY_SEPARATOR, $abs);
        if ($mustExist) {
            throw new \RuntimeException("Path does not exist: {$abs}");
        }
        return $abs;
    }

    private function isContained(string $path, string $root): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($path, $root);
    }

    private function assertContained(string $path, string $root): string
    {
        $abs = $this->absPath($path, mustExist: false);
        if (!$this->isContained($abs, $root)) {
            throw new \RuntimeException("Attempted path escape. Root: {$root}; Path: {$abs}");
        }
        return $abs;
    }

    public function canonicalKey(string $root): string
    {
        // Exact match
        if (isset($this->roots[$root])) {
            return $root;
        }
        // Case-insensitive match
        foreach ($this->roots as $key => $_) {
            if (strcasecmp($key, $root) === 0) {
                return $key;
            }
        }
        throw new \RuntimeException("Unknown root '{$root}'");
    }

    public function root(string $rootKey): string
    {
        if (!isset($this->roots[$rootKey]) || $this->roots[$rootKey] === '') {
            throw new \RuntimeException("Unknown root '{$rootKey}'");
        }
        return $this->roots[$rootKey];
    }

    private function assertSafeSegment(string $seg, string $label): string
    {
        if ($seg === '' || $seg === '.' || $seg === '..') {
            throw new \RuntimeException("Invalid {$label}");
        }
        if (strpbrk($seg, "/\\") !== false) {
            throw new \RuntimeException("Invalid {$label} (must be a single path segment)");
        }
        return $seg;
    }

    private function assertSafeRelative(string $rel): void
    {
        // Forbid .. traversal and Windows drive prefixes
        if (preg_match('#(^|[\\/])\.\.([\\/]|$)#', $rel)) {
            throw new \RuntimeException('Relative path traversal is not allowed');
        }
        if (preg_match('#^[A-Za-z]:[\\/]#', $rel)) {
            throw new \RuntimeException('Absolute drive paths are not allowed');
        }
    }

    /**
     * Decompose an absolute path into {rootKey, slug, relPath}.
     * Throws if the path is not contained in any configured root.
     *
     * @return array{rootKey:string, slug:string, relPath:string}
     */
    public function decompose(string $abs): array
    {
        $abs = $this->absPath($abs, mustExist: false);

        foreach ($this->roots as $rootKey => $root) {
            $baseReal = $this->absPath($root, mustExist: true);
            if ($this->isContained($abs, $baseReal)) {
                $rest = ltrim(substr($abs, strlen(rtrim($baseReal, DIRECTORY_SEPARATOR))), DIRECTORY_SEPARATOR);
                if ($rest === '') {
                    // path equals root dir; no slug/rel
                    return ['rootKey' => $rootKey, 'slug' => '', 'relPath' => ''];
                }
                // first segment is slug; remainder (if any) is relPath
                $parts   = explode(DIRECTORY_SEPARATOR, $rest, 3);
                $slug    = $parts[0] ?? '';
                // support {root}/{slug} and {root}/{slug}/{rel}
                $relPath = $parts[2] ?? ($parts[1] ?? '');
                return ['rootKey' => $rootKey, 'slug' => $slug, 'relPath' => $relPath];
            }
        }

        throw new \RuntimeException("Path not within an allowed root: {$abs}");
    }

}
?>
