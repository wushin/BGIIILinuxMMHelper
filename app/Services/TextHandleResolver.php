<?php
namespace App\Services;

final class TextHandleResolver
{
    /** Return [resolvedText|null, matchedHandle|null, version|null] */
    public function resolve(string $value, array $map): array
    {
        // Match: h<alnum> optionally followed by ;<digits>
        if (!preg_match('~^(h[0-9a-z]+)(?:;(\d+))?$~i', $value, $m)) {
            return [null, null, null]; // not a handle string
        }
        $handle  = $m[1];
        $version = isset($m[2]) ? (string)$m[2] : null;

        if ($version !== null && isset($map["{$handle};{$version}"])) {
            return [$map["{$handle};{$version}"], $handle, $version];
        }
        if (isset($map[$handle])) {
            return [$map[$handle], $handle, null];
        }
        return [null, $handle, $version];
    }
}
?>
