<?php
declare(strict_types=1);

namespace App\Services\Dialog;
use Config\LsxRegions;

/**
 * DialogSummarizer
 * Summarizes a normalized LSX Dialog tree into a compact structure for browse.php.
 */
final class DialogSummarizer
{
    /** @var array<string, array{text:string, version?:string}> */
    private array $handleMap;
    private ?string $abs;

    private array $dlg_tags;

    public function __construct(array $handleMap = [], ?string $abs = null)
    {
        $cfg = config(LsxRegions::class);
        $this->handleMap = $handleMap;
        $this->abs = $abs;
        $this->dlg_tags = $cfg->dlg_tags;
    }

    /** @param array<string,mixed>|array<int,mixed> $lsxTree */
    public function summarize(array $lsxTree): array
    {
        $root   = $this->rootNode($lsxTree);
        $dialog = $this->findFirstNodeById($root, 'dialog') ?? $root;

        $category   = $this->findAttrValue($dialog, 'category');
        $dialogUuid = $this->findAttrValue($dialog, 'UUID');
        $timelineId = $this->findAttrValue($dialog, 'TimelineId');

        $speakerList = $this->parseSpeakerList($dialog);
        $addressed   = $this->parseDefaultAddressedSpeakers($dialog);

        $parse = $this->parseNodes($dialog, $speakerList);
        $nodeMap               = $parse['nodes'];
        $problemsEdgesOrphans  = $parse['problems']['edges_orphans'];
        $problemsUnknownCtors  = $parse['problems']['constructors_bad'];
        $problemsUnmappedSpk   = $parse['problems']['speakers_unmapped'];
        $problemsFlagBadIdx    = $parse['problems']['flags_bad_param'];

        $fromAttr = [];
        foreach ($nodeMap as $u => $n) {
            if (!empty($n['isRoot'])) $fromAttr[$u] = true;
        }

        $rootOrdered = $this->collectRootNodesList($dialog);
        // Merge ordered roots: file FIFO then Root=true stragglers
        foreach (array_keys($fromAttr) as $u) {
            if (!in_array($u, $rootOrdered, true)) $rootOrdered[] = $u;
        }

        $stats = $this->computeStats($nodeMap, $speakerList);

        // Ensure stable object shapes when empty
        $nodesOut         = $this->asObjectIfEmpty($nodeMap);
        $fromAttrOut      = $this->asObjectIfEmpty($fromAttr);
        $constructorsHist = $this->asObjectIfEmpty($stats['constructors'] ?? []);

        return [
            'category'   => $category,
            'dialogUuid' => $dialogUuid,
            'timelineId' => $timelineId,
            'speakers'   => [
                'list'      => array_values($speakerList),
                'narrator'  => ['present' => true],
                'addressed' => $addressed,
            ],
            'roots'      => [
                'ordered'       => $rootOrdered,
                'fromAttribute' => $fromAttrOut,
                'fromList'      => $rootOrdered ? array_values(array_intersect($rootOrdered, $this->collectRootNodesList($dialog))) : $this->collectRootNodesList($dialog),
            ],
            'nodes'      => $nodesOut,
            'problems'   => [
                'roots'        => [
                    'missingAttribute' => array_values(array_diff($this->collectRootNodesList($dialog), array_keys($fromAttr))),
                    'missingList'      => array_values(array_diff(array_keys($fromAttr), $this->collectRootNodesList($dialog))),
                ],
                'edges'        => [ 'orphans'   => array_values(array_unique($problemsEdgesOrphans)) ],
                'constructors' => [ 'unknown'   => $problemsUnknownCtors ],
                'speakers'     => [ 'unmapped'  => $problemsUnmappedSpk ],
                'flags'        => [ 'invalidParamIndex' => $problemsFlagBadIdx ],
            ],
            'stats'      => [
                'nodes'        => $stats['nodes'],
                'roots'        => $stats['roots'],
                'endNodes'     => $stats['endNodes'],
                'edges'        => $stats['edges'],
                'speakers'     => $stats['speakers'],
                'constructors' => $constructorsHist,
            ],
        ];
    }

    // ---- helpers ----

    private function rootNode(array $n): array
    {
        if (isset($n['tag'])) return $n;
        if (isset($n[0]) && is_array($n[0])) return $n[0];
        return $n;
    }

