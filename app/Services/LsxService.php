<?php
declare(strict_types=1);

namespace App\Services;

use App\Libraries\LocalizationScanner;
use App\Libraries\LsxHelper;
use App\Services\Dialog\DialogSummarizer;

final class LsxService
{
    public function __construct(
        private readonly PathResolver $paths,
        private ?LsxHelper $helper = null
    ) {
        $this->helper ??= service('lsxHelper');
    }

    /** Cheap peek for region & group. */
    public function peek(string $abs): array
    {
        if ($this->helper && method_exists($this->helper, 'peek')) {
            $res = $this->helper->peek($abs);
            return [
                'region'      => $res['region'] ?? $res['Region'] ?? null,
                'regionGroup' => $res['regionGroup'] ?? $res['group'] ?? $this->toGroup($res['region'] ?? null),
            ];
        }
        $chunk = @file_get_contents($abs, false, null, 0, 131072) ?: '';
        if (preg_match('~<\s*region[^>]*\bid\s*=\s*"([^"]+)"~i', $chunk, $m)) {
            $r = $m[1];
            return ['region' => $r, 'regionGroup' => $this->toGroup($r)];
        }
        return ['region' => null, 'regionGroup' => null];
    }

    public function read(string $abs, array $ctx = []): array
    {
        $payload = $this->normalize($abs, $ctx);     // your existing normalizer
        $peek    = $this->peek($abs, $ctx);          // your existing peeker

        $region      = $peek['region']      ?? null;
        $regionGroup = $peek['regionGroup'] ?? null;

        $meta = [
            'region'      => $region ? (string)$region : null,
            'regionGroup' => $regionGroup ? (string)$regionGroup : null,
        ];

        if (\is_string($regionGroup) && \strtolower($regionGroup) === 'dialog') {
            // Build the localization handle map, then summarize
            $handles = $this->loadHandlesForFile($abs, $ctx);
            $summ    = new \App\Services\Dialog\DialogSummarizer(\is_array($handles) ? $handles : [], $abs);
            $meta['dialog'] = $summ->summarize($payload);

            // Optional: expose a small sample so you can sanity-check in the browser
            if (!empty($handles)) {
                $sample = \array_slice(\array_keys($handles), 0, 5);
                $meta['localization'] = [
                    'handlesCount' => \count($handles),
                    'sampleKeys'   => $sample,
                ];
            } else {
                $meta['localization'] = [
                    'handlesCount' => 0,
                    'sampleKeys'   => [],
                ];
            }
        }

        return [
            'kind'    => 'lsx',
            'ext'     => 'lsx',
            'meta'    => $meta,
            'payload' => $payload,
            'result'  => $payload, // keep for back-compat if the UI still reads result.*
        ];
    }


    /** Low-level: parse XML to normalized array (or delegate to helper). */
    public function normalize(string $abs): array
    {
        if ($this->helper) {
            foreach (['readNormalized','normalize','parse','read'] as $m) {
                if (method_exists($this->helper, $m)) {
                    $res = $this->helper->{$m}($abs);
                    if (is_array($res)) return $res;
                }
            }
        }
        $xml = @file_get_contents($abs) ?: '';
        if ($xml === '') return ['raw' => ''];
        $sx = @simplexml_load_string($xml);
        if ($sx === false) return ['raw' => $xml];
        $dom = dom_import_simplexml($sx);
        if (!$dom) return ['raw' => $xml];
        return $this->domToNormalized($dom);
    }

    private function domToNormalized(\DOMNode $node): array
    {
        $out = ['tag' => $node->nodeName, 'attr' => [], 'children' => []];
        if ($node->attributes) {
            foreach ($node->attributes as $a) $out['attr'][$a->nodeName] = $a->nodeValue;
        }
        foreach ($node->childNodes as $c) {
            if ($c->nodeType === XML_TEXT_NODE) {
                $txt = trim($c->nodeValue ?? '');
                if ($txt !== '') $out['children'][] = ['tag' => '#text', 'text' => $txt];
            } elseif ($c->nodeType === XML_ELEMENT_NODE) {
                $out['children'][] = $this->domToNormalized($c);
            }
        }
        return $out;
    }

