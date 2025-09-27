<?php
namespace App\Services;

/**
 * Recursively builds a directory tree: dirs first, A→Z.
 * - Skips dot entries.
 * - Ignores pure ".lsf" files (keeps ".lsf.lsx").
 */
class DirectoryScanner
{
    /**
     * @return array<int, array{
     *   name:string,
     *   isDir:bool,
     *   rel:string,
     *   ext?:string,
     *   children?:array
     * }>
     */
    public function tree(string $abs, string $baseRel = ''): array
    {
        $entries = @scandir($abs) ?: [];
        $dirs  = [];
        $files = [];

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..' || $name[0] === '.') {
                continue;
            }

            $p   = $abs . DIRECTORY_SEPARATOR . $name;
            $rel = ltrim($baseRel . '/' . $name, '/');

            if (is_dir($p)) {
                $dirs[] = [
                    'name'     => $name,
                    'isDir'    => true,
                    'rel'      => $rel . '/',
                    'children' => $this->tree($p, $rel),
                ];
                continue;
            }

            // Ignore pure .lsf (binary), not .lsf.lsx
            if (preg_match('/\.lsf$/i', $name)) {
                continue;
            }
            if (preg_match('/\.loca$/i', $name)) {
                continue;
            }

            $files[] = [
                'name'  => $name,
                'isDir' => false,
                'ext'   => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
                'rel'   => $rel,
            ];
        }

        usort($dirs,  fn($a,$b)=> strcasecmp($a['name'],$b['name']));
        usort($files, fn($a,$b)=> strcasecmp($a['name'],$b['name']));

        return array_merge($dirs, $files);
    }
}
?>
