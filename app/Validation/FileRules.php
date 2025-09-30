<?php
namespace App\Validation;

final class FileRules
{
    public function validRoot(string $str): bool
    {
        return (bool) preg_match('~^(GameData|MyMods|UnpackedMods)$~', $str);
    }

    public function validSlug(string $str): bool
    {
        // single segment, no slashes or backslashes
        return $str !== '' && !preg_match('~[\\\\/]~', $str);
    }

    public function validRelPath(string $str): bool
    {
        if ($str === '') return false;
        // no .. traversal or absolute drive prefixes
        if (preg_match('#(^|[\\/])\.\.([\\/]|$)#', $str)) return false;
        if (preg_match('#^[A-Za-z]:[\\/]#', $str)) return false;
        return true;
    }
}
?>
