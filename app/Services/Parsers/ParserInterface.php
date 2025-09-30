<?php
namespace App\Services\Parsers;

interface ParserInterface
{
    /** Parse editor-facing data from on-disk bytes */
    public function read(string $bytes, string $absPath): array;

    /**
     * Produce on-disk bytes from editor data.
     * $raw:  raw text blob (e.g., TXT/XML)
     * $json: structured JSON (e.g., LSX-as-JSON)
     * Return: bytes to write.
     */
    public function write(?string $raw, ?string $json, string $absPath): string;
}
?>
