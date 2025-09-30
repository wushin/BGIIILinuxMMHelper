<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use App\Services\PathResolver;
use App\Services\ContentService;
use App\Config\Selection as SelectionConfig;
use App\Services\SelectionService;
use App\Services\LsxService;
use Config\BG3Paths;
use League\CommonMark\Environment\Environment;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;

class Services extends BaseService
{
    public static function contentService(bool $getShared = true): ContentService
    {
        if ($getShared) {
            return static::getSharedInstance('contentService');
        }
        /** @var BG3Paths $cfg */
        $cfg   = config(BG3Paths::class);
        $paths = static::pathResolver(false);
        return new ContentService($paths, $cfg);
    }

    public static function pathResolver(bool $getShared = true)
    {
        if ($getShared) return static::getSharedInstance('pathResolver');
        return new \App\Services\PathResolver();
    }

    public static function directoryScanner(bool $getShared = true)
    {
        if ($getShared) return static::getSharedInstance('directoryScanner');
        return new \App\Services\DirectoryScanner();
    }

    public static function imageTransformer(bool $getShared = true)
    {
        if ($getShared) return static::getSharedInstance('imageTransformer');
        return new \App\Services\ImageTransformer();
    }

    public static function responseBuilder(bool $getShared = true)
    {
        if ($getShared) return static::getSharedInstance('responseBuilder');
        return new \App\Services\ResponseBuilder(service('response'));
    }

    public static function mimeGuesser(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('mimeGuesser');
        }
        return new \App\Services\MimeGuesser(new \Config\FileKinds());
    }

    public static function markdown(bool $getShared = true): MarkdownConverter
    {
        if ($getShared) {
            return static::getSharedInstance('markdown');
        }

        $config = [
            // GitHub-like: don’t turn single newlines into <br>
            'renderer' => [
                'soft_break' => "\n",
            ],
            // Valid options for v2 HeadingPermalinkExtension
            'heading_permalink' => [
                'min_heading_level'   => 1,
                'max_heading_level'   => 6,
                'insert'              => 'before',   // before|after|inside
                'symbol'              => '¶',        // you can style/hide via CSS
                'title'               => 'Permalink',
                'id_prefix'           => '',
                'apply_id_to_heading' => true,
                // NOTE: no slug_normalizer here — that caused your error
            ],
        ];

        $env = new Environment($config);
        $env->addExtension(new CommonMarkCoreExtension());
        $env->addExtension(new GithubFlavoredMarkdownExtension());
        $env->addExtension(new HeadingPermalinkExtension());

        return new MarkdownConverter($env);
    }

    // Mongo client
    public static function mongo(bool $getShared = true): \MongoDB\Client
    {
        if ($getShared) {
            return static::getSharedInstance('mongo');
        }
        $cfg = config(\Config\Mongo::class);
        return new \MongoDB\Client($cfg->uri, $cfg->clientOptions, $cfg->driverOptions);
    }

    // Mongo Collection
    public static function mongoCollection(bool $getShared = true): \MongoDB\Collection
    {
        if ($getShared) {
            return static::getSharedInstance('mongoCollection');
        }
        $cfg = config(\Config\Mongo::class);
        $client = static::mongo(false);
        $client_db = $client->selectDatabase($cfg->db);
        return $client_db->selectCollection($cfg->collection);
    }

    // Mongo indexer
    public static function mongoIndexer(bool $getShared = true): \App\Services\MongoIndexer
    {
        if ($getShared) {
            return static::getSharedInstance('mongoIndexer');
        }
        $mongo = static::mongo(false);
        $paths = static::pathResolver(false);
        $kinds = static::mimeGuesser(false);
        $mcfg  = config(\Config\Mongo::class);
        $bg3   = config(\Config\BG3Paths::class);
        return new \App\Services\MongoIndexer($mongo, $bg3, $mcfg);
    }

    public static function selection(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('selection');
        }

        $config  = config(SelectionConfig::class);
        $session = service('session');

        return new SelectionService($config, $session);
    }

    public static function lsxService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('lsxService');
        }
        return new \App\Services\LsxService(static::pathResolver());
    }

    public static function parserFactory(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('parserFactory');
        }
        return new \App\Services\Parsers\ParserFactory(
            service('mimeGuesser'),
            service('lsxService')
        );
    }
}
?>
