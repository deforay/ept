<?php

declare(strict_types=1);

namespace EptTestHarness;

/**
 * Runs scheduled-jobs/evaluate-shipments.php as a subprocess. Never loads any app class.
 */
final class Evaluator
{
    public function __construct(private Config $config) {}

    public function evaluate(int $shipmentId): void
    {
        $php = $this->config->phpBinary;
        $script = $this->config->repoRoot . '/scheduled-jobs/evaluate-shipments.php';
        $cmd = sprintf(
            'APPLICATION_ENV=%s %s %s -s %d 2>&1',
            escapeshellarg($this->config->env),
            escapeshellarg($php),
            escapeshellarg($script),
            $shipmentId
        );

        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);

        if ($exit !== 0) {
            $tail = implode("\n", array_slice($output, -25));
            throw new \RuntimeException("evaluate-shipments.php failed (exit $exit). Tail:\n$tail");
        }
    }
}
