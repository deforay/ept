<?php

class Pt_Reports_ChartService
{
    private static ?Pt_Reports_ChartRenderer_RendererInterface $renderer = null;

    /**
     * Initialize the chart service. Call once at the start of a report batch.
     * Renders via Node.js + Chart.js + skia-canvas (chart-render.js).
     */
    public static function initialize(): void
    {
        self::$renderer = self::buildRenderer();
    }

    public static function shutdown(): void
    {
        self::$renderer = null;
    }

    /**
     * Render a chart to a PNG file.
     *
     * @param array  $config    Chart configuration
     * @param string $outputDir Directory to write the PNG
     * @return string Absolute path to the generated PNG file
     */
    public static function render(array $config, string $outputDir): string
    {
        if (self::$renderer === null) {
            self::$renderer = self::buildRenderer();
        }
        return self::$renderer->render($config, $outputDir);
    }

    // --- Internal ---

    private static function buildRenderer(): Pt_Reports_ChartRenderer_RendererInterface
    {
        $scriptPath = defined('ROOT_PATH')
            ? ROOT_PATH . DIRECTORY_SEPARATOR . 'chart-render.js'
            : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'chart-render.js';

        if (!file_exists($scriptPath)) {
            throw new RuntimeException(
                "Chart renderer script not found at $scriptPath. " .
                "Expected repository-root chart-render.js to be present."
            );
        }
        if (!self::nodeAvailable()) {
            throw new RuntimeException(
                'Node.js is required for report chart rendering but was not found on PATH. ' .
                'Install Node.js (Ubuntu: `apt install nodejs npm`; Docker: use a base image with Node) ' .
                'and run `npm ci` at the repo root.'
            );
        }

        return new Pt_Reports_ChartRenderer_ChartJsNode($scriptPath);
    }

    private static function nodeAvailable(): bool
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(['which', 'node'], $descriptors, $pipes);
        if (!is_resource($process)) {
            return false;
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($process) === 0;
    }
}
