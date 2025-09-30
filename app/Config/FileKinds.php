<?php
namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Single source of truth for extension → {kind, mime}.
 */
class FileKinds extends BaseConfig
{
    /** @var array<string, array{kind:string, mime:string}> */
    public array $map = [
        // text-like
        'txt'  => ['kind' => 'text', 'mime' => 'text/plain'],
        'khn'  => ['kind' => 'text', 'mime' => 'text/plain'],

        // xml family
        'xml'  => ['kind' => 'xml',  'mime' => 'application/xml'],
        'lsx'  => ['kind' => 'lsx',  'mime' => 'application/xml'],

        // images
        'png'  => ['kind' => 'image','mime' => 'image/png'],
        'dds'  => ['kind' => 'image','mime' => 'application/octet-stream'],

        // wildcard fallback
        '*'    => ['kind' => 'unknown', 'mime' => 'application/octet-stream'],
    ];

    public function kindOf(string $ext): string
    {
        $ext = strtolower(ltrim($ext, '.'));
        return $this->map[$ext]['kind'] ?? $this->map['*']['kind'];
    }

    public function mimeOf(string $ext): string
    {
        $ext = strtolower(ltrim($ext, '.'));
        return $this->map[$ext]['mime'] ?? $this->map['*']['mime'];
    }
}
?>
