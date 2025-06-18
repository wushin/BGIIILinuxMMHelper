<?php
// app/Controllers/SearchMongo.php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use MongoDB\Client as MongoClient;
use Throwable;

/**
 * REST endpoint: /search/mongo?q=term&page=n
 * Returns paginated, full‑text Mongo results (keys + values) with highlighting handled client‑side.
 */
class SearchMongo extends ResourceController
{
    protected $format = 'json';   // respond() returns JSON

    public function index()
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $query = $this->request->getGet('q') ?? '';
        $page  = max(1, (int) $this->request->getGet('page'));
        $per   = 5;

        log_message('info', "Mongo search request: '{$query}' (page {$page})");

        try {
            $client      = new MongoClient('mongodb://bg3mmh-mongo:27017');
            $collection  = $client->bg3mmh->files;
            $filter = $query === '' ? [] : [
                '$or' => [
                    ['raw'  => ['$regex' => $query, '$options' => 'i']],
                ]
            ];

            $opts = [
                'skip'       => ($page - 1) * $per,
                'limit'      => $per,
                'projection' => [
                'filepath' => 1,
                'raw'      => 1,
                ],
                'sort' => ['filepath' => 1], // fallback stable sort
            ];

            unset($opts['projection']['score']);

            $cursor  = $collection->find($filter, $opts);
            $total   = $collection->countDocuments($filter);
            $results = iterator_to_array($cursor, false);

            return $this->respond([
                'results'  => $results,
                'total'    => $total,
                'page'     => $page,
                'perPage'  => $per,
            ]);

        } catch (Throwable $e) {
            log_message('error', 'Mongo search failed: ' . $e->getMessage());
            return $this->failServerError('Mongo search failed.');
        }
    }
}
?>
