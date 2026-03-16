<?php

use mitoteam\jpgraph\MtJpGraph;

class Pt_Reports_ChartRenderer_JpGraph implements Pt_Reports_ChartRenderer_RendererInterface
{
    private static bool $loaded = false;

    private function ensureLoaded(): void
    {
        if (!self::$loaded) {
            MtJpGraph::load(['bar', 'line', 'pie'], true);
            self::$loaded = true;
        }
    }

    public function render(array $config, string $outputDir): string
    {
        $this->ensureLoaded();

        $type = $config['type'] ?? 'bar';
        $filename = ($config['filename'] ?? 'chart') . '.png';
        $outputPath = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        return match ($type) {
            'bar' => $this->renderBar($config, $outputPath),
            'groupedBar' => $this->renderGroupedBar($config, $outputPath),
            'stackedBar' => $this->renderStackedBar($config, $outputPath),
            'pie' => $this->renderPie($config, $outputPath),
            'bar+line' => $this->renderBarLine($config, $outputPath),
            default => throw new InvalidArgumentException("Unknown chart type: $type"),
        };
    }

    private function renderBar(array $config, string $outputPath): string
    {
        $width = $config['width'] ?? 700;
        $height = $config['height'] ?? 400;
        $datasets = $config['datasets'] ?? [];
        $dataset = $datasets[0] ?? [];

        $graph = new Graph($width, $height, 'auto');
        $graph->SetScale('textlin');
        $graph->img->SetAntiAliasing();

        $theme = new UniversalTheme();
        $graph->SetTheme($theme);
        $graph->SetBox(false);
        $graph->ygrid->SetFill(false);

        $this->applyMargins($graph, $config);
        $this->applyTitle($graph, $config);
        $this->applyAxes($graph, $config);

        $b1plot = new BarPlot($dataset['data'] ?? []);

        $colors = $dataset['colors'] ?? [];
        $borderColor = $dataset['borderColor'] ?? null;

        if (count($colors) > 1) {
            $b1plot->SetFillColor($colors);
        } elseif (count($colors) === 1) {
            $b1plot->SetFillColor($colors[0]);
        }

        if ($borderColor !== null) {
            $b1plot->SetColor($borderColor);
        }

        if (!empty($dataset['label'])) {
            $b1plot->SetLegend($dataset['label']);
        }

        if (!empty($dataset['showValues'])) {
            $b1plot->value->Show();
            $b1plot->value->SetFormat($dataset['valueFormat'] ?? '%d');
            $b1plot->value->SetColor($dataset['valueColor'] ?? 'black');
        }

        $gbplot = new GroupBarPlot([$b1plot]);
        $graph->Add($gbplot);

        $this->applyLegend($graph, $config);

        return $this->stroke($graph, $outputPath);
    }

    private function renderGroupedBar(array $config, string $outputPath): string
    {
        $width = $config['width'] ?? 700;
        $height = $config['height'] ?? 400;
        $datasets = $config['datasets'] ?? [];

        $graph = new Graph($width, $height, 'auto');
        $graph->SetScale('textlin');
        $graph->img->SetAntiAliasing();

        $theme = new UniversalTheme();
        $graph->SetTheme($theme);
        $graph->SetBox(false);
        $graph->ygrid->SetFill(false);

        $this->applyMargins($graph, $config);
        $this->applyTitle($graph, $config);
        $this->applyAxes($graph, $config);

        $plots = [];
        foreach ($datasets as $ds) {
            $plot = new BarPlot($ds['data'] ?? []);

            $colors = $ds['colors'] ?? [];
            if (!empty($colors)) {
                $color = $colors[0];
                $plot->SetFillColor($color);
                $plot->SetColor($color);
            }

            if (!empty($ds['label'])) {
                $plot->SetLegend($ds['label']);
            }

            if (!empty($ds['showValues'])) {
                $plot->value->Show();
                $plot->value->SetFormat($ds['valueFormat'] ?? '%d');
                $plot->value->SetColor($ds['valueColor'] ?? 'black');
            }

            if (!empty($ds['barWidth'])) {
                $plot->SetAbsWidth($ds['barWidth']);
            }

            $plots[] = $plot;
        }

        $gbplot = new GroupBarPlot($plots);
        if (!empty($config['barGroupWidth'])) {
            $gbplot->SetWidth($config['barGroupWidth']);
        }
        $graph->Add($gbplot);

        $this->applyLegend($graph, $config);

        return $this->stroke($graph, $outputPath);
    }

