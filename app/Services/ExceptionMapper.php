<?php
namespace App\Services;

use App\Exceptions\InvalidPath;
use App\Exceptions\UnsupportedKind;
use App\Exceptions\ParseError;
use App\Exceptions\WriteDenied;

final class ExceptionMapper
{
    public function status(\Throwable $e): int
    {
        // Known domain exceptions
        if ($e instanceof InvalidPath)     return 404; // not found
        if ($e instanceof WriteDenied)     return 403; // forbidden
        if ($e instanceof UnsupportedKind) return 415; // unsupported media type
        if ($e instanceof ParseError)      return 422; // unprocessable entity

        // Common natives
        if ($e instanceof \InvalidArgumentException) return 400; // bad request
        if ($e instanceof \LengthException)          return 400;

        // Mongo hiccups often indicate backend trouble; pick 503 or 500
        if (str_starts_with(get_class($e), 'MongoDB\\Driver\\')) return 503;

        // Fallback: internal error
        return 500;
    }

    public function shortType(\Throwable $e): string
    {
        $fq = get_class($e);
        $pos = strrpos($fq, '\\');
        return $pos === false ? $fq : substr($fq, $pos + 1);
    }
}
?>