    private function asObjectIfEmpty(array $a): array|object
    {
        return empty($a) ? (object)[] : $a;
    }

    private function findAttrValue(array $node, string $id): ?string
    {
        foreach ($node['children'] ?? [] as $c) {
            if (($c['tag'] ?? null) === 'attribute' && ($c['attr']['id'] ?? null) === $id) {
                return isset($c['attr']['value']) ? (string)$c['attr']['value'] : null;
            }
        }
        return null;
    }

    private function findFirstNodeById(array $n, string $id): ?array
    {
        $want = strtolower($id);
        if (($n['tag'] ?? '') === 'node' && strtolower((string)($n['attr']['id'] ?? '')) === $want) {
            return $n;
        }
        foreach ($n['children'] ?? [] as $c) {
            if (is_array($c)) {
                $hit = $this->findFirstNodeById($c, $id);
                if ($hit) return $hit;
            }
        }
        return null;
    }

    private function findAllNodesById(array $n, string $id): array
    {
        $acc = [];
        $this->walk($n, function(array $node) use (&$acc, $id) {
            if (($node['tag'] ?? '') === 'node' && strtolower((string)($node['attr']['id'] ?? '')) === strtolower($id)) {
                $acc[] = $node;
            }
        });
        return $acc;
    }

    private function walk(array $n, callable $fn): void
    {
        $fn($n);
        foreach ($n['children'] ?? [] as $c) {
            if (is_array($c)) $this->walk($c, $fn);
        }
    }

    private function getChildAttributes(array $n): array
    {
        $out = [];
        foreach ($n['children'] ?? [] as $c) {
            if (($c['tag'] ?? '') === 'attribute') {
                // Preserve ALL attribute fields, not just 'type' and 'value'
                $attr = $c['attr'] ?? [];
                $id = (string)($attr['id'] ?? '');
                if ($id === '') continue;
                $out[$id] = $attr; // contains id, type, value, and any extended keys (handle, version, text, ...)
            }
        }
        return $out;
    }
    /** Return the entries inside a node’s <children> block (or immediate children if none). */
    private function childBlock(array $n): array
    {
        foreach ($n['children'] ?? [] as $c) {
            if (($c['tag'] ?? '') === 'children') {
                return $c['children'] ?? [];
            }
        }
        return $n['children'] ?? [];
    }

    private function parseSpeakerList(array $root): array
    {
        $out = [];
        $speakerList = $this->findFirstNodeById($root, 'speakerlist');
        if (!$speakerList) return $out;

        foreach ($this->childBlock($speakerList) as $entry) {
            if (($entry['tag'] ?? '') !== 'node') continue;
            $attrs = $this->getChildAttributes($entry);
            $idx   = isset($attrs['index']['value']) ? (int)$attrs['index']['value'] : null;
            if ($idx === null) continue;
            $out[$idx] = [
                'index'     => $idx,
                'mappingId' => isset($attrs['SpeakerMappingId']['value']) ? (string)$attrs['SpeakerMappingId']['value'] : null,
            ];
        }
        ksort($out);
        return $out;
    }

    private function parseDefaultAddressedSpeakers(array $root): array
    {
        $out = [];
        $node = $this->findFirstNodeById($root, 'DefaultAddressedSpeakers');
        if (!$node) return $out;

        foreach ($this->childBlock($node) as $mapNode) {
            if (($mapNode['tag'] ?? '') !== 'node') continue;
            $attrs = $this->getChildAttributes($mapNode);
            $from  = isset($attrs['MapKey']['value']) ? (int)$attrs['MapKey']['value'] : null;
            $to    = isset($attrs['MapValue']['value']) ? (int)$attrs['MapValue']['value'] : null;
            if ($from === null || $to === null) continue;
            $out[] = ['fromIndex' => $from, 'toIndex' => $to];
        }
        return $out;
    }

    private function collectRootNodesList(array $root): array
    {
        $ordered = [];
        $rootNodes = $this->findAllNodesById($root, 'RootNodes');
        foreach ($rootNodes as $rn) {
            $attrs = $this->getChildAttributes($rn);
            if (isset($attrs['RootNodes']['value'])) {
                $ordered[] = (string)$attrs['RootNodes']['value'];
            }
        }
        return $ordered;
    }

