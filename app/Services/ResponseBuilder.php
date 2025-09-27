<?php
namespace App\Services;

use CodeIgniter\HTTP\ResponseInterface;

class ResponseBuilder
{
    public function __construct(private ResponseInterface $response) {}

    public function ok(array $data = [], array $meta = []): ResponseInterface
    {
        return $this->response->setJSON(['ok' => true, 'data' => $data, 'meta' => $meta]);
    }

    public function error(string $message, int $status = 400, array $meta = []): ResponseInterface
    {
        return $this->response->setStatusCode($status)
            ->setJSON(['ok' => false, 'error' => $message, 'meta' => $meta]);
    }
}
?>
