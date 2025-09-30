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
        $cfg  = service('contentConfig');
        $tele = service('telemetry');
        $lsxCfg = service('lsxConfig');
        $tok  = $tele->start('lsx.parse');

        $abs   = $ctx['abs']    ?? null;   // if you pass abs in ctx, great
        $rel   = $ctx['relPath']?? null;
        $stat  = null;
        $key   = null;

        // Build a stable cache key if we can fingerprint the file
        $locSig = '';           // signature of localization inputs (mtimes/sizes)
        $xmlListForSig = [];    // only for meta/debug; not required

        if (is_string($abs) && $abs !== '' && @is_file($abs)) {
            $stat = @stat($abs);

            // If we know which mod we're in, hash its localization XMLs into the key
            if (!empty($ctx['rootKey']) && !empty($ctx['slug'])) {
                $modAbs = $this->paths->join($ctx['rootKey'], $ctx['slug'], null);
                if (@is_dir($modAbs)) {
                    $scanner = new \App\Libraries\LocalizationScanner(
                        $lsxCfg->restrictToLocalizationDir,
                        $lsxCfg->validateLocalizationHead
                    );

                    // Either prefer a manifest selection (language-aware) or just take all XMLs for signature
                    if ($lsxCfg->includeAllXmlInCacheKey) {
                        $xmlListForSig = $scanner->findLocalizationXmlsForMod($modAbs);
                    } else {
                        $manifest = $scanner->scanMyModsManifest(null);
                        $xmlListForSig = $manifest[$ctx['slug']]['xml_files'] ?? [];
                    }

                    if ($xmlListForSig) {
                        $parts = [];
                        foreach ($xmlListForSig as $p) {
                            $st = @stat($p);
                            $parts[] = $p . '|' . (($st['mtime'] ?? 0)) . '|' . (($st['size'] ?? 0));
                        }
                        $locSig = md5(implode(';', $parts));
                    }
                }
            }

            $key = 'lsx_' . md5(
                ($abs ?? '') . '|' . ($stat['mtime'] ?? 0) . '|' . ($stat['size'] ?? strlen($bytes)) . '|' . $locSig
            );
        }

        if (!$key && $cfg->lsxCacheTtl > 0) {
            // fallback: bytes + relPath if provided
            $rel = (string)($ctx['relPath'] ?? '');
            $key = 'lsx_' . md5($rel . '#' . strlen($bytes));
        }

        if ($cfg->lsxCacheTtl > 0 && $key) {
            $cached = cache($key);
            if (is_array($cached) && isset($cached['payload'], $cached['meta'])) {
                $tele->end($tok, ['path' => $rel ?? $abs, 'cached' => true]);
                return $cached;
            }
        }
    
        $__t0 = hrtime(true);
        $region = $this->helper->detectRegionFromHead($bytes);
        $group  = $this->helper->regionGroupFromId($region);

        $meta = [
            'region' => $region,
            'regionGroup'  => $group,
        ];
        $lsxCfg = service('lsxConfig');
        $normalizer = service('lsxNormalizer');
        try {
            $payload = $normalizer->normalize($bytes);   // ← canonical JSON tree
        } catch (\Throwable $e) {
            // Fallback: keep working even if a file isn’t strictly well-formed
            $payload = ['raw' => $bytes, 'error' => $e->getMessage()];
        }

        $jsonTree = null;

        try {
            $scanner = new \App\Libraries\LocalizationScanner(
                $lsxCfg->restrictToLocalizationDir,
                $lsxCfg->validateLocalizationHead
            );

            $handleMap = [];

            // Only build a map when we know the mod folder
            if (!empty($ctx['rootKey']) && !empty($ctx['slug'])) {
                $modAbs = $this->paths->join($ctx['rootKey'], $ctx['slug'], null);

                // Prefer a language-aware selection via manifest
                $manifest = $scanner->scanMyModsManifest(null);
                if (isset($manifest[$ctx['slug']])) {
                    if ($lsxCfg->mergeAllLanguages) {
                        // merge every XML for the mod
                        $handleMap = $scanner->buildHandleMapForMod($manifest, $ctx['slug'], null, [], true);
                    } else {
                        // pick from preferredLanguages in order, or the default
                        $handleMap = $scanner->buildHandleMapForMod(
                            $manifest,
                            $ctx['slug'],
                            null,
                            $lsxCfg->preferredLanguages,
                            false
                        );
                    }
                } else {
                    // fallback: brute scan for XMLs in the mod folder
                    $langXmls  = $scanner->findLocalizationXmlsForMod($modAbs);
                    $handleMap = $scanner->buildHandleMapFromFiles($langXmls, true);
                }
            }

            // Parse LSX with the handle map (injects attrs.text for TranslatedString handles)
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

        $__ms = (hrtime(true) - $__t0) / 1e6;
        log_message('info', 'LSX parse {path} region={region} group={group} ms={ms}', [
            'path'   => ($ctx['relPath'] ?? $ctx['abs'] ?? '(unknown)'),
            'region' => $meta['region'] ?? null,
            'group'  => $meta['regionGroup'] ?? null,
            'ms'     => number_format($__ms, 3),
        ]);
        if ($cfg->lsxCacheTtl > 0 && $key) {
            cache()->save($key, ['payload' => $payload, 'meta' => $meta], $cfg->lsxCacheTtl);
        }
        $tele->end($tok, ['path' => $rel ?? $abs, 'cached' => false]);

        return ['payload' => $payload, 'meta' => $meta];
    }
}

