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

        if ($kind === 'text') {
            $sub = service('textPeek')->classify($absPath);

            return match ($sub) {
                'txt.goal'             => new GoalsParser(),
                'txt.stats.equipment'  => new StatsEquipmentParser(),
                'txt.stats.treasure'   => new StatsTreasureParser(),
                'txt.stats.generic'    => new StatsParser(),      // your existing generic stats parser (with handle resolution)
                default                => new TxtParser(),        // plain text fallback
            };
        }

        return match ($kind) {
            'xml'  => new XmlParser(),
            'lsx'  => new LsxParser($this->lsx),
            default => new PassthroughParser(),
        };
    }

}
?>
