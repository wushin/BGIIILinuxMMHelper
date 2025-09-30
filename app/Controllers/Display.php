<?php

namespace App\Controllers;

use Config\Services;
use CodeIgniter\HTTP\ResponseInterface;

class Display extends BaseController
{
    /**
     * GET /display/{Root}/{path...}
     * Root: GameData|MyMods|UnpackedMods
     * Streams file contents with an appropriate Content-Type.
     */
    public function view(string $root, string $path = ''): ResponseInterface
    {
        $paths   = Services::pathResolver();
        $content = Services::contentService();

        try {
            $rootKey = $paths->canonicalKey($root);

            $path    = ltrim($path, '/');
            if ($path === '') {
                return $this->response->setStatusCode(404)->setBody('Missing slug or file path.');
            }

            $firstSlashPos = strpos($path, '/');
            if ($firstSlashPos === false) {
                $slug    = $path;     // only a slug, no relPath
                $relPath = null;      // join() will point to the mod root (directory)
            } else {
                $slug    = substr($path, 0, $firstSlashPos);
                $relPath = substr($path, $firstSlashPos + 1); // may be multi-segment
            }

            $abs   = $paths->join($rootKey, $slug, $relPath);
            $bytes = $content->read($abs);
            $mime  = $this->detectMime($abs);

            if (str_starts_with($mime, 'text/') || $mime === 'application/xml') {
                return $this->response
                    ->setContentType($mime, 'utf-8')
                    ->setBody($bytes);
            }

            // binary
            return $this->response
                ->setHeader('Content-Disposition', 'inline; filename="' . basename($abs) . '"')
                ->setContentType($mime)
                ->setBody($bytes);

        } catch (\Throwable $e) {
            $code = $this->statusFromException($e, $path);
            return $this->response->setStatusCode($code)->setBody($e->getMessage());
        }
    }

    /**
     * POST /display/{Root}/{path...}
     * Saves file contents. Accepts form field "content" or raw request body.
     * Returns JSON { ok: true, path: "...", bytes: N } on success.
     */
    public function save(string $root, string $path = ''): ResponseInterface
    {
        $paths   = Services::pathResolver();
        $content = Services::contentService();

        try {
            // 1) Canonicalize root via PathResolver
            $rootKey = $paths->canonicalKey($root);

            // 2) Split "{slug}/{relPath...}" → ($slug, $relPath)
            $path = ltrim($path, '/');
            if ($path === '') {
                return $this->response->setStatusCode(404)->setJSON([
                    'ok'    => false,
                    'error' => 'Missing slug or file path.',
                ]);
            }

            $firstSlashPos = strpos($path, '/');
            if ($firstSlashPos === false) {
                $slug    = $path;   // only slug provided
                $relPath = null;    // join() will resolve to the mod root (dir)
            } else {
                $slug    = substr($path, 0, $firstSlashPos);
                $relPath = substr($path, $firstSlashPos + 1); // may have multiple segments
            }

            // 3) Safe absolute path
            $abs = $paths->join($rootKey, $slug, $relPath);

            // 4) Read body (form field "content" or raw body)
            $body = $this->request->getPost('content');
            if ($body === null) {
                $body = $this->request->getBody() ?? '';
            }

            $result = $content->saveEditor($abs, $body, null); // raw text save
            return $this->response->setJSON([
              'ok'    => true,
              'path'  => $result['path'],
              'bytes' => $result['bytes'], // <- actual bytes written
            ]);

        } catch (\Throwable $e) {
            $code = $this->statusFromException($e, $path);
            return $this->response->setStatusCode($code)->setJSON([
                'ok'    => false,
                'error' => $e->getMessage(),
            ]);
        }
    }


    /**
     * GET /browse/{Root}[/path...]
     * Lists a directory (dirs first), optionally filtered by known extensions.
     * Returns JSON array of {name, path, isDir}.
     */
    public function browse(string $root, string $path = ''): ResponseInterface
    {
        $paths   = Services::pathResolver();
        $content = Services::contentService();

        try {
            $rootKey = $this->normalizeRoot($root);
            $items   = $content->listDir($rootKey, $path, null);

            return $this->response->setJSON([
                'ok'      => true,
                'root'    => $rootKey,
                'cwd'     => $path,
                'results' => $items,
            ]);
        } catch (\Throwable $e) {
            $code = $this->statusFromException($e, $path);
            return $this->response->setStatusCode($code)->setJSON([
                'ok'    => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ---------- helpers ----------

    /** Map a filename to a reasonable MIME type. */
    private function detectMime(string $absPath): string
    {
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        return match ($ext) {
            'xml', 'lsx'      => 'application/xml',
            'txt', 'khn', ''  => 'text/plain',
            'json'            => 'application/json',
            'png'             => 'image/png',
            'jpg', 'jpeg'     => 'image/jpeg',
            'gif'             => 'image/gif',
            'webp'            => 'image/webp',
            'dds'             => 'application/octet-stream', // view/download raw unless you convert
            default           => 'text/plain',               // safest default for unknowns
        };
    }

    /** Normalize allowed root names. */
    private function normalizeRoot(string $root): string
    {
        $root = strtolower($root);
        return match ($root) {
            'gamedata'     => 'GameData',
            'mymods'       => 'MyMods',
            'unpackedmods' => 'UnpackedMods',
            default        => throw new \RuntimeException("Unknown root '{$root}'. Expected GameData|MyMods|UnpackedMods"),
        };
    }

    /** Derive an HTTP status code from an exception and path context. */
    private function statusFromException(\Throwable $e, string $path): int
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'not configured') || str_contains($msg, 'escape')) {
            return 400;
        }
        // Prefer exception type/message over hitting the filesystem here
        $code = (int) ($e->getCode() ?? 0);
        if ($code === 404) {
            return 404;
        }
        if (!empty($path)) {
            $m = strtolower($msg);
            if (str_contains($m, 'not found') || str_contains($m, 'no such file') || str_contains($m, 'does not exist')) {
                return 404;
            }
        }
        
        return 500;
    }
}
?>
