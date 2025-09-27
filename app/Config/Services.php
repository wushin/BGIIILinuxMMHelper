<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use App\Services\PathResolver;
use App\Services\ContentService;
use Config\BG3Paths;

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
        return new \App\Services\PathResolver(config(BG3Paths::class));
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
}
?>