    private function renderStackedBar(array $config, string $outputPath): string
    {
        $width = $config['width'] ?? 700;
        $height = $config['height'] ?? 400;
        $datasets = $config['datasets'] ?? [];

        $graph = new Graph($width, $height);
        $graph->SetScale('textlin');
        $graph->SetShadow();
        $graph->img->SetAntiAliasing();

        $this->applyMargins($graph, $config);
        $graph->ygrid->SetFill(false);
        $this->applyTitle($graph, $config);
        $this->applyAxes($graph, $config);

        $plots = [];
        foreach ($datasets as $ds) {
            $plot = new BarPlot($ds['data'] ?? []);

            $colors = $ds['colors'] ?? [];
            if (!empty($colors)) {
                $color = $colors[0];
                $plot->SetFillColor($color);
                $plot->SetColor($color);
            }

            if (!empty($ds['label'])) {
                $plot->SetLegend($ds['label']);
            }

            $plot->SetShadow();
            $plots[] = $plot;
        }

        $accplot = new AccBarPlot($plots);

        if (!empty($config['showValues'])) {
            $accplot->value->SetColor('black');
            $accplot->value->Show();
            $accplot->value->SetAngle($config['valueAngle'] ?? 45);
            $accplot->value->SetAlign($config['valueHAlign'] ?? 'left', $config['valueVAlign'] ?? 'bottom');
            $accplot->value->SetFormat($config['valueFormat'] ?? '%01.0f');
        }

        $graph->Add($accplot);

        $this->applyLegend($graph, $config);

        return $this->stroke($graph, $outputPath);
    }

    private function renderPie(array $config, string $outputPath): string
    {
        $width = $config['width'] ?? 700;
        $height = $config['height'] ?? 400;
        $slices = $config['slices'] ?? [];

        $graph = new PieGraph($width, $height);
        $graph->SetShadow();
        $graph->img->SetAntiAliasing();

        if (!empty($config['title'])) {
            $graph->title->Set($config['title']);
        }

        $p1 = new PiePlot($slices['data'] ?? []);
        $p1->value->SetColor('black');

        if (!empty($slices['labels'])) {
            $p1->SetLegends($slices['labels']);
        }

        if (!empty($slices['colors'])) {
            $p1->SetSliceColors($slices['colors']);
        }

        $graph->Add($p1);

        $legend = $config['legend'] ?? [];
        $graph->legend->SetPos(
            $legend['x'] ?? 0.5,
            $legend['y'] ?? 0.97,
            $legend['hAlign'] ?? 'center',
            $legend['vAlign'] ?? 'bottom'
        );
        if (!empty($legend['columns'])) {
            $graph->legend->SetColumns($legend['columns']);
        }
        if (!empty($legend['shadow'])) {
            $graph->legend->SetShadow($legend['shadow']);
        }

        return $this->stroke($graph, $outputPath);
    }

