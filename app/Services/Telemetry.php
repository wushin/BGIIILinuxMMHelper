<?php
namespace App\Services;

final class Telemetry
{
    /** Simple timer helper: returns [token, start] */
    public function start(string $name): array
    {
        return [$name, hrtime(true)];
    }

    /** End timer and log info */
    public function end(array $token, array $ctx = []): void
    {
        [$name, $t0] = $token;
        $ms = (hrtime(true) - $t0) / 1e6;
        log_message('info', 'telemetry {name} {ms}ms {ctx}', [
            'name' => $name,
            'ms'   => number_format($ms, 3),
            'ctx'  => json_encode($ctx, JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** Lightweight counter log */
    public function bump(string $name, array $ctx = []): void
    {
        log_message('info', 'metric {name} {ctx}', [
            'name' => $name,
            'ctx'  => json_encode($ctx, JSON_UNESCAPED_SLASHES),
        ]);
    }
}
?>
