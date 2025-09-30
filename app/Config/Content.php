<?php
namespace Config;

use CodeIgniter\Config\BaseConfig;

class Content extends BaseConfig
{
    /** Max raw bytes we’ll inline in JSON payloads (else omit) */
    public int $rawInclusionMaxBytes = 512 * 1024; // 512KB

    /** Hard write size ceiling to protect disks */
    public int $maxWriteBytes = 25 * 1024 * 1024; // 25MB

    /** Normalize text newlines on write (to \n) */
    public bool $normalizeLF = true;

    /** Pretty-print XML on write */
    public bool $prettyXml = true;

    /** LSX parse cache TTL (seconds). 0 disables cache. */
    public int $lsxCacheTtl = 300; // 5 minutes
}
?>
