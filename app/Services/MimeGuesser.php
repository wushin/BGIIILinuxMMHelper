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
}
?>
