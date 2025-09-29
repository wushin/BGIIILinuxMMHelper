<?php

namespace App\Services;

use App\Config\Selection as SelectionConfig;
use CodeIgniter\Session\Session;
use InvalidArgumentException;

class SelectionService
{
    public function __construct(
        private SelectionConfig $config,
        private Session $session
    ) {}

    /**
     * Persist the last selected item.
     *
     * Expected keys in $selection:
     * - root (string)
     * - slug (string)
     * - relPath (string)
     * Optional:
     * - ext (string)
     * - kind (string)
     * - ts (ISO 8601 string); will be set if not provided
     *
     * @param array $selection
     * @param array $scope Optional override: ['root' => ..., 'slug' => ...]
     */
    public function remember(array $selection, array $scope = []): void
    {
        $normalized = $this->normalizeSelection($selection);

        $key = $this->buildKey($normalized, $scope);

        $this->session->set($key, $normalized);
    }

    /**
     * Retrieve the last selection or null.
     *
     * @param array $scope Optional override: ['root' => ..., 'slug' => ...]
     * @return array|null
     */
    public function recall(array $scope = []): ?array
    {
        $key = $this->buildKey(null, $scope);

        $value = $this->session->get($key);

        if (!is_array($value) || !$this->isSelectionValid($value)) {
            return null;
        }

        return $value;
    }

    /**
     * Clear the stored selection for the given scope.
     *
     * @param array $scope
     */
    public function clear(array $scope = []): void
    {
        $key = $this->buildKey(null, $scope);
        $this->session->remove($key);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function normalizeSelection(array $sel): array
    {
        $required = ['root', 'slug', 'relPath'];

        foreach ($required as $k) {
            if (!isset($sel[$k]) || !is_string($sel[$k]) || $sel[$k] === '') {
                throw new InvalidArgumentException("Selection missing/invalid: {$k}");
            }
        }

        // Enforce allowed roots if configured
        if (!empty($this->config->allowedRoots) && !in_array($sel['root'], $this->config->allowedRoots, true)) {
            throw new InvalidArgumentException('Selection root not allowed: ' . $sel['root']);
        }

        // Normalize relPath: strip leading slash and collapse "../"
        $rel = ltrim($sel['relPath'], '/');
        if (str_contains($rel, '..')) {
            throw new InvalidArgumentException('Unsafe relPath');
        }
        $sel['relPath'] = $rel;

        // Derive ext if not provided
        if ($this->config->includeExtInPayload && empty($sel['ext'])) {
            $sel['ext'] = pathinfo($rel, PATHINFO_EXTENSION) ?: '';
        }

        // kind is optional; you may set it at the call site.
        if ($this->config->includeKindInPayload && !isset($sel['kind'])) {
            $sel['kind'] = '';
        }

        // Timestamp
        $sel['ts'] = $sel['ts'] ?? gmdate('c');

        return $sel;
    }

    private function buildKey(?array $selection, array $scope): string
    {
        $mode = $this->config->scopeMode;

        $root = $scope['root'] ?? ($selection['root'] ?? null);
        $slug = $scope['slug'] ?? ($selection['slug'] ?? null);

        $base = rtrim($this->config->keyPrefix, '.') . '.';

        return match ($mode) {
            'perRoot' => $base . 'root.' . ($root ?? 'unknown'),
            'perMod'  => $base . 'mod.'  . ($root ?? 'unknown') . '.' . ($slug ?? 'unknown'),
            default   => $base . 'global',
        };
    }

    private function isSelectionValid(array $sel): bool
    {
        foreach (['root', 'slug', 'relPath'] as $k) {
            if (!isset($sel[$k]) || !is_string($sel[$k]) || $sel[$k] === '') {
                return false;
            }
        }
        if (!empty($this->config->allowedRoots) && !in_array($sel['root'], $this->config->allowedRoots, true)) {
            return false;
        }
        if (str_contains($sel['relPath'], '..')) {
            return false;
        }
        return true;
    }
}
?>
