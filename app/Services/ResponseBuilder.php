<?php
namespace App\Services;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class ResponseBuilder
{
    public function __construct(private ResponseInterface $response) {}

    public function wantsJson(RequestInterface $req): bool
    {
        $fmt = strtolower((string) $req->getGet('format'));
        if ($fmt === 'html') return false;
        if ($fmt === 'json') return true;

        // XHR hint
        if (strtolower($req->getHeaderLine('X-Requested-With')) === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower((string) $req->getHeaderLine('Accept'));
        return strpos($accept, 'application/json') !== false;
    }

    public function ok(
        ResponseInterface $res,
        RequestInterface $req,
        array $json,
        string $view,
        array $viewData,
        int $status = 200
    ): ResponseInterface {
        if ($this->wantsJson($req)) {
            return $res->setStatusCode($status)->setContentType('application/json')->setJSON($json);
        }
        return $res->setStatusCode($status)->setContentType('text/html', 'utf-8')->setBody(view($view, $viewData));
    }

    public function notFound(
        ResponseInterface $res,
        RequestInterface $req,
        string $message,
        string $errorView = 'mods/error',
        array $extraViewData = []
    ): ResponseInterface {
        if ($this->wantsJson($req)) {
            return $res->setStatusCode(404)->setJSON(['ok' => false, 'error' => $message]);
        }
        return $res
            ->setStatusCode(404)
            ->setBody(view($errorView, array_merge(['pageTitle' => 'Not Found', 'message' => $message], $extraViewData)));
    }

    public function error(
        ResponseInterface $res,
        RequestInterface $req,
        \Throwable $e,
        ?int $forceStatus = null
    ): ResponseInterface {
        $mapper = service('exceptionMapper');
        $status = $forceStatus ?? $mapper->status($e);
        $type   = $mapper->shortType($e);

        if ($this->wantsJson($req)) {
            return $res
                ->setStatusCode($status)
                ->setJSON([
                    'ok'    => false,
                    'error' => $e->getMessage(),
                    'type'  => $type,
                    'code'  => $status,
                ]);
        }

        // HTML fallback uses your existing error view
        return $res
            ->setStatusCode($status)
            ->setBody(view('mods/error', [
                'pageTitle' => "{$status} {$type}",
                'message'   => $e->getMessage(),
            ]));
    }

}
?>
