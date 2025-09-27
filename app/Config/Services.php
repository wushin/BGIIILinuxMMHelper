<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use App\Services\PathResolver;
use App\Services\ContentService;
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

    public static function lsxService(bool $getShared = true)
    {
        if ($getShared) return static::getSharedInstance('lsxService');
        // LocalizationScanner lives in App\Libraries and has its own config
        $scanner = new \App\Libraries\LocalizationScanner(true, false);
        return new \App\Services\LsxService(config(LsxRegions::class), $scanner);
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
        if ($getShared) return static::getSharedInstance('mimeGuesser');
        return new \App\Services\MimeGuesser(config(\Config\FileKinds::class));
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
}
?>
