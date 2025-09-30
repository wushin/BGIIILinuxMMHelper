<?php
namespace App\Services\Parsers;

use App\Services\MimeGuesser;
use App\Services\LsxService;

final class ParserFactory
{
    public function __construct(
        private MimeGuesser $mime,
        private LsxService $lsx
    ) {}

    public function forPath(string $absPath): ParserInterface
    {
        $kind = $this->mime->kindFromPath($absPath);

        return match ($kind) {
            'text' => new TxtParser(),
            'xml'  => new XmlParser(),
            'lsx'  => new LsxParser($this->lsx),
            default => new PassthroughParser(),
        };
    }
}
?>