    private function parseNodes(array $root, array $speakerList): array
    {
        $nodeMap = [];
        $problemsEdgesOrphans = [];
        $problemsUnknownCtors = [];
        $problemsUnmappedSpk  = [];
        $problemsFlagBadIdx   = [];

        $nodesContainer = $this->findFirstNodeById($root, 'nodes');
        if (!$nodesContainer) {
            return [
                'nodes' => $nodeMap,
                'problems' => [
                    'edges_orphans'    => $problemsEdgesOrphans,
                    'constructors_bad' => $problemsUnknownCtors,
                    'speakers_unmapped'=> $problemsUnmappedSpk,
                    'flags_bad_param'  => $problemsFlagBadIdx,
                ],
            ];
        }

        // **Important**: iterate the inner <children> block
        $nodesChildren = $this->childBlock($nodesContainer);

        // Local UUID set
        $localSet = [];
        foreach ($nodesChildren as $node) {
            if (($node['tag'] ?? '') !== 'node') continue;
            if (strtolower((string)($node['attr']['id'] ?? '')) !== 'node') continue;
            $uuid = $this->getNodeUuid($node);
            if ($uuid) $localSet[$uuid] = true;
        }

        foreach ($nodesChildren as $node) {
            if (($node['tag'] ?? '') !== 'node') continue;
            if (strtolower((string)($node['attr']['id'] ?? '')) !== 'node') continue;

            $uuid         = $this->getNodeUuid($node) ?? '';
            $attrs        = $this->getChildAttributes($node);

            $constructor  = isset($attrs['constructor']['value']) ? (string)$attrs['constructor']['value'] : null;
            $isRoot       = (isset($attrs['Root']['value'])    && $this->boolish($attrs['Root']['value']));
            $isEnd        = (isset($attrs['endnode']['value']) && $this->boolish($attrs['endnode']['value']));
            $speakerIndex = isset($attrs['speaker']['value']) ? (int)$attrs['speaker']['value'] : null;

            $groupId      = $attrs['GroupID']['value'] ?? $attrs['groupid']['value'] ?? null;
            $groupIndex   = isset($attrs['GroupIndex']['value']) ? (int)$attrs['GroupIndex']['value']
                           : (isset($attrs['groupindex']['value']) ? (int)$attrs['groupindex']['value'] : null);

            if ($constructor !== null && !in_array($constructor, $this->dlg_tags, true)) {
                $problemsUnknownCtors[] = ['uuid' => $uuid, 'value' => $constructor];
            }
            if ($speakerIndex !== null && $speakerIndex >= 0 && !isset($speakerList[$speakerIndex])) {
                $problemsUnmappedSpk[] = ['uuid' => $uuid, 'index' => $speakerIndex];
            }

            $texts = $this->parseTexts($node);

            $children = [];
            foreach ($this->findAllNodesById($node, 'child') as $child) {
                $cAttrs = $this->getChildAttributes($child);
                $cid    = isset($cAttrs['UUID']['value']) ? (string)$cAttrs['UUID']['value'] : null;
                if (!$cid) continue;
                if (isset($localSet[$cid])) {
                    $children[] = ['type' => 'local', 'uuid' => $cid];
                } else if (strcasecmp((string)$constructor, 'Nested') === 0) {
                    $children[] = ['type' => 'nested', 'uuid' => $cid];
                } else {
                    $children[] = ['type' => 'orphan', 'uuid' => $cid];
                    $problemsEdgesOrphans[] = $cid;
                }
            }

            $flags = $this->parseFlags($node, $speakerList, $problemsFlagBadIdx);

            $nodeMap[$uuid] = [
                'constructor'  => $constructor,
                'isRoot'       => $isRoot,
                'isEnd'        => $isEnd,
                'speakerIndex' => $speakerIndex,
                'groupId'      => $groupId,
                'groupIndex'   => $groupIndex,
                'texts'        => $texts,
                'children'     => $children,
                'flags'        => $flags,
            ];
        }

        return [
            'nodes' => $nodeMap,
            'problems' => [
                'edges_orphans'    => $problemsEdgesOrphans,
                'constructors_bad' => $problemsUnknownCtors,
                'speakers_unmapped'=> $problemsUnmappedSpk,
                'flags_bad_param'  => $problemsFlagBadIdx,
            ],
        ];
    }

