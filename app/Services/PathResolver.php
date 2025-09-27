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

        $this->gameData     = $this->normalizeRoot($this->readEnvOrDefault($cfg->envGameData, $cfg->defaultGameData));
        $this->myMods       = $this->normalizeRoot($this->readEnvOrDefault($cfg->envMyMods, $cfg->defaultMyMods));
        $this->unpackedMods = $this->normalizeRoot($this->readEnvOrDefault($cfg->envUnpackedMods, $cfg->defaultUnpackedMods));

        $this->roots = array_values(array_filter([$this->gameData, $this->myMods, $this->unpackedMods]));
    }

    public function gameData(): ?string     { return $this->gameData     ?: null; }
    public function myMods(): ?string       { return $this->myMods       ?: null; }
    public function unpackedMods(): ?string { return $this->unpackedMods ?: null; }

    /**
     * Join a relative path to one of the known roots and return a sanitized, absolute path.
     * @param 'GameData'|'MyMods'|'UnpackedMods' $rootKey
     * @throws \RuntimeException
     */
    public function join(string $rootKey, string $relative): string
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
        $relative = ltrim($relative, "\\/"); // enforce “relative”
        $joined   = preg_replace('#[\\/]+#', DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR . $relative);
        return $this->assertContained($joined, $root);
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
}
?>
