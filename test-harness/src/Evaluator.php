<?php

declare(strict_types=1);

namespace EptTestHarness;

/**
 * Runs scheduled-jobs/evaluate-shipments.php and generate-shipment-reports.php
 * as subprocesses. Never loads any app class.
 */
final class Evaluator
{
    public function __construct(private Config $config) {}

    public function evaluate(int $shipmentId): array
    {
        return $this->run(
            'scheduled-jobs/evaluate-shipments.php',
            ['-s', (string) $shipmentId],
            'evaluate-shipments.php'
        );
    }

    /**
     * Run scheduled-jobs/generate-shipment-reports.php for the given shipment.
     * --force regenerates even if a report already exists; no -p/-s flag, so the
     * script produces participant PDFs AND the summary in one pass.
     *
     * @return string[] subprocess stdout/stderr lines (so the caller can verify outcome).
     */
    public function generateReports(int $shipmentId): array
    {
        return $this->run(
            'scheduled-jobs/generate-shipment-reports.php',
            ['--shipment=' . $shipmentId, '--force'],
            'generate-shipment-reports.php'
        );
    }

    /**
     * @param string[] $args
     * @return string[] subprocess output (returned for callers that need to inspect it).
     */
    private function run(string $relativeScript, array $args, string $label): array
    {
        $php = $this->config->phpBinary;
        $script = $this->config->repoRoot . '/' . $relativeScript;
        $argStr = implode(' ', array_map('escapeshellarg', $args));
        $cmd = sprintf(
            'APPLICATION_ENV=%s %s %s %s 2>&1',
            escapeshellarg($this->config->env),
            escapeshellarg($php),
            escapeshellarg($script),
            $argStr
        );

        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);

        if ($exit !== 0) {
            $tail = implode("\n", array_slice($output, -25));
            throw new \RuntimeException("$label failed (exit $exit). Tail:\n$tail");
        }
        return $output;
    }
}