    private function getNodeUuid(array $n): ?string
    {
        foreach ($n['children'] ?? [] as $c) {
            if (($c['tag'] ?? '') === 'attribute' && ($c['attr']['id'] ?? null) === 'UUID') {
                return isset($c['attr']['value']) ? (string)$c['attr']['value'] : null;
            }
        }
        $key = $n['attr']['key'] ?? null;
        return $key ? (string)$key : null;
    }

    private function boolish($v): bool
    {
        $s = strtolower((string)$v);
        return $s === 'true' || $s === '1' || $s === 'yes';
    }

    private function parseTexts(array $node): array
    {
        $out = [];
        $tagTexts = [];
        // Collect TagText nodes
        $this->walk($node, function(array $n) use (&$tagTexts) {
            if (($n['tag'] ?? '') === 'node' && strtolower((string)($n['attr']['id'] ?? '')) === 'tagtext') {
                $tagTexts[] = $n;
            }
        });

        foreach ($tagTexts as $tt) {
            $attrs  = $this->getChildAttributes($tt);
            $lineId = isset($attrs['LineId']['value']) ? (string)$attrs['LineId']['value'] : null;
            $stub   = isset($attrs['stub']['value']) ? $this->boolish($attrs['stub']['value']) : false;

            $handle = null;
            $text   = null;
            $version = null;

            // Shape A: attribute id="TagText" carries handle/version/text
            if (isset($attrs['TagText'])) {
                $hAttr = $attrs['TagText'];
                if (isset($hAttr['handle'])) {
                    $handle = (string)$hAttr['handle'];
                } elseif (isset($hAttr['value'])) {
                    // Some normalizers store handle in 'value'
                    $handle = (string)$hAttr['value'];
                }
                if (isset($hAttr['text'])) {
                    $text = trim((string)$hAttr['text']);
                    if ($text === '') { $text = null; }
                }
                if (isset($hAttr['version'])) {
                    $version = (string)$hAttr['version'];
                }
            }

            // Shape B: legacy nested <TranslatedString><attribute id="Handle"/></TranslatedString>
            if ($handle === null) {
                $this->walk($tt, function(array $n) use (&$handle) {
                    if (($n['tag'] ?? '') === 'node' && strtolower((string)($n['attr']['id'] ?? '')) === 'translatedstring') {
                        foreach ($n['children'] ?? [] as $a) {
                            if (($a['tag'] ?? '') !== 'attribute') continue;
                            $aid = strtolower((string)($a['attr']['id'] ?? ''));
                            if ($aid === 'handle' || $aid === 'contentuid') {
                                $handle = (string)($a['attr']['value'] ?? '');
                            }
                        }
                    }
                });
            }

            // Normalize whitespace-only text to null so handle fallback triggers
            if ($text !== null && trim($text) === '') { $text = null; }

            // Resolve text via handle map if needed
            if (($text === null || $text === '') && $handle) {
                $candidates = [$handle, strtolower($handle), strtoupper($handle)];
                foreach ($candidates as $k) {
                    if (isset($this->handleMap[$k]) && isset($this->handleMap[$k]['text'])) {
                        $text = (string)$this->handleMap[$k]['text'];
                        break;
                    }
                }
            }

            $entry = [];
            if ($lineId !== null) $entry['lineId'] = $lineId;
            if ($handle !== null) $entry['handle'] = $handle;
            if ($text   !== null) $entry['text']   = $text;
            if ($stub)            $entry['stub']   = true;
            if ($entry) $out[] = $entry;
        }
        return $out;
    }
    private function parseFlags(array $node, array $speakerList, array &$problemsFlagBadIdx): array
    {
        $checks = [];
        $sets   = [];

        // Helper to emit a flag entry; accepts a precomputed $type (from group or node)
        $emit = function (array $fnode, string $type) use (&$checks, &$sets, $speakerList, &$problemsFlagBadIdx) {
            $attrs = $this->getChildAttributes($fnode);

            $UUID  = isset($attrs['UUID']['value']) ? (string)$attrs['UUID']['value'] : null;
            $value = array_key_exists('value', $attrs) ? $this->boolish($attrs['value']['value'] ?? 'false') : null;

            // paramval is optional now; treat empty string as null
            $paramval = (isset($attrs['paramval']['value']) && $attrs['paramval']['value'] !== '')
                ? (int)$attrs['paramval']['value']
                : null;

            // Still require UUID & value to be meaningful
            if ($UUID === null || $value === null) {
                return;
            }

            // Build target: if paramval is absent, don't try to resolve — mark as 'none'
            $target = ($paramval === null)
                ? ['kind' => 'none']
                : $this->resolveParamTarget($paramval, $speakerList, $problemsFlagBadIdx);

            return [
                'type'     => $type,
                'UUID'     => $UUID,
                'value'    => $value,
                'paramval' => $paramval,
                'target'   => $target,
            ];
        };

        foreach (['checkflags' => &$checks, 'setflags' => &$sets] as $id => &$bucket) {
            foreach ($this->findAllNodesById($node, $id) as $flagsNode) {
                // Preferred: flaggroup(type=...) -> flag(...)
                $groups = $this->findAllNodesById($flagsNode, 'flaggroup');
                if (!empty($groups)) {
                    foreach ($groups as $grp) {
                        $gattrs = $this->getChildAttributes($grp);
                        $type   = isset($gattrs['type']['value']) ? (string)$gattrs['type']['value'] : 'Global';

                        foreach ($this->childBlock($grp) as $f) {
                            if (($f['tag'] ?? '') !== 'node' || strtolower((string)($f['attr']['id'] ?? '')) !== 'flag') {
                                continue;
                            }
                            $entry = $emit($f, $type);
                            if ($entry !== null) {
                                $bucket[] = $entry;
                            }
                        }
                    }
                } else {
                    // Fallback: flags listed directly under checkflags/setflags
                    foreach ($this->childBlock($flagsNode) as $f) {
                        if (($f['tag'] ?? '') !== 'node' || strtolower((string)($f['attr']['id'] ?? '')) !== 'flag') {
                            continue;
                        }
                        $attrs = $this->getChildAttributes($f);
                        $type  = isset($attrs['type']['value']) ? (string)$attrs['type']['value'] : 'Global';

                        $entry = $emit($f, $type);
                        if ($entry !== null) {
                            $bucket[] = $entry;
                        }
                    }
                }
            }
        }

        return ['checks' => $checks, 'sets' => $sets];
    }
    
