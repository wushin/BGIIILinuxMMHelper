<?php

namespace App\Helpers;

use App\Helpers\FileHelper;

class FormatHelper
{
    public static function formatForm(string $path, string $slug, string $key): string
    {
        $html = "<div id='fileName'>" . FileHelper::getFileName($path, $key) . 
                " <button id=\"saveFile\" onclick=\"submitMainForm()\">Save</button>";

        if (substr($key, -3) === "xml") {
            $html .= "<button id=\"saveFile\" onclick=\"addRowToForm('mainForm')\">AddNewRow</button>";
        }

        $html .= "</div><form id=\"mainForm\">";
        $html .= "<input type=\"hidden\" name=\"fileName\" value=\"" . FileHelper::getFileSavePath($path, $key) . "\"/>";
        $html .= "<input type=\"hidden\" name=\"path\" value=\"" . $path . "\"/>";
        $html .= "<input type=\"hidden\" name=\"slug\" value=\"" . $slug . "\"/>";
        return $html;
    }

    public static function formatData(string $data, ?string $term = null): string
    {
        if (!is_null($term)) {
            $escapedTerm = htmlspecialchars($term, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
            return preg_replace(
                '/' . preg_quote($escapedTerm, '/') . '/i',
                '<span class="highlight">$0</span>',
                htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5)
            );
        } else {
            return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
        }
    }

    public static function stripQuotes(string $str): string
    {
        return trim($str, "\"'");
    }
}
?>
