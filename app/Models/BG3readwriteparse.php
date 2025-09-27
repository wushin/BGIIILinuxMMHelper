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

namespace App\Models;

use CodeIgniter\Model;

use App\Helpers\XmlHelper;
use App\Helpers\ArrayHelper;
use App\Helpers\FormatHelper;
use App\Helpers\FilePathHelper;
use App\Helpers\TextParserHelper;
use App\Helpers\DialogHelper;
use App\Libraries\LocalizationScanner;

class BG3readwriteparse extends Model
{
    /** Parser for JSON in/out helpers */
    protected DialogHelper $dialog;

    /** language XMLs parsed to arrays: [ filePath => array| "Empty" ] */
    protected array $_Lang = [];

    /** game data parsed to arrays (plus region meta on LSX): [ filePath => array ] */
    protected array $_Data = [];

    /** region → [file paths] index for LSX */
    protected array $_RegionIndex = [];

    protected string $moddir;

    public function __construct($moddir)
    {
        $this->dialog = new DialogHelper();
        $this->moddir = $moddir;
        $this->buildDataSet($moddir);
    }

    public function __call($method, $arguments)
    {
        $arguments = array_merge(['stdObject' => $this], $arguments);
        if (isset($this->{$method}) && is_callable($this->{$method})) {
            return \call_user_func_array($this->{$method}, $arguments);
        }
        throw new \Exception("Fatal error: Call to undefined method stdObject::{$method}()");
    }

    /* ----------------------- Parsers (leaf ops) ----------------------- */

    private function parseLang(string $langfile): void
    {
        if (FilePathHelper::testFile($langfile)) {
            $xml = service('contentService')->read($langfile);
            $this->_Lang[$langfile] = XmlHelper::createArray($xml);
        } else {
            $this->_Lang[$langfile] = "Empty";
        }
    }

    /** Pick default localization XML(s) for this mod. If $all=true, return all; else best single (prefer EN). */
    private function defaultLocalizationPaths(bool $all = false): array
    {
        $scanner = new \App\Libraries\LocalizationScanner(true, false);
        $paths   = $scanner->findLocalizationXmlsForMod($this->moddir);
        if (!$paths) return [];

        if ($all) {
            return $paths;
        }

        // Prefer English-ish filenames
        $prefer = ['english','en','eng','enus'];
        foreach ($paths as $p) {
            $base = strtolower(pathinfo($p, PATHINFO_FILENAME));
            if (in_array($base, $prefer, true)) {
                return [$p];
            }
            // fuzzy: filename contains any preferred token
            foreach ($prefer as $tok) {
                if (str_contains($base, $tok)) {
                    return [$p];
                }
            }
        }
        // fallback: first
        return [reset($paths)];
    }

    /** Build handle→text map from default localization XML(s). If $all=true, merge all Lang XMLs. */
    private function buildDefaultHandleMap(bool $all = false): array
    {
        $paths = $this->defaultLocalizationPaths($all);
        if (!$paths) return [];

        $scanner = new \App\Libraries\LocalizationScanner(true, false);
        return $scanner->buildHandleMapFromFiles($paths, true); // laterWins=true
    }


    /**
     * Parse an LSX file with region peek, switching by region id.
     * - Attaches: __region and __regionGroup into the parsed array
     * - Indexes file under $_RegionIndex[$region]
     */
    private function parseLsx(string $filepath): void
    {
        $region = $this->detectRegionFromFileHead($filepath) ?? 'unknown';

        // Keep full parse across categories (original behavior)
        $xmlArr = XmlHelper::createArray(service('contentService')->read($filepath));

        if (\is_array($xmlArr)) {
            $xmlArr['__region']      = $region;
            $xmlArr['__regionGroup'] = $this->regionGroupFromId($region); // e.g., dialog / gameplay / assets / meta / unknown
        }

        $this->_Data[$filepath] = $xmlArr;

        $bucket = $region ?: 'unknown';
        $this->_RegionIndex[$bucket] = $this->_RegionIndex[$bucket] ?? [];
        $this->_RegionIndex[$bucket][] = $filepath;
    }