    private function renderBarLine(array $config, string $outputPath): string
    {
        $width = $config['width'] ?? 900;
        $height = $config['height'] ?? 400;
        $datasets = $config['datasets'] ?? [];
        $y2 = $config['y2Axis'] ?? [];

        $graph = new Graph($width, $height, 'auto');
        $graph->SetScale('textlin');
        $graph->SetY2Scale('lin', $y2['min'] ?? 0, $y2['max'] ?? 100);
        $graph->SetY2OrderBack(false);
        $graph->SetFrame(false);

        $this->applyMargins($graph, $config);
        $graph->ygrid->SetFill(false);
        $this->applyTitle($graph, $config);

        // X-axis
        $xAxis = $config['xAxis'] ?? [];
        if (!empty($xAxis['labels'])) {
            $graph->xaxis->SetTickLabels($xAxis['labels']);
        }
        $graph->xaxis->SetTextLabelInterval(1);
        $graph->xaxis->HideLine(false);
        $graph->xaxis->HideTicks(false, false);
        $graph->xaxis->SetLabelAlign('center');
        $graph->xaxis->SetFont(FF_DEFAULT, FS_BOLD, 11);

        $labelAngle = $xAxis['labelAngle'] ?? 0;
        if ($labelAngle > 0) {
            $graph->xaxis->SetFont(FF_DEFAULT, FS_BOLD, 9);
            $graph->xaxis->SetLabelAngle($labelAngle);
        }

        // Y-axis
        $yAxis = $config['yAxis'] ?? [];
        if (!empty($yAxis['title'])) {
            $graph->yaxis->title->Set($yAxis['title'], 'center');
            $graph->yaxis->title->SetMargin($yAxis['titleMargin'] ?? 20);
            $graph->yaxis->title->SetFont(FF_DEFAULT, FS_BOLD, 12);
        }
        $graph->yscale->SetAutoMin(0);
        $graph->yaxis->HideLine(false);
        $graph->yaxis->HideTicks(false, false);

        // Y2-axis
        if (!empty($y2['title'])) {
            $graph->y2axis->title->Set($y2['title']);
            $graph->y2axis->title->SetMargin($y2['titleMargin'] ?? 30);
            $graph->y2axis->title->SetFont(FF_DEFAULT, FS_BOLD, 12);
        }
        if (!empty($y2['labelFormat'])) {
            $graph->y2axis->SetLabelFormat($y2['labelFormat']);
        }
        $graph->y2axis->HideLine(false);
        $graph->y2axis->HideTicks(false, false);
        $graph->y2scale->SetAutoMax($y2['max'] ?? 100);
        $graph->y2scale->SetAutoMin($y2['min'] ?? 0);

        // Separate bar and line datasets
        $barPlots = [];
        foreach ($datasets as $ds) {
            $dsType = $ds['type'] ?? 'bar';
            $colors = $ds['colors'] ?? [];
            $color = $colors[0] ?? 'gray';

            if ($dsType === 'line') {
                $linePlot = new LinePlot($ds['data'] ?? []);
                $graph->AddY2($linePlot);
                $linePlot->SetBarCenter(true);
                $linePlot->SetWeight(0);
                $linePlot->SetLineWeight(0);

                $pointStyle = $ds['pointStyle'] ?? 'circle';
                $markType = match ($pointStyle) {
                    'circle' => MARK_FILLEDCIRCLE,
                    'square' => MARK_FILLEDSQUARE,
                    default => MARK_FILLEDCIRCLE,
                };
                $linePlot->mark->SetType($markType, '', 4.0);
                $linePlot->mark->SetWeight(10);
                $linePlot->mark->SetWidth(14);
                $linePlot->mark->setColor($color);
                $linePlot->mark->setFillColor($color);
                $linePlot->mark->SetSize(5);

                $valueFormat = $ds['valueFormat'] ?? '%d%%';
                $linePlot->value->SetFormat($valueFormat);
                $linePlot->value->SetMargin(14);
                $linePlot->value->Show();
                $linePlot->value->SetFont(FF_DEFAULT, FS_NORMAL, 10);
                $linePlot->value->SetColor('black');

                if (!empty($ds['label'])) {
                    $linePlot->SetLegend($ds['label']);
                }
            } else {
                $barPlot = new BarPlot($ds['data'] ?? []);
                $barPlot->SetFillColor($color);
                $barPlot->SetColor($color);
                $barPlot->SetAlign('center');

                if (!empty($ds['barWidth'])) {
                    $barPlot->SetAbsWidth($ds['barWidth']);
                }

                if (!empty($ds['label'])) {
                    $barPlot->SetLegend($ds['label']);
                }

                $barPlots[] = $barPlot;
            }
        }

        if (!empty($barPlots)) {
            $gbplot = new GroupBarPlot($barPlots);
            if (!empty($config['barGroupWidth'])) {
                $gbplot->SetWidth($config['barGroupWidth']);
            }
            $graph->Add($gbplot);
        }

        $this->applyLegend($graph, $config);

        return $this->stroke($graph, $outputPath);
    }

