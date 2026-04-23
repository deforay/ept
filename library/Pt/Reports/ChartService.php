<?php

class Pt_Reports_ChartService
{
    private static ?Pt_Reports_ChartRenderer_RendererInterface $renderer = null;

    /**
     * Initialize the chart service. Call once at the start of a report batch.
     * Uses Node.js + skia-canvas if available; falls back to JPGraph.
     */
    public static function initialize(): void
    {
        self::$renderer = self::buildRenderer();
    }

    /**
     * No-op — Node.js renderer is stateless; nothing to tear down.
     */
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

        try {
            return self::$renderer->render($config, $outputDir);
        } catch (RuntimeException $e) {
            if (self::$renderer instanceof Pt_Reports_ChartRenderer_ChartJsNode) {
                error_log('ChartService: Node renderer failed, falling back to JPGraph: ' . $e->getMessage());
                $fallback = new Pt_Reports_ChartRenderer_JpGraph();
                return $fallback->render($config, $outputDir);
            }
            throw $e;
        }
    }

    /**
     * Returns the name of the active renderer (for logging/debugging).
     */
    public static function getActiveRenderer(): string
    {
        return (self::$renderer instanceof Pt_Reports_ChartRenderer_ChartJsNode)
            ? 'ChartJsNode'
            : 'JpGraph';
    }

    // --- Internal ---

    private static function buildRenderer(): Pt_Reports_ChartRenderer_RendererInterface
    {
        $scriptPath = defined('ROOT_PATH')
            ? ROOT_PATH . DIRECTORY_SEPARATOR . 'chart-render.js'
            : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'chart-render.js';

        if (self::nodeAvailable() && file_exists($scriptPath)) {
            return new Pt_Reports_ChartRenderer_ChartJsNode($scriptPath);
        }

        return new Pt_Reports_ChartRenderer_JpGraph();
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

    private static function getConfigValue(string $name): ?string
    {
        try {
            return Pt_Commons_General::getConfig($name);
        } catch (\Throwable) {
            return null;
        }
    }
}
