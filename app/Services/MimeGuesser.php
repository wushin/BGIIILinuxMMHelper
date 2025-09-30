<?php
namespace App\Services;

use Config\FileKinds;

/**
 * Thin wrapper around Config\FileKinds.
 * No internal maps here — config is the single source of truth.
 */
class MimeGuesser
{
    public function __construct(private FileKinds $cfg) {}

    public function kindFromExt(string $ext): string
    {
        return $this->cfg->kindOf($ext);
    }

    public function mimeFromExt(string $ext): string
    {
        return $this->cfg->mimeOf($ext);
    }

    public function kindFromPath(string $path): string
    {
        return $this->kindFromExt(pathinfo($path, PATHINFO_EXTENSION) ?: '');
    }
    public function mimeFromPath(string $path): string
    {
        return $this->mimeFromExt(pathinfo($path, PATHINFO_EXTENSION) ?: '');
    }
    public function isTextLikeMime(string $mime): bool
    {
        $m = strtolower($mime);
        return str_starts_with($m, 'text/') || in_array($m, ['application/xml', 'application/json'], true);
    }
    public function isEditableKind(string $kind): bool
    {
        return in_array($kind, ['text','xml','lsx'], true);
    }
    public function supportedExts(): array
    {
        return array_values(array_filter(array_keys($this->cfg->map), fn($k) => $k !== '*'));
    }

}
?>
