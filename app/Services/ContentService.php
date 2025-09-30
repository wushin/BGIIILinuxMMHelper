<?php

namespace App\Services;

use App\Services\PathResolver;
use Config\BG3Paths;
use App\Services\MimeGuesser;
use App\Services\ImageTransformer;
use App\Libraries\LocalizationScanner;
use App\Libraries\LsxHelper;
use App\Helpers\TextParserHelper;
use App\Helpers\XmlHelper;
use App\Services\Parsers\ParserFactory;

/**
 * ContentService centralizes safe file reading/writing and basic directory listing.
 */
class ContentService
{
    public function __construct(
        private readonly PathResolver $paths,
        private readonly BG3Paths $cfg,
    ) {}

    /**
     * Read a file from disk after verifying it is inside an allowed root.
     * @throws \RuntimeException
     */
    public function read(string $absoluteOrRelativePath): string
    {
        $abs  = $this->paths->assertInAnyRoot($absoluteOrRelativePath);
        $data = @file_get_contents($abs);
        if ($data === false) {
            throw new \RuntimeException("Failed to read file: {$abs}");
        }
        return $data;
    }

    /**
     * Write to a file atomically with a .tmp + rename. Creates parent dirs if needed.
     * Optionally normalizes newlines to "\n".
     * @throws \RuntimeException
     */
    public function write(string $absoluteOrRelativePath, string $content, bool $normalizeLF = true): void
    {
        $abs = $this->paths->assertInAnyRoot($absoluteOrRelativePath);
        $dir = dirname($abs);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new \RuntimeException("Failed to create directory: {$dir}");
        }

        if ($normalizeLF) {
            $content = $this->normalizeNewlines($content);
        }

        $cfg = service('contentConfig');
        if (strlen($bytes) > $cfg->maxWriteBytes) {
            throw new \RuntimeException('File too large after write');
        }

        $tmp = $abs . '.tmp';
        if (@file_put_contents($tmp, $content) === false) {
            throw new \RuntimeException("Failed to write temp file: {$tmp}");
        }

        // Simple backup
        if (file_exists($abs)) {
            @copy($abs, $abs . '.bak');
        }

