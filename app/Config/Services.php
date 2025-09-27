<?php

namespace Config;

use CodeIgniter\Config\BaseService;
use App\Services\PathResolver;
use App\Services\ContentService;
use Config\BG3Paths;

class Services extends BaseService
{
    public static function pathResolver(bool $getShared = true): PathResolver
    {
        if ($getShared) {
            return static::getSharedInstance('pathResolver');
        }
        /** @var BG3Paths $cfg */
        $cfg = config(BG3Paths::class);
        return new PathResolver($cfg);
    }

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
}
?>
