<?php

namespace App\Helpers;

class FilePathHelper
{
    public static function getUrlPath(string $path, string $slug): string
    {
        return $path . "/" . $slug;
    }

    public static function getAbsPath(string $path, string $slug): string
    {
        return FCPATH . $path . "/" . $slug;
    }

    public static function getFileUrlPath(string $path, string $key): string
    {
        return "/display/" . $path . "/" . preg_replace('/' . str_replace("/", "\/", FCPATH) . '(.*?)\//', '', $key);
    }

    public static function getFileSavePath(string $path, string $key): string
    {
        return FCPATH . $path . "/" . preg_replace('/' . str_replace("/", "\/", FCPATH) . '(.*?)\//', '', $key);
    }

    public static function getFileName(string $path, string $key): string
    {
        return preg_replace('/' . str_replace("/", "\/", FCPATH) . $path . '\/(.*?)\//', '', $key);
    }

    public static function getAbsFileName(string $path, string $slug, string $key): string
    {
        return FCPATH . $path . "/" . addslashes(urldecode($slug)) . "/" . $key;
    }

    public static function testFile(string $file): bool
    {
        $lines = file($file);
        return count($lines) > 3;
    }
}

