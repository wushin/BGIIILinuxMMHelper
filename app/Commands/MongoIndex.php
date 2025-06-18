<?php
namespace App\Commands;

ini_set('memory_limit', '-1');
set_time_limit(0);

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use MongoDB\Client as MongoClient;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class MongoIndex extends BaseCommand
{
    protected $group       = 'bg3mmh';
    protected $name        = 'mongoindex:scan';
    protected $description = 'Scans and indexes game data files into MongoDB.';
    protected $usage       = 'mongoindex:scan [--sync] [--rebuild]';

    protected $options = [
        '--sync'    => 'Only update changed or new files.',
        '--rebuild' => 'Clear and re-import everything.',
    ];

    public function run(array $params)
    {
        helper(['filesystem']);

        $gameDataPath = env('bg3LinuxHelper.GameData');
        if (!$gameDataPath || !is_dir($gameDataPath)) {
            log_message('error', 'Invalid or missing GameData path: ' . $gameDataPath);
            CLI::error('Invalid GameData path.');
            return;
        }

        $syncMode = in_array('--sync', $params);
        $rebuildMode = in_array('--rebuild', $params);

        try {
            $mongo = new MongoClient('mongodb://bg3mmh-mongo:27017');
            $collection = $mongo->bg3mmh->files;

            if ($rebuildMode) {
                $collection->drop();
                log_message('info', 'MongoDB collection dropped for rebuild.');
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($gameDataPath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                $filePath = $fileInfo->getPathname();
                $extension = strtolower($fileInfo->getExtension());

                // Limit to known formats
                if (!in_array($extension, ['xml', 'lsx', 'txt', 'khn'])) {
                    continue;
                }

                $hash = md5_file($filePath);
                $existing = $collection->findOne(['filepath' => $filePath]);

                if ($syncMode && $existing && $existing['hash'] === $hash) {
                    log_message('info', "Unchanged, skipping: {$filePath}");
                    continue;
                }

                $relativePath = str_replace($gameDataPath . '/', '', $filePath);
                $parts = explode('/', $relativePath);
                $category = strtolower($parts[0] ?? 'unknown');
                $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

                $raw = file_get_contents($filePath);

                $data = [
                    'filepath'   => $relativePath,
                    'filename'   => basename($filePath),
                    'extension'  => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
                    'category'   => $category,
                    'hash'       => md5_file($filePath),
                    'indexed_at' => date(DATE_ATOM),
                    'raw'        => $raw,
                ];

                try {
                    if (filesize($filePath) > 16777216) { // 16 MB
                      log_message('warning', "Skipping large file: {$filePath}");
                      continue;
                    }
                    $content = file_get_contents($filePath);
                    $data['raw'] = $content;

                    $collection->replaceOne(
                        ['filepath' => $filePath],
                        $data,
                        ['upsert' => true]
                    );

                    log_message('info', "Indexed: {$filePath}");
                } catch (\Throwable $e) {
                    log_message('error', "Failed to read {$filePath}: " . $e->getMessage());
                }
            }

            CLI::write('Indexing complete.', 'green');
            log_message('info', 'MongoDB indexing complete.');
            try {
                $collection->createIndex(['raw' => 'text']);
                log_message('info', 'MongoDB text index on `raw` created (or already exists).');
            } catch (\Throwable $e) {
                log_message('error', 'Failed to create text index on `raw`: ' . $e->getMessage());
            }

        } catch (\Throwable $e) {
            log_message('error', 'MongoDB connection or operation failed: ' . $e->getMessage());
            CLI::error('MongoDB indexing failed.');
        }
    }
}