        if (!@rename($tmp, $abs)) {
            @unlink($tmp);
            throw new \RuntimeException("Atomic rename failed for: {$abs}");
        }
    }

    /**
     * List a directory (non-recursive), optional extension filter.
     * Returns: [['name'=>..., 'path'=>..., 'isDir'=>bool], ...]
     *
     * @param 'GameData'|'MyMods'|'UnpackedMods' $rootKey
     * @param string $relative
     * @param array<string>|null $exts
     * @throws \RuntimeException
     */
    public function listDir(string $rootKey, string $relative = '', ?array $exts = null): array
    {
        $base = $this->paths->join($rootKey, $relative);
        if (!is_dir($base)) {
            throw new \RuntimeException("Not a directory: {$base}");
        }

        $exts = $exts ?? ($this->cfg->allowedExtensions ?? []);
        $exts = array_map('strtolower', $exts);

        $out = [];
        $it  = new \FilesystemIterator($base, \FilesystemIterator::SKIP_DOTS);
        foreach ($it as $info) {
            /** @var \SplFileInfo $info */
            $isDir = $info->isDir();
            $path  = $info->getPathname();
            if (!$isDir && !empty($exts)) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if ($ext !== '' && !in_array($ext, $exts, true)) {
                    continue;
                }
            }
            $out[] = [
                'name'  => $info->getFilename(),
                'path'  => $path,
                'isDir' => $isDir,
            ];
        }

        usort($out, function ($a, $b) {
            // dirs first, then natural alpha
            if ($a['isDir'] !== $b['isDir']) {
                return $a['isDir'] ? -1 : 1;
            }
            return strnatcasecmp($a['name'], $b['name']);
        });

        return $out;
    }

    /** Normalize CRLF/CR to LF for consistent diffs & storage. */
    public function normalizeNewlines(string $s): string
    {
        $s = str_replace("\r\n", "\n", $s);
        $s = str_replace("\r", "\n", $s);
        return $s;
    }

    /**
     * Read & interpret a file and return a normalized payload for controllers/views.
     * Returns:
     * [
     *   'kind'    => string,           // txt|xml|lsx|image|unknown...
     *   'ext'     => string,           // file extension lowercased
     *   'payload' => array|null,       // parsed structure (text/xml/lsx/image)
     *   'raw'     => ?string,          // original bytes (policy-limited)
     *   'meta'    => array,            // region/group, etc for LSX
     * ]
     *
     * $ctx is optional but recommended for LSX enrichment:
     *   ['rootKey' => 'MyMods', 'slug' => '<mod-slug>', 'relPath' => '...']
     */
    public function open(string $abs, array $ctx = []): array
    {
        // ------------- read bytes -------------
        $bytes = $this->read($abs); // you already have this
        $ext   = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $kinds = service('mimeGuesser');
        $kind  = $kinds->kindFromExt($ext);
        $cfg = service('contentConfig');
        $tele = service('telemetry');
        $tele->bump('content.open', ['kind' => $kind, 'ext' => $ext]);
        // helper to cap raw inlined payloads in JSON
        $includeRaw = function (string $bytes) use ($cfg): ?string {
            return (strlen($bytes) <= $cfg->rawInclusionMaxBytes) ? $bytes : null;
        };
        log_message('info', 'Open {kind} file {abs}', [
            'kind' => $kind,
            'abs'  => $abs,
        ]);

        $payload = null;
        $rawOut  = null;
        $meta    = [];

        // ------------- helpers -------------
        $isTextLikeExts  = ['txt','khn','anc','ann','cln','clc'];
        $isTextLike      = in_array($ext, $isTextLikeExts, true) || in_array($kind, ['txt','khn','unknown'], true);

        // ------------- kind switch -------------
        switch ($kind) {
            case 'txt':
            case 'khn':
                try {
                    $json = \App\Helpers\TextParserHelper::txtToJson($bytes, true);
                    $arr  = json_decode($json, true);
                    $payload = is_array($arr) ? $arr : ['raw' => $bytes];
                } catch (\Throwable $__) {
                    $payload = ['raw' => $bytes];
                }
                $rawOut = $includeRaw($bytes);
                break;

            case 'xml':
                try {
                    $json = \App\Helpers\XmlHelper::createJson($bytes, true);
                    $arr  = json_decode($json, true);
                    $payload = is_array($arr) ? $arr : ['raw' => $bytes];
                } catch (\Throwable $__) {
                    $payload = ['raw' => $bytes];
                }
                $rawOut = $includeRaw($bytes);
                break;

            case 'lsx':
                try {
                    $ctx['abs']     = $abs;
                    $ctx['relPath'] = $relPath ?? ($ctx['relPath'] ?? null);

                    $lsx     = service('lsxService')->parse($bytes, $ctx);
                    $payload = $lsx['payload'] ?? ['raw' => $bytes];
                    $meta    = $lsx['meta']    ?? [];
                } catch (\Throwable $e) {
                    // Do NOT 404 just because enrichment failed; return raw + error note
                    $payload = ['raw' => $bytes];
                    $meta    = [
                        'region'      => null,
                        'regionGroup' => null,
                        'error'       => $e->getMessage(),
                    ];
                    log_message('error', 'LSX parse failed: {msg}', ['msg' => $e->getMessage()]);
                }
                $rawOut = $includeRaw($bytes);
                break;

            case 'image':
                if ($ext === 'dds') {
                    try {
                        $uri = service('imageTransformer')->ddsToPngDataUri($abs);
                        $payload = ['dataUri' => $uri, 'converted' => (bool)$uri, 'from' => 'dds'];
                    } catch (\Throwable $__) {
                        // fall back to raw base64 with generic mime
                        $payload = [
                            'dataUri'   => 'data:' . $kinds->mimeFromExt($ext) . ';base64,' . base64_encode($bytes),
                            'converted' => false,
                        ];
                    }
                } else {
                    $payload = [
                        'dataUri'   => 'data:' . $kinds->mimeFromExt($ext) . ';base64,' . base64_encode($bytes),
                        'converted' => false,
                    ];
                }
                // no rawOut for images by default
                break;

            default:
                // Unknown/binary: no parsing, but allow small raw echo for “text-like unknown”
                if ($isTextLike) {
                    $rawOut = $includeRaw($bytes);
                }
                $payload = ['raw' => $isTextLike ? $rawOut : null];
                break;
        }

        return [
            'kind'    => $kind,
            'ext'     => $ext,
            'payload' => $payload,
            'raw'     => $rawOut,
            'meta'    => $meta,
        ];
    }


    /**
     * Infer (rootKey, slug) from an absolute path by checking configured roots.
     * Returns [null, null] if not under a known root.
     */
    private function inferRootAndSlugFromAbsolute(string $abs): array
    {
        /** @var PathResolver $paths */
        $paths = service('pathResolver');

        foreach (['GameData', 'MyMods', 'UnpackedMods'] as $rk) {
            try {
                $root = rtrim($paths->root($rk), DIRECTORY_SEPARATOR);
            } catch (\Throwable $__) {
                continue;
            }
            if ($root !== '' && str_starts_with($abs, $root . DIRECTORY_SEPARATOR)) {
                $tail  = substr($abs, strlen($root) + 1);
                $parts = explode(DIRECTORY_SEPARATOR, $tail, 2);
                $slug  = $parts[0] ?? '';
                if ($slug !== '') {
                    return [$rk, $slug];
                }
            }
        }
        return [null, null];
    }

    public function save(string $abs, ?string $raw, ?string $json): array
    {
        // Normalize inputs
        $raw  = is_string($raw)  ? $raw  : null;
        $json = is_string($json) ? $json : null;

        // Figure out ext/kind
        $ext   = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $kinds = service('mimeGuesser');
        $kind  = $kinds->kindFromExt($ext);

        // Decide what bytes to write
        $bytesToWrite = null;
        $warnings     = [];

        $parser = service('parserFactory')->forPath($abs);

        // Delegate to parser: enforce policy first
        if (in_array($kind, ['image', 'unknown'], true) && $json !== null) {
            throw new \RuntimeException('JSON writes not allowed for this file type.');
        }

        $bytesToWrite = $parser->write($raw, $json, $abs);
        // Write atomically-ish
        $bytesWritten = $this->write($abs, $bytesToWrite, true);

        return [
            'ok'            => true,
            'path'          => $abs,
            'ext'           => $ext,
            'kind'          => $kind,
            'bytesWritten'  => $bytesWritten,
            'warnings'      => $warnings,
        ];
    }

}
?>