    /**
     * Classify a region id into a broader group (useful for filtering/views).
     */
    private function regionGroupFromId(string $region): string
    {
        switch ($region) {
            // Dialog-ish
            case 'dialog':
            case 'DialogBank':
            case 'TimelineBank':
            case 'TimelineContent':
            case 'TLScene':
                return 'dialog';

            // Asset/visual banks
            case 'TextureBank':
            case 'TextureAtlasInfo':
            case 'MaterialBank':
            case 'CharacterVisualBank':
            case 'VisualBank':
            case 'IconUVList':
                return 'assets';

            // Gameplay data
            case 'AbilityDistributionPresets':
            case 'ActionResourceDefinitions':
            case 'Backgrounds':
            case 'CharacterCreationAppearanceVisuals':
            case 'CharacterCreationPresets':
            case 'ClassDescriptions':
            case 'ConditionErrors':
            case 'DefaultValues':
            case 'Effect':
            case 'EffectBank':
            case 'EnterPhaseSoundEvents':
            case 'EnterSoundEvents':
            case 'EquipmentLists':
            case 'ExitPhaseSoundEvents':
            case 'ExitSoundEvents':
            case 'FactionContainer':
            case 'FactionManager':
            case 'Flags':
            case 'LevelMapValues':
            case 'MultiEffectInfos':
            case 'Origins':
            case 'PassiveLists':
            case 'ProgressionDescriptions':
            case 'Progressions':
            case 'Races':
            case 'SkillLists':
            case 'SpellLists':
            case 'Tags':
            case 'Templates':
            case 'TooltipExtraTexts':
                return 'gameplay';

            // Core/meta/config
            case 'Config':
            case 'Dependencies':
            case 'MetaData':
                return 'meta';

            default:
                return 'unknown';
        }
    }

    private function parseImg(string $type, string $filepath): void
    {
        $this->_Data[$filepath]['type'] = strtolower($type);
    }

    private function parseKhn(string $filepath): void
    {
        $this->_Data[$filepath][] = service('contentService')->read($filepath);
    }

    private function parseTxt(string $filepath): void
    {
        // Original used file(..., FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
        // Replicate via service read + explode + trim/skip empty.
        $raw  = service('contentService')->read($filepath);
        $rows = preg_split("/\r\n|\r|\n/", $raw);
        $rows = array_values(array_filter($rows, static fn($l) => $l !== ''));
        $this->_Data[$filepath] = TextParserHelper::txt2Array($rows);
    }

    /* ----------------------- Dataset builder ------------------------- */

    private function buildDataSet($moddir): void
    {
        $scanner   = new LocalizationScanner(true, false); // only /Localization; no head validation (fast)
        $langXmls  = $scanner->findLocalizationXmlsForMod($moddir);

        $parsedLangXmls = [];
        foreach ($langXmls as $xmlPath) {
            $this->parseLang($xmlPath);
            $parsedLangXmls[$xmlPath] = true;
        }

        $directory = new \RecursiveDirectoryIterator(
            $moddir,
            \RecursiveDirectoryIterator::FOLLOW_SYMLINKS
        );
        $iterator = new \RecursiveIteratorIterator($directory);

        foreach ($iterator as $info) {
            if (!$info->isFile()) {
                continue;
            }

            $path = $info->getPathname();
            $ext  = strtolower($info->getExtension());

            if ($ext === 'xml' && isset($parsedLangXmls[$path])) {
                continue;
            }

            switch ($ext) {
                case 'lsx':
                    $this->parseLsx($path);
                    break;
                case 'khn':
                    $this->parseKhn($path);
                    break;
                case 'txt':
                    $this->parseTxt($path);
                    break;
                case 'png':
                case 'dds':
                    $this->parseImg($ext, $path);
                    break;

            }
        }
    }

    /* ----------------------- LSX region peek ------------------------- */

    /**
     * Read only the first N bytes of an LSX file and extract <region id="...">.
     * Returns the id string or null if not found.
     */
    public function detectRegionFromFileHead(string $lsxPath, int $bytes = 8192): ?string
    {
        try {
            $head = service('contentService')->read($lsxPath);
            if ($bytes > 0 && strlen($head) > $bytes) {
                $head = substr($head, 0, $bytes);
            }
        } catch (\Throwable $e) {
            return null;
        }

        if ($head === '' || $head === null) {
            return null;
        }

        if (preg_match('/<region\s+id\s*=\s*"([^"]+)"/i', $head, $m)) {
            return $m[1];
        }
        return null;
        // Note: you can whitelist/normalize known regions here if desired.
    }

    /* ----------------------- Accessors / Mutators -------------------- */

