<?php
namespace App\Services;

use Config\FileKinds;

class MimeGuesser
{
    public function __construct(private FileKinds $cfg) {}

    /** ext: lowercase, no dot */
    public function kindFromExt(string $ext): string
    {
        $ext = strtolower(ltrim($ext, '.'));
        return $this->cfg->kindOf($ext);
    }

    public function mimeFromExt(string $ext): string
    {
        $ext = strtolower(ltrim($ext, '.'));
        return $this->cfg->mimeOf($ext);
    }

    public function kindFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
        return $this->kindFromExt($ext);
    }

    public function mimeFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
        return $this->mimeFromExt($ext);
    }

    /** Supported extensions (omit wildcard) */
    public function supportedExts(): array
    {
        $map = $this->cfg->map ?? [];
        unset($map['*']);
        return array_keys($map);
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
}
?>
