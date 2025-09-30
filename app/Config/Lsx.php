<?php
namespace Config;

use CodeIgniter\Config\BaseConfig;

class Lsx extends BaseConfig
{
    /**
     * Preferred languages (by code) in priority order.
     * Will try these first when picking localization XMLs.
     * Examples: 'en', 'en-US', 'fr', 'de', ...
     */
    public array $preferredLanguages = ['en', 'en-US'];

    /**
     * If true, merge all localization XMLs found for the mod.
     * If false, select only the first matching preferred language,
     * or the manifest's default if no preferred match exists.
     */
    public bool $mergeAllLanguages = false;

    /**
     * LocalizationScanner options
     */
    public bool $restrictToLocalizationDir = true;   // only scan .../Localization/*
    public bool $validateLocalizationHead  = false;  // strict XML head check (usually false)

    /**
     * Cache key behavior: include all mod XML mtimes in the LSX parse cache key.
     * Safer: cache invalidates if ANY localization file changes.
     */
    public bool $includeAllXmlInCacheKey = true;
}
?>
