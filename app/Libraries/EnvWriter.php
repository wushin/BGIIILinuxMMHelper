<?php
/**
 * This file is part of BGIII Mod Manager Linux Helper.
 *
 * Copyright (C) 2025 Wushin.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Libraries;

final class EnvWriter
{
    private string $envPath;

    public function __construct(?string $envPath = null)
    {
        $this->envPath = $envPath ?? (ROOTPATH . '.env');
    }

    /**
     * Upsert multiple keys in the .env file.
     * @param array<string,string> $pairs key => value
     */
    public function setMany(array $pairs): void
    {
        $lines = file_exists($this->envPath)
            ? file($this->envPath, FILE_IGNORE_NEW_LINES)
            : [];

        // Backup
        $backup = $this->envPath . '.bak';
        if ($lines) { @copy($this->envPath, $backup); }

        // Normalize end newline for new files
        if ($lines === false) $lines = [];

        foreach ($pairs as $key => $value) {
            $keyPattern = '~^(\s*' . preg_quote($key, '~') . '\s*=\s*)(.*)$~';
            $wrappedVal = $this->wrapValue($value);

            $replaced = false;
            foreach ($lines as $i => $line) {
                if (preg_match($keyPattern, $line)) {
                    $lines[$i] = $key . ' = ' . $wrappedVal;
                    $replaced = true;
                }
            }
            if (!$replaced) {
                // Add a small section header if file was empty
                if (empty($lines)) {
                    $lines[] = '# BG3 Linux MM Helper settings';
                }
                $lines[] = $key . ' = ' . $wrappedVal;
            }
        }

        // Ensure trailing newline
        $contents = implode(PHP_EOL, $lines);
        if (!str_ends_with($contents, PHP_EOL)) $contents .= PHP_EOL;

        $fh = fopen($this->envPath, 'c+');
        if ($fh === false) throw new \RuntimeException('Cannot open .env for writing');

        // lock, truncate, write
        if (!flock($fh, LOCK_EX)) { fclose($fh); throw new \RuntimeException('Cannot lock .env'); }
        ftruncate($fh, 0);
        fwrite($fh, $contents);
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    private function wrapValue(string $v): string
    {
        // Quote if contains spaces, #, :, =, or starts/ends with quotes
        if ($v === '' || preg_match('/\s|#|=|:|^"|\'|"$|\'$/', $v)) {
            // escape inner quotes
            $v = str_replace(['"', "\n", "\r"], ['\"', ' ', ' '], $v);
            return '"' . $v . '"';
        }
        return $v;
    }
}

