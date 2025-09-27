<?php
/**
 * This file is part of BGIII Mod Manager Linux Helper.
 *
 * Copyright (C) 2025 Wushin.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Libraries;

use SimpleXMLElement;

class LocalizationScanner
{
    /** ignore entries when scanning directories */
    private array $ignore = ['.', '..', '.DS_Store', 'Thumbs.db'];
    /** if true, only accept XMLs under a "Localization" folder */
    private bool $restrictToLocalizationDir;
    /** if true, peek head to confirm an XML looks like localization */
    private bool $validateLocalizationHead;

    public function __construct(bool $restrictToLocalizationDir = true, bool $validateLocalizationHead = false)
    {
        $this->restrictToLocalizationDir = $restrictToLocalizationDir;
        $this->validateLocalizationHead  = $validateLocalizationHead;
    }

    /* ----------------------------- public API ----------------------------- */

    /**
     * Scan MyMods and return a manifest of localization XMLs per mod.
     * Shape:
     * {
     *   "ModName": {
     *     "xml_files": ["/abs/.../english.xml", "/abs/.../french.xml", ...],
     *     "by_language": {"en": "...english.xml", "fr": "...french.xml", "unknown": "..."},
     *     "default": "en" | "unknown" | null
     *   },
     *   ...
     * }
     */
    public function scanMyModsManifest(?string $myModsRoot = null): array
    {
        $root = $this->resolveMyModsRoot($myModsRoot);
        if (!$root) return ['error' => 'MyMods root not configured or not a directory'];

        $mods = $this->scanDirectory($root, true); // dirs only
        $out  = [];

        foreach ($mods as $dirName) {
            $modPath = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dirName;
            $xmls    = $this->findLocalizationXmlsForMod($modPath);

            $byLang = [];
            foreach ($xmls as $p) {
                $lang = $this->guessLanguageFromFilename($p) ?? 'unknown';
                if (!isset($byLang[$lang])) $byLang[$lang] = $p; // keep first match per lang
            }
            $default = isset($byLang['en']) ? 'en' : (array_key_first($byLang) ?: null);

            $out[$dirName] = [
                'xml_files'   => $xmls,
                'by_language' => $byLang,
                'default'     => $default,
            ];
        }
        ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }

    /**
     * Build a merged handle->text map from:
     * - a specific xml path,
     * - a list of paths,
     * - languages within a manifest entry,
     * - or “all” for that mod.
     *
     * Later files override earlier keys.
     */
    public function buildHandleMapForMod(
        array $manifest,                  // from scanMyModsManifest()
        string $modName,                  // key in $manifest
        array|string|null $paths = null,  // explicit XML path(s) (wins over languages/all)
        array $languages = [],            // e.g. ['en','fr']; uses manifest[mod]['by_language']
        bool $all = false                 // if true, uses manifest[mod]['xml_files']
    ): array {
        if (!isset($manifest[$modName])) return [];

        $chosenPaths = [];
        if ($paths) {
            $chosenPaths = is_array($paths) ? $paths : [$paths];
        } elseif ($languages) {
            foreach ($languages as $lang) {
                $p = $manifest[$modName]['by_language'][$lang] ?? null;
                if ($p) $chosenPaths[] = $p;
            }
        } elseif ($all) {
            $chosenPaths = $manifest[$modName]['xml_files'];
        } else {
            $defLang = $manifest[$modName]['default'] ?? null;
            if ($defLang && isset($manifest[$modName]['by_language'][$defLang])) {
                $chosenPaths[] = $manifest[$modName]['by_language'][$defLang];
            }
        }

        return $this->buildHandleMapFromFiles($chosenPaths);
    }

    /**
     * Parse an LSX (string) using a prebuilt handle->text map.
     * Returns JSON string with structure-preserving tree and attrs.text for TranslatedString.
     */
    public function parseLsxWithHandleMapToJson(string $lsxXml, array $handleMap): string
    {
        $tree = $this->parseLsxWithHandleMap($lsxXml, $handleMap);
        $json = json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new \RuntimeException('JSON encode failed: ' . json_last_error_msg());
        return $json;
    }

    /**
     * Convenience: end-to-end for a mod:
     * - pick localization XMLs by [$paths] or [$languages] or $all,
     * - merge handles,
     * - parse LSX (string) with that map → JSON string.
     */
    public function parseLsxForModToJson(
        array $manifest,
        string $modName,
        string $lsxXml,
        array|string|null $paths = null,
        array $languages = [],
        bool $all = false
    ): string {
        $map = $this->buildHandleMapForMod($manifest, $modName, $paths, $languages, $all);
        return $this->parseLsxWithHandleMapToJson($lsxXml, $map);
    }

    /* ----------------------------- scanning ------------------------------ */

    public function findLocalizationXmlsForMod(string $modPath): array
    {
        if (!is_dir($modPath)) return [];

        $xmls = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) continue;
            if (strtolower($file->getExtension()) !== 'xml') continue;

            $path = $file->getPathname();

            if ($this->restrictToLocalizationDir) {
                if (!preg_match('~[/\\\\]Localization([/\\\\]|$)~i', $path)) continue;
            }

            if ($this->validateLocalizationHead && !$this->looksLikeLocalizationXml($path)) {
                continue;
            }

            $xmls[] = $path;
        }

        sort($xmls, SORT_STRING);
        return $xmls; // can be 0/1/MANY
    }

    /* -------------------------- handle map build ------------------------- */

    public function buildHandleMapFromFiles(array $paths, bool $laterWins = true): array
    {
        $map = [];
        foreach ($paths as $p) {
            if (!is_file($p)) continue;

            $xml = null;
            try {
                $xml = service('contentService')->read($p);
            } catch (\Throwable $__) {
                // fallback for paths outside allowed roots
                $xml = @file_get_contents($p);
            }
            if ($xml === false || $xml === '') continue;

            $partial = $this->buildHandleMapFromString($xml);

            // merge
            if ($laterWins) {
                foreach ($partial as $k => $v) $map[$k] = $v;
            } else {
                foreach ($partial as $k => $v) if (!array_key_exists($k, $map)) $map[$k] = $v;
            }
        }
        return $map;
    }

    /**
     * Parse english.xml-like content: <content contentuid="...">text</content>
     * (optionally wrapped in <contentList>).
     */
    public function buildHandleMapFromString(string $englishXml): array
    {
        $sx = $this->loadXmlString($englishXml);
        $map = [];
        $contents = $sx->xpath("//content");
        if ($contents) {
            foreach ($contents as $c) {
                $uid  = (string)($c['contentuid'] ?? '');
                $text = trim((string)$c);
                if ($uid !== '') $map[$uid] = $text;
            }
        }
        return $map;
    }

    /* ------------------------------ parser ------------------------------- */

    /** Core LSX parse with supplied handle map (no file I/O). */
    public function parseLsxWithHandleMap(string $lsxXml, array $handleMap): array
    {
        $root = $this->loadXmlString($lsxXml);
        return $this->elementToArray($root, $handleMap);
    }

    private function elementToArray(SimpleXMLElement $el, array $handleMap): array
    {
        $node = [
            'name'     => $el->getName(),
            'attrs'    => [],
            'children' => []
        ];

        foreach ($el->attributes() as $k => $v) {
            $node['attrs'][(string)$k] = (string)$v;
        }

        if (strcasecmp($node['name'], 'attribute') === 0) {
            $type   = $node['attrs']['type']   ?? '';
            $handle = $node['attrs']['handle'] ?? '';
            if (strcasecmp($type, 'TranslatedString') === 0 && $handle !== '') {
                $node['attrs']['text'] = array_key_exists($handle, $handleMap) ? $handleMap[$handle] : null;
            }
        }

        foreach ($el->children() as $child) {
            $node['children'][] = $this->elementToArray($child, $handleMap);
        }
        return $node;
    }

    /* ----------------------------- helpers ------------------------------- */

    private function scanDirectory(string $path, bool $dirsOnly = false): array
    {
        if (!is_dir($path)) return [];
        $path    = rtrim($path, DIRECTORY_SEPARATOR);
        $entries = array_values(array_diff(scandir($path), $this->ignore));
        if ($dirsOnly) {
            $entries = array_values(array_filter($entries, fn(string $n) => is_dir($path . DIRECTORY_SEPARATOR . $n)));
        }
        return $entries;
    }

    /** Prefer override, else CI4 pathResolver()->myMods() */
    private function resolveMyModsRoot(?string $override): ?string
    {
        if ($override && is_dir($override)) {
            $real = realpath($override) ?: $override;
            return is_dir($real) ? $real : null;
        }
        $paths = service('pathResolver');
        $root  = $paths->myMods();
        return ($root && is_dir($root)) ? $root : null;
    }

    private function looksLikeLocalizationXml(string $xmlPath): bool
    {
        // Try reading via contentService (then trim to head), else fallback to native head-read
        $head = null;
        try {
            $bytes = service('contentService')->read($xmlPath);
            $head  = substr($bytes, 0, 4096);
        } catch (\Throwable $__) {
            $fh = @fopen($xmlPath, 'rb');
            if ($fh) {
                $head = @fread($fh, 4096);
                @fclose($fh);
            }
        }
        if (!$head) return false;

        // quick signature checks
        if (preg_match('/<content\s+[^>]*contentuid\s*=\s*"/i', $head)) return true;
        if (preg_match('/<contentList[\s>]/i', $head)) return true;
        return false;
    }

    private function loadXmlString(string $xml): SimpleXMLElement
    {
        $xml = trim($xml);
        if ($xml === '') throw new \InvalidArgumentException('Empty XML string');
        libxml_use_internal_errors(true);
        $sx = simplexml_load_string(
            $xml,
            "SimpleXMLElement",
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET | LIBXML_BIGLINES | LIBXML_NOCDATA
        );
        if ($sx === false) {
            $errs = array_map(fn($e) => trim($e->message ?? ''), libxml_get_errors() ?: []);
            libxml_clear_errors();
            throw new \RuntimeException('Failed to parse XML: ' . implode(' | ', array_filter($errs)));
        }
        return $sx;
    }

    private function guessLanguageFromFilename(string $path): ?string
    {
        $base = strtolower(pathinfo($path, PATHINFO_FILENAME));
        $map = [
            'english'=>'en','en'=>'en','eng'=>'en','enus'=>'en',
            'french'=>'fr','fr'=>'fr',
            'german'=>'de','de'=>'de',
            'spanish'=>'es','es'=>'es',
            'italian'=>'it','it'=>'it',
            'polish'=>'pl','pl'=>'pl',
            'russian'=>'ru','ru'=>'ru',
            'turkish'=>'tr','tr'=>'tr',
            'korean'=>'ko','ko'=>'ko',
            'japanese'=>'ja','ja'=>'ja',
            'schinese'=>'zh-CN','zhcn'=>'zh-CN',
            'tchinese'=>'zh-TW','zhtw'=>'zh-TW',
            'portuguese'=>'pt','pt'=>'pt','ptbr'=>'pt-BR','brazilian'=>'pt-BR',
        ];
        if (isset($map[$base])) return $map[$base];
        foreach ($map as $needle => $code) {
            if (str_contains($base, $needle)) return $code;
        }
        return null;
    }
}
?>
