<?php

interface Pt_Reports_ChartRenderer_RendererInterface
{
    /**
     * Render a chart to a PNG file.
     *
     * @param array  $config    Chart configuration array
     * @param string $outputDir Directory to write the temp PNG file
     * @return string Absolute path to the generated PNG file
     */
    public function render(array $config, string $outputDir): string;
}
