<?php

namespace App\Helpers;

class BG3DirectoryHelper
{
    protected static array $ignore = ['..', '.', '.git', 'ToDo', '.gitignore', 'tools', 'BG3LinuxHelper'];

    public static function getMyMods(): array
    {
        return self::scanDirectory(getenv('bg3LinuxHelper.MyMods'));
    }

    public static function getAllMods(): array
    {
        return self::scanDirectory(getenv('bg3LinuxHelper.AllMods'));
    }

    public static function getGameData(): array
    {
        return self::scanDirectory(getenv('bg3LinuxHelper.GameData'));
    }

    public static function getAll(): array
    {
        return [
            'MyMods' => self::getMyMods(),
            'AllMods' => self::getAllMods(),
            'GameData' => self::getGameData(),
        ];
    }

    protected static function scanDirectory(string $path): array
    {
        return is_dir($path) ? array_values(array_diff(scandir($path), self::$ignore)) : [];
    }
}
?>
