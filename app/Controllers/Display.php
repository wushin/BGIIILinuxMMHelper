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
            $rootKey = $this->normalizeRoot($root);
            $abs     = $paths->join($rootKey, $path);
            $bytes   = $content->read($abs);
            $mime    = $this->detectMime($abs);

            // text-ish types with UTF-8 charset
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
            // 400 for invalid root/escape attempts, 404 if path looks like a file that doesn't exist, else 500
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
            $rootKey = $this->normalizeRoot($root);
            $abs     = $paths->join($rootKey, $path);

            $body = $this->request->getPost('content');
            if ($body === null) {
                $body = $this->request->getBody() ?? '';
            }

            $content->write($abs, $body, true);

            return $this->response->setJSON([
                'ok'   => true,
                'path' => $abs,
                'bytes'=> strlen($body),
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
        if (!empty($path) && !file_exists($path)) {
            return 404;
        }
        return 500;
    }
}
?>
