<?php
namespace App\Services\Parsers;

use App\Services\Parsers\ParserInterface;

final class StatsParser implements ParserInterface
{
    public function read(string $bytes, string $absPath): array
    {
        // 1) Split lines and preserve original order for roundtrip
        $lines = preg_split('/\R/u', $bytes) ?: [];
        $sourceLines = [];
        foreach ($lines as $i => $raw) {
            $sourceLines[] = ['i' => $i, 'text' => rtrim($raw, "\r\n")];
        }

        // 2) Parse Larian stats blocks
        $entries = [];
        $cur = null;

        foreach ($lines as $raw) {
            $line = trim($raw);
            if ($line === '' || (isset($line[0]) && $line[0] === '#')) continue;

            if (preg_match('~^new\s+entry\s+"([^"]+)"~i', $line, $m)) {
                if ($cur) $entries[] = $cur;
                $cur = ['name' => $m[1], 'type' => null, 'using' => null, 'data' => []];
                continue;
            }
            if (!$cur) continue;

            if (preg_match('~^type\s+"([^"]+)"~i', $line, $m)) {
                $cur['type'] = $m[1]; continue;
            }
            if (preg_match('~^using\s+"([^"]+)"~i', $line, $m)) {
                $cur['using'] = $m[1]; continue;
            }
            if (preg_match('~^data\s+"([^"]+)"\s*"([^"]*)"~i', $line, $m)) {
                $key = $m[1]; $val = $m[2];
                $cur['data'][$key] = $val;
                continue;
            }
        }
        if ($cur) $entries[] = $cur;

        // 3) Build a localization handle map for this mod (versioned + latest)
        $handleMap = [];
        try {
            $lsxCfg  = service('lsxConfig');
            $scanner = new \App\Libraries\LocalizationScanner(
                $lsxCfg->restrictToLocalizationDir ?? true,
                $lsxCfg->validateLocalizationHead  ?? false
            );

            $rootKey = $GLOBALS['__ctx_root']    ?? null;
            $slug    = $GLOBALS['__ctx_slug']    ?? null;

            if ($rootKey && $slug) {
                $manifest = $scanner->scanMyModsManifest(null);
                if (isset($manifest[$slug])) {
                    if (!empty($lsxCfg->mergeAllLanguages)) {
                        $handleMap = $scanner->buildHandleMapForMod($manifest, $slug, null, [], true);
                    } else {
                        $prefs = $lsxCfg->preferredLanguages ?? ['en','en-US'];
                        $handleMap = $scanner->buildHandleMapForMod($manifest, $slug, null, $prefs, false);
                    }
                } else {
                    $modAbs = service('pathResolver')->join($rootKey, $slug, null);
                    $xmls   = $scanner->findLocalizationXmlsForMod($modAbs);
                    $handleMap = $scanner->buildHandleMapFromFiles($xmls, true);
                }
            }
        } catch (\Throwable $__) {
            $handleMap = [];
        }

        // 4) Resolve TranslatedString handles in data values
        $resolvedCount = 0;
        $unresolved = [];
        try {
            $resolver = service('textHandleResolver');

            foreach ($entries as &$e) {
                if (!isset($e['data']) || !is_array($e['data'])) continue;
                foreach ($e['data'] as $k => $v) {
                    if (!is_string($v)) continue;
                    // returns [text|null, handle|null, version|null]
                    [$text, $h, $ver] = $resolver->resolve($v, $handleMap);
                    if ($h !== null) {
                        if ($text !== null) {
                            $e['data'][$k] = [
                                'raw'    => $v,
                                'text'   => $text,
                                'handle' => $h,
                                'ver'    => $ver, // null => latest
                            ];
                            $resolvedCount++;
                        } else {
                            $e['data'][$k] = [
                                'raw'    => $v,
                                'handle' => $h,
                                'ver'    => $ver,
                            ];
                            $unresolved[] = $v;
                        }
                    }
                }
            }
            unset($e);
        } catch (\Throwable $__) {
            // leave entries as-is if resolver not available
        }

        return [
            'kind' => 'txt.stats',
            'json' => [
                'entries' => $entries,
                'source'  => ['lines' => $sourceLines],
            ],
            'meta' => [
                'subtype'           => 'generic',
                'resolvedHandles'   => $resolvedCount,
                'unresolvedSamples' => array_slice(array_values(array_unique($unresolved)), 0, 20),
            ],
        ];
    }

    public function write(?string $raw, ?string $json, string $absPath): string
    {
        // keep passthrough (a pretty-writer can come later)
        return (string)($raw ?? ($json ?? ''));
    }
}
?>