    // --- Shared helpers ---

    private function applyTitle(Graph|PieGraph $graph, array $config): void
    {
        if (!empty($config['title'])) {
            $graph->title->Set($config['title']);
            $titleMargin = $config['titleMargin'] ?? 10;
            $graph->title->SetMargin($titleMargin);
            $titleSize = $config['titleSize'] ?? 15;
            $graph->title->SetFont(FF_DEFAULT, FS_BOLD, $titleSize);
        }
    }

    private function applyAxes(Graph $graph, array $config): void
    {
        $xAxis = $config['xAxis'] ?? [];
        $yAxis = $config['yAxis'] ?? [];

        if (!empty($xAxis['labels'])) {
            $graph->xaxis->SetTickLabels($xAxis['labels']);
        }
        if (!empty($xAxis['title'])) {
            $graph->xaxis->title->Set($xAxis['title']);
            $graph->xaxis->SetTitleMargin($xAxis['titleMargin'] ?? 30);
        }
        if (!empty($xAxis['labelAngle'])) {
            $graph->xaxis->SetLabelAngle($xAxis['labelAngle']);
        }
        if (isset($xAxis['labelMargin'])) {
            $graph->xaxis->SetLabelMargin($xAxis['labelMargin']);
        }

        if (!empty($yAxis['title'])) {
            $graph->yaxis->title->Set($yAxis['title'], 'center');
            $graph->yaxis->SetTitleMargin($yAxis['titleMargin'] ?? 30);
        }

        $graph->yaxis->HideLine(false);
        $graph->yaxis->HideTicks(false, false);
        $graph->xaxis->SetTickSide(SIDE_DOWN);
        $graph->yaxis->SetTickSide(SIDE_LEFT);
    }

    private function applyMargins(Graph $graph, array $config): void
    {
        $margins = $config['margins'] ?? null;
        if ($margins !== null) {
            $graph->img->SetMargin(
                $margins['left'] ?? 40,
                $margins['right'] ?? 30,
                $margins['top'] ?? 10,
                $margins['bottom'] ?? 150
            );
        }
    }

    private function applyLegend(Graph $graph, array $config): void
    {
        $legend = $config['legend'] ?? [];
        if (empty($legend)) {
            return;
        }

        $graph->legend->SetPos(
            $legend['x'] ?? 0.5,
            $legend['y'] ?? 0.98,
            $legend['hAlign'] ?? 'center',
            $legend['vAlign'] ?? 'bottom'
        );

        if (!empty($legend['columns'])) {
            $graph->legend->SetColumns($legend['columns']);
        }
        if (!empty($legend['shadow'])) {
            $graph->legend->SetShadow($legend['shadow']);
        }
        if (!empty($legend['frameWeight'])) {
            $graph->legend->SetFrameWeight($legend['frameWeight']);
        }
        if (!empty($legend['colMargin'])) {
            $graph->legend->SetVColMargin($legend['colMargin']);
        }
        if (!empty($legend['fontSize'])) {
            $graph->legend->SetFont(FF_DEFAULT, FS_BOLD, $legend['fontSize']);
        }
    }

    private function stroke(Graph|PieGraph $graph, string $outputPath): string
    {
        $graph->img->SetImgFormat('png');
        $graph->img->SetQuality(100);
        $graph->Stroke($outputPath);
        return $outputPath;
    }
}