    private function resolveParamTarget(int $paramval, array $speakerList, array &$problemsFlagBadIdx): array
    {
        if ($paramval === -666) return ['kind' => 'narrator'];
        if ($paramval === -1)   return ['kind' => 'none'];
        if ($paramval >= 0) {
            if (isset($speakerList[$paramval])) {
                $m = $speakerList[$paramval];
                return ['kind' => 'speaker', 'index' => $paramval, 'mappingId' => $m['mappingId'] ?? null];
            }
            $problemsFlagBadIdx[] = ['index' => $paramval];
            return ['kind' => 'invalid', 'index' => $paramval];
        }
        $problemsFlagBadIdx[] = ['index' => $paramval];
        return ['kind' => 'invalid', 'index' => $paramval];
    }

    private function computeStats(array $nodeMap, array $speakerList): array
    {
        $countNodes = 0; $roots = 0; $ends = 0; $edges = 0;
        $ctors = [];
        foreach ($nodeMap as $uuid => $n) {
            $countNodes++;
            if (!empty($n['isRoot'])) $roots++;
            if (!empty($n['isEnd']))  $ends++;
            $edges += count($n['children'] ?? []);
            $ctor = (string)($n['constructor'] ?? '');
            if ($ctor !== '') $ctors[$ctor] = ($ctors[$ctor] ?? 0) + 1;
        }
        return [
            'nodes'        => $countNodes,
            'roots'        => $roots,
            'endNodes'     => $ends,
            'edges'        => $edges,
            'speakers'     => count($speakerList),
            'constructors' => $ctors,
        ];
    }

    public function getTags(): array
    {
        return $this->dlg_tags;
    }
}
?>
