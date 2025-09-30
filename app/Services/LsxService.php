<?php

namespace App\Services;

use App\Libraries\LocalizationScanner;
use App\Libraries\LsxHelper;

final class LsxService
{
    public function __construct(
        private readonly PathResolver $paths,
        private ?\App\Libraries\LsxHelper $helper = null
    ) {
        $this->helper ??= service('lsxHelper');
    }

    /**
     * Parse LSX bytes with optional enrichment (region/group + handle-map).
     * @param string $bytes  LSX file content
     * @param array  $ctx    ['rootKey' => 'MyMods', 'slug' => '...', 'relPath' => '...'] (optional)
     * @return array { payload: array, meta: array }
     */
    public function parse(string $bytes, array $ctx = []): array
    {
        $region = $this->helper->detectRegionFromHead($bytes);
        $group  = $this->helper->regionGroupFromId($region);

        $meta = [
            'region' => $region,
            'regionGroup'  => $group,
        ];

        $jsonTree = null;

        try {
            $scanner   = new LocalizationScanner(true, false);

            // Build handle-map only when we know which mod we're in
            $handleMap = [];
            if (!empty($ctx['rootKey']) && !empty($ctx['slug'])) {
                $modAbs    = $this->paths->join($ctx['rootKey'], $ctx['slug'], null);
                $langXmls  = $scanner->findLocalizationXmlsForMod($modAbs);
                $handleMap = $scanner->buildHandleMapFromFiles($langXmls, true);
            }

            $jsonTree = $scanner->parseLsxWithHandleMapToJson($bytes, $handleMap);
        } catch (\Throwable $__) {
            // Fallback to generic XML→JSON if enrichment blows up
            try {
                $jsonTree = \App\Helpers\XmlHelper::createJson($bytes, true);
            } catch (\Throwable $___) {
                $jsonTree = null;
            }
        }

        $payload = $jsonTree ? json_decode($jsonTree, true) : null;
        if (!is_array($payload)) {
            $payload = ['raw' => $bytes];
        }

        return ['payload' => $payload, 'meta' => $meta];
    }
}