    public function getLang(): array
    {
        return $this->_Lang;
    }

    public function getData(): array
    {
        return $this->_Data;
    }

    /** Quick lookup: files grouped by detected region (for LSX). */
    public function getRegionIndex(): array
    {
        // ensure deterministic order
        foreach ($this->_RegionIndex as &$list) {
            sort($list, SORT_STRING);
        }
        ksort($this->_RegionIndex, SORT_NATURAL | SORT_FLAG_CASE);
        return $this->_RegionIndex;
    }

    public function setDataTxt($filepath, $data): void
    {
        service('contentService')->write($filepath, preg_replace("/\r\n?/", "\n", $data), true);
        $this->_Data[$filepath][0] = service('contentService')->read($filepath);
    }

    public function setDataBySearch($newvalue, $locations): void
    {
        foreach ($locations as $path) {
            ArrayHelper::setNestedValue($this->_Data, $path, $newvalue);
            $this->saveFile($path[0]);
        }
    }

    public function setLangBySearch($newvalue, $locations): void
    {
        foreach ($locations as $path) {
            ArrayHelper::setNestedValue($this->_Lang, $path, $newvalue);
            $this->saveFile($path[0]);
        }
    }

    public function searchData($key)
    {
        return ArrayHelper::recursiveSearchKeyMap($key, $this->_Data);
    }

    public function searchLang($key)
    {
        return ArrayHelper::recursiveSearchKeyMap($key, $this->_Lang);
    }

    public function findNReplace($oldvalue, $newvalue): void
    {
        $searchResults = $this->searchData($oldvalue);
        if (!empty($searchResults) && end($searchResults[0]) === "handle" && strpos($newvalue, ";") !== false) {
            $searchDup[] = $searchResults[0];
            $searchDup[0][count($searchResults[0]) - 1] = 'version';
            $handleInfo = explode(";", $newvalue);
            $this->setDataBySearch($handleInfo[0], $searchResults);
            $this->setDataBySearch($handleInfo[1], $searchDup);
        } else {
            $this->setDataBySearch($newvalue, $this->searchData($oldvalue));
        }

        $langResults = $this->searchLang($oldvalue);
        if (!empty($langResults) && end($langResults[0]) === "contentuid" && strpos($newvalue, ";") !== false) {
            $versionDup[] = $langResults[0];
            $versionDup[0][count($langResults[0]) - 1] = 'version';
            $contentuidInfo = explode(";", $newvalue);
            $this->setLangBySearch($contentuidInfo[0], $langResults);
            $this->setLangBySearch($contentuidInfo[1], $versionDup);
        } else {
            $this->setLangBySearch($newvalue, $this->searchLang($oldvalue));
        }
    }

    /* ----------------------- Renderers / Writers --------------------- */

    public function draw($file, $term = null)
    {
        switch (substr($file, -3)) {
            case "xml":
                return $this->writeLang($file);
            case "lsx":
                return FormatHelper::wrapEditableContent($this->writeXml($file), $term);
            case "txt":
                return FormatHelper::wrapEditableContent($this->writeTxt($file), $term);
            case "khn":
                return FormatHelper::wrapEditableContent($this->writeKhn($file), $term);
            case "png":
            case "DDS":
            case "dds":
                return $this->displayImg($file);
            default:
                return "<div class='display flash-red'>Unsupported file type: " . htmlspecialchars($file) . "</div>";
        }
    }

    public function saveFile($file, $data = false)
    {
        if (is_null($data)) {
            $data = $this->_Data[$file];
        } elseif (!is_array($data)) {
            $data = html_entity_decode($data, ENT_QUOTES | ENT_XML1);
        }
        switch (strtolower(substr($file, -3))) {
            case "xml":
                $this->writeLang($file, $data, true);
                break;
            case "lsx":
                $this->writeXml($file, $data, true);
                break;
            case "txt":
                $this->writeTxtRaw($file, $data, true);
                break;
            case "khn":
                $this->setDataTxt($file, $data);
                break;
            case "png":
            case "dds":
                return $this->displayImg($file);
        }
    }

    public function writeKhn($filepath, $save = false)
    {
        $lines = "";
        foreach ($this->_Data[$filepath] as $txt) {
            $lines .= $txt;
        }
        if ($save) {
            service('contentService')->write($filepath, preg_replace("/\r\n?/", "\n", $lines), true);
        }
        return $lines;
    }

