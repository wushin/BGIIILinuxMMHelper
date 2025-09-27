<?php
namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Extension → { kind, mime }
 * Add/edit here without touching PHP logic.
 */
class FileKinds extends BaseConfig
{
    /** @var array<string, array{kind:string, mime:string}> */
    public array $map = [
        // text-like
        'txt'  => ['kind' => 'txt',  'mime' => 'text/plain'],
        'khn'  => ['kind' => 'khn',  'mime' => 'text/plain'],
        'xml'  => ['kind' => 'xml',  'mime' => 'application/xml'],
        'lsx'  => ['kind' => 'lsx',  'mime' => 'application/xml'],

        // images
        'png'  => ['kind' => 'image','mime' => 'image/png'],
        'jpg'  => ['kind' => 'image','mime' => 'image/jpeg'],
        'jpeg' => ['kind' => 'image','mime' => 'image/jpeg'],
        'gif'  => ['kind' => 'image','mime' => 'image/gif'],
        'webp' => ['kind' => 'image','mime' => 'image/webp'],
        'dds'  => ['kind' => 'image','mime' => 'application/octet-stream'],

        // fallback
        '*'    => ['kind' => 'unknown', 'mime' => 'application/octet-stream'],
    ];

    public function kindOf(string $ext): string
    {
        $ext = strtolower($ext);
        return $this->map[$ext]['kind'] ?? $this->map['*']['kind'];
    }

    public function mimeOf(string $ext): string
    {
        $ext = strtolower($ext);
        return $this->map[$ext]['mime'] ?? $this->map['*']['mime'];
    }
}
?>