    private function toGroup(?string $region): ?string
    {
        if ($region === null) return null;
        $r = strtolower($region);
        if ($r === 'dialog' || $r === 'dialogs' || $r === 'story.dialog') return 'dialog';
        return null;
    }

    /**
     * Build a handle -> { text, version } map for the LSX's module.
     * Supports <content contentuid="..."> and <content handle="...">, and grabs
     * any descendant <text>...</text> (male/female branches included).
     */
    private function loadHandlesForFile(string $abs, array $ctx): array
    {
        // Caller supplied a map? use it.
        if (!empty($ctx['handles']) && is_array($ctx['handles'])) {
            return $ctx['handles'];
        }

        // Preferred: a scanner, if your project has one wired.
        if (class_exists(\App\Libraries\LocalizationScanner::class)) {
            try {
                $scanner = new \App\Libraries\LocalizationScanner(true, true);
                if (method_exists($scanner, 'scanModule')) {
                    $h = $scanner->scanModule(\dirname($abs), 'English');
                    if (is_array($h) && $h) return $h;
                } elseif (method_exists($scanner, 'scan')) {
                    $h = $scanner->scan(\dirname($abs), 'English');
                    if (is_array($h) && $h) return $h;
                }
            } catch (\Throwable $e) {
                // fall through to manual parse
            }
        }

        // Walk up to the nearest .../Localization/English/english.xml
        $dir  = \dirname($abs);
        $root = null;
        for ($i = 0; $i < 10; $i++) {
            if (\is_dir($dir . DIRECTORY_SEPARATOR . 'Localization')) { $root = $dir; break; }
            $parent = \dirname($dir);
            if ($parent === $dir) break;
            $dir = $parent;
        }
        if (!$root) return [];

        $english = $root . DIRECTORY_SEPARATOR . 'Localization' . DIRECTORY_SEPARATOR . 'English' . DIRECTORY_SEPARATOR . 'english.xml';
        if (!\is_file($english)) return [];

        $xml = @\file_get_contents($english);
        if ($xml === false || $xml === '') return [];

        $map = [];

        // Capture each <content ...>...</content> that has contentuid or handle
        if (\preg_match_all('~<content\b([^>]*)>(.*?)</content>~is', $xml, $blocks, PREG_SET_ORDER)) {
            foreach ($blocks as $blk) {
                $attrs = $blk[1];
                $inner = $blk[2];

                // Extract the key (prefer contentuid, else handle)
                $key = null;
                if (\preg_match('~\bcontentuid\s*=\s*"([^"]+)"~i', $attrs, $m)) {
                    $key = $m[1];
                } elseif (\preg_match('~\bhandle\s*=\s*"([^"]+)"~i', $attrs, $m)) {
                    $key = $m[1];
                }
                if ($key === null || $key === '') continue;

                // Try to extract a version if available
                $version = null;
                if (\preg_match('~\bversion\s*=\s*"([^"]+)"~i', $attrs, $vm)) {
                    $version = $vm[1];
                }

                // Extract first <text>…</text> anywhere in the content block (male/female/etc)
                $text = '';
                if (\preg_match('~<text[^>]*>(.*?)</text>~is', $inner, $tm)) {
                    $text = \html_entity_decode(\trim($tm[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                } else {
                    // Fallback: strip tags & take whatever remains
                    $stripped = \trim(\preg_replace('~<[^>]+>~', '', $inner) ?? '');
                    if ($stripped !== '') $text = \html_entity_decode($stripped, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }

                // Store under both original and lowercased keys for tolerant lookup
                $map[$key] = ['text' => $text, 'version' => $version];
                $lc = \strtolower($key);
                if (!isset($map[$lc])) {
                    $map[$lc] = ['text' => $text, 'version' => $version];
                }
            }
        }

        return $map;
    }

}
?>
