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
        if ($fmt === 'html') return false;   // allow ?format=html for testing
        if ($fmt === 'json') return true;    // allow ?format=json to force JSON
        $accept = (string) ($req->getHeaderLine('Accept') ?? '');
        return str_contains($accept, 'application/json');
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
            return $res->setStatusCode($status)->setJSON($json);
        }
        return $res->setStatusCode($status)->setBody(view($view, $viewData));
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

}
?>