    public function displayImg($filepath, $save = false)
    {
        // ImageMagick reads directly from path; path containment handled by caller/UI
        $img = new \Imagick($filepath);
        $img->setImageFormat('png');
        $imageDataUri = 'data:' . $img->getFormat() . ';base64,' . base64_encode($img->getimageblob());
        return '<img class="dynImg" src="' . $imageDataUri . '" alt="Dynamic Image">';
    }

    public function writeXml($filepath, $data = false, $save = false)
    {
        if ($data) {
            $xml_string = XmlHelper::createXML('save', XmlHelper::createArray($data)['save'])->saveXML();
        } else {
            $xml_string = XmlHelper::createXML('save', $this->_Data[$filepath]['save'])->saveXML();
        }
        $dom = XmlHelper::createFormattedDOM(XmlHelper::minifyEmptyTags($xml_string));
        if ($save) {
            $xml = $dom->saveXML();
            service('contentService')->write($filepath, html_entity_decode($xml), true);
        }
        return $dom->saveXML();
    }

    public function writeLang($filepath, $data = false, $save = false)
    {
        if (FilePathHelper::testFile($filepath)) {
            if ($data) {
                $xml_string = XmlHelper::createXML('contentList', $data)->saveXML();
            } else {
                $xml_string = XmlHelper::createXML('contentList', $this->_Lang[$filepath]['contentList'])->saveXML();
            }
            $dom = XmlHelper::createFormattedDOM(XmlHelper::minifyEmptyTags($xml_string));
            if ($save) {
                $xml = $dom->saveXML();
                $xml = XmlHelper::injectXmlNamespaces($xml, 'contentList', [
                    'xsd' => 'http://www.w3.org/2001/XMLSchema',
                    'xsi' => 'http://www.w3.org/2001/XMLSchema-instance'
                ]);
                service('contentService')->write($filepath, $xml, true);
            }
            return $dom->saveXML();
        }
        return "Empty";
    }

    public function writeTxt($filepath, $save = false)
    {
        $data = TextParserHelper::array2Txt($this->_Data[$filepath]);
        if ($save) {
            service('contentService')->write($filepath, preg_replace("/\r\n?/", "\n", $data), true);
        }
        return $data;
    }

    public function writeTxtRaw($filepath, $txt, $save = false)
    {
        $data = preg_replace("/\r\n?/", "\n", TextParserHelper::array2Txt(TextParserHelper::string2Array($txt)));
        if ($save) {
            service('contentService')->write($filepath, $data, true);
        }
        return $data;
    }

    /* ----------------------- Array helpers (updated API) ------------- */

    public function lsxPrimaryDialog(string $lsxPath): array
    {
        $lsxXml    = service('contentService')->read($lsxPath);
        $handleMap = $this->buildDefaultHandleMap(false); // prefer EN if available

        // Always return primary; if no Lang found, attrs['text'] becomes null
        return $this->dialog->primaryDialogFromHandles($lsxXml, $handleMap ?: []);
    }

    public function lsxSecondaryDialog(string $lsxPath): array
    {
        // No localization needed
        $lsxXml = service('contentService')->read($lsxPath);
        return $this->dialog->secondaryDialog($lsxXml);
    }

    public function lsxBothDialog(string $lsxPath): array
    {
        $lsxXml    = service('contentService')->read($lsxPath);
        $handleMap = $this->buildDefaultHandleMap(false);

        // Always return both; fall back to empty map if none found
        return $this->dialog->bothDialogFromHandles($lsxXml, $handleMap ?: []);
    }

    public function lsxRebuildFromPrimaryDialog(string $primaryJson): string
    {
        // Still returns XML text
        return $this->dialog->buildLsxFromPrimaryDialog($primaryJson);
    }

    public function lsxPrimaryDialogFromStrings(string $lsxXml, string $englishXml): array
    {
        // Prefer mod’s default Lang; fall back to provided englishXml if none found
        $handleMap = $this->buildDefaultHandleMap(false);
        if ($handleMap) {
            return $this->dialog->primaryDialogFromHandles($lsxXml, $handleMap);
        }
        return $this->dialog->primaryDialog($lsxXml, $englishXml); // DialogHelper returns array
    }

    public function lsxSecondaryDialogFromString(string $lsxXml): array
    {
        return $this->dialog->secondaryDialog($lsxXml);
    }

}
?>
