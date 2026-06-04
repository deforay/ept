<?php

class Pt_Reports_ChartRenderer_ChartJsNode implements Pt_Reports_ChartRenderer_RendererInterface
{
    private string $scriptPath;

    public function __construct(string $scriptPath)
    {
        $this->scriptPath = $scriptPath;
    }

    public function render(array $config, string $outputDir): string
    {
        $type = $config['type'] ?? 'bar';
        // SVG keeps fine lines crisp regardless of how small the chart renders on
        // the PDF — TCPDF ImageSVG() embeds vectors, no raster downscaling. PNG
        // remains the default for charts whose callers haven't migrated yet.
        $format = strtolower($config['format'] ?? 'png');
        if (!in_array($format, ['png', 'svg'], true)) {
            throw new InvalidArgumentException("Unsupported chart format: $format");
        }
        $filename = ($config['filename'] ?? 'chart') . '.' . $format;
        $outputPath = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        $chartJsConfig = match ($type) {
            'bar'        => $this->buildBarConfig($config),
            'groupedBar' => $this->buildGroupedBarConfig($config),
            'stackedBar' => $this->buildStackedBarConfig($config),
            'pie'        => $this->buildPieConfig($config),
            'bar+line'   => $this->buildBarLineConfig($config),
            'boxRange'   => $this->buildBoxRangeConfig($config),
            'boxplot'    => $this->buildBoxplotConfig($config),
            default      => throw new InvalidArgumentException("Unknown chart type: $type"),
        };

        $reqWidth = $config['width'] ?? 700;
        $reqHeight = $config['height'] ?? 400;

        // Default: ensure minimum 1400px render width for crisp text at PDF display
        // sizes — fonts in builders are calibrated for 1400px wide. Charts that
        // intentionally render small (e.g. mini-charts in a strip) can opt out via
        // skipMinWidth so TCPDF embeds the natural-size PNG without downscaling.
        if ($format === 'png' && empty($config['skipMinWidth'])) {
            $minWidth = 1400;
            if ($reqWidth < $minWidth) {
                $scale = $minWidth / $reqWidth;
                $reqHeight = (int) round($reqHeight * $scale);
                $reqWidth = $minWidth;
            }
        }

        $payload = json_encode([
            'width'  => $reqWidth,
            'height' => $reqHeight,
            'format' => $format,
            'chart'  => $chartJsConfig,
        ]);

        $output = $this->callNodeScript($payload);

        if ($output === false || strlen($output) === 0) {
            throw new RuntimeException('chart-render.js produced no output');
        }

        file_put_contents($outputPath, $output);
        return $outputPath;
    }

    private function callNodeScript(string $jsonPayload): string|false
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(['node', $this->scriptPath], $descriptors, $pipes);
        if (!is_resource($process)) {
            return false;
        }

        fwrite($pipes[0], $jsonPayload);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            Pt_Commons_LoggerUtility::logError('chart-render.js error: ' . $stderr);
            return false;
        }

        return $output;
    }

    // --- Chart.js config builders ---

    private function buildBarConfig(array $config): array
    {
        $datasets = $config['datasets'] ?? [];
        $ds = $datasets[0] ?? [];
        $colors = $ds['colors'] ?? [];
        $data = $ds['data'] ?? [];

        $bgColors = (count($colors) > 1)
            ? $this->mapColors($colors)
            : $this->mapColors(array_fill(0, count($data), $colors[0] ?? '#4e79a7'));

        $borderColors = !empty($ds['borderColor'])
            ? array_fill(0, count($data), $this->mapColor($ds['borderColor']))
            : $bgColors;

        $chartDataset = [
            'data'            => $data,
            'backgroundColor' => $bgColors,
            'borderColor'     => $borderColors,
            'borderWidth'     => 1,
            'borderRadius'    => 4,
        ];
        if (!empty($ds['label'])) {
            $chartDataset['label'] = $ds['label'];
        }

        return [
            'type' => 'bar',
            'data' => ['labels' => $config['xAxis']['labels'] ?? [], 'datasets' => [$chartDataset]],
            'options' => $this->buildBarOptions($config),
        ];
    }

    private function buildGroupedBarConfig(array $config): array
    {
        $chartDatasets = [];
        foreach ($config['datasets'] ?? [] as $ds) {
            $color = $this->mapColor($ds['colors'][0] ?? '#4e79a7');
            $chartDataset = [
                'data'            => $ds['data'] ?? [],
                'backgroundColor' => $color,
                'borderColor'     => $color,
                'borderWidth'     => 1,
                'borderRadius'    => 4,
            ];
            if (!empty($ds['label'])) {
                $chartDataset['label'] = $ds['label'];
            }
            $chartDatasets[] = $chartDataset;
        }

        return [
            'type' => 'bar',
            'data' => ['labels' => $config['xAxis']['labels'] ?? [], 'datasets' => $chartDatasets],
            'options' => $this->buildBarOptions($config),
        ];
    }

    private function buildStackedBarConfig(array $config): array
    {
        $chartDatasets = [];
        foreach ($config['datasets'] ?? [] as $ds) {
            $color = $this->mapColor($ds['colors'][0] ?? '#4e79a7');
            $chartDataset = [
                'data'            => $ds['data'] ?? [],
                'backgroundColor' => $color,
                'borderColor'     => $color,
                'borderWidth'     => 1,
            ];
            if (!empty($ds['label'])) {
                $chartDataset['label'] = $ds['label'];
            }
            $chartDatasets[] = $chartDataset;
        }

        $options = $this->buildBarOptions($config);
        $options['scales']['x']['stacked'] = true;
        $options['scales']['y']['stacked'] = true;

        return [
            'type' => 'bar',
            'data' => ['labels' => $config['xAxis']['labels'] ?? [], 'datasets' => $chartDatasets],
            'options' => $options,
        ];
    }

    private function buildPieConfig(array $config): array
    {
        $slices = $config['slices'] ?? [];
        $colors = $this->mapColors($slices['colors'] ?? []);

        return [
            'type' => 'doughnut',
            'data' => [
                'labels'   => $slices['labels'] ?? [],
                'datasets' => [[
                    'data'            => $slices['data'] ?? [],
                    'backgroundColor' => $colors,
                    'borderWidth'     => 2,
                    'borderColor'     => '#ffffff',
                ]],
            ],
            'options' => [
                'plugins' => [
                    'title'  => [
                        'display' => !empty($config['title']),
                        'text'    => $config['title'] ?? '',
                        'font'    => ['size' => 48, 'weight' => 'bold'],
                        'padding' => ['bottom' => 16],
                    ],
                    'legend' => [
                        'display'  => true,
                        'position' => $config['legend']['position'] ?? 'bottom',
                        'labels'   => ['padding' => 32, 'usePointStyle' => true, 'font' => ['size' => 38]],
                    ],
                ],
            ],
        ];
    }

    private function buildBarLineConfig(array $config): array
    {
        $y2 = $config['y2Axis'] ?? [];
        $chartDatasets = [];

        foreach ($config['datasets'] ?? [] as $ds) {
            $dsType = $ds['type'] ?? 'bar';
            $color = $this->mapColor($ds['colors'][0] ?? '#4e79a7');

            if ($dsType === 'line') {
                $chartDataset = [
                    'type'               => 'line',
                    'data'               => $ds['data'] ?? [],
                    'borderColor'        => $color,
                    'backgroundColor'    => $color,
                    'pointBackgroundColor' => $color,
                    'pointBorderColor'   => $color,
                    'pointRadius'        => 6,
                    'borderWidth'        => 2,
                    'fill'               => false,
                    'yAxisID'            => 'y2',
                    'order'              => 0,
                ];
                if (!empty($ds['label'])) {
                    $chartDataset['label'] = $ds['label'];
                }
                $chartDatasets[] = $chartDataset;
            } else {
                $chartDataset = [
                    'type'            => 'bar',
                    'data'            => $ds['data'] ?? [],
                    'backgroundColor' => $color,
                    'borderColor'     => $color,
                    'borderWidth'     => 1,
                    'borderRadius'    => 4,
                    'yAxisID'         => 'y',
                    'order'           => 1,
                ];
                if (!empty($ds['label'])) {
                    $chartDataset['label'] = $ds['label'];
                }
                $chartDatasets[] = $chartDataset;
            }
        }

        return [
            'type' => 'bar',
            'data' => ['labels' => $config['xAxis']['labels'] ?? [], 'datasets' => $chartDatasets],
            'options' => [
                'plugins' => [
                    'title'  => $this->buildTitlePlugin($config),
                    'legend' => $this->buildLegendPlugin($config),
                ],
                'scales' => [
                    'x' => [
                        'grid'  => ['display' => false],
                        'ticks' => $this->buildXTicks($config),
                    ],
                    'y' => [
                        'beginAtZero' => true,
                        'title' => [
                            'display' => !empty($config['yAxis']['title']),
                            'text'    => $config['yAxis']['title'] ?? '',
                            'font'    => ['size' => 24, 'weight' => 'bold'],
                        ],
                        'grid' => ['color' => 'rgba(0,0,0,0.06)'],
                    ],
                    'y2' => [
                        'position' => 'right',
                        'min'      => $y2['min'] ?? 0,
                        'max'      => $y2['max'] ?? 100,
                        'title'    => [
                            'display' => !empty($y2['title']),
                            'text'    => $y2['title'] ?? '',
                            'font'    => ['size' => 24, 'weight' => 'bold'],
                        ],
                        'grid' => ['display' => false],
                    ],
                ],
            ],
        ];
    }

    /**
     * Per-sample distribution chart used by Vietnam (NIHE) numeric-kit Peer Group Statistics.
     * Renders one "box" per sample showing the peer Mean ± SD (floating bar) with whiskers from
     * the peer min..max range, and overlays the participant's own reported value as a coloured
     * point (Acceptable / Unacceptable / Not Evaluated). Stays within Chart.js's built-in bar
     * and line controllers so it works with the existing chart-render.js without plugins.
     *
     * Expected $config:
     *   labels: ['NIHE-HIV-2301-01', ...]
     *   datasets: [
     *     ['type'=>'whisker', 'data'=>[[min,max], ...]],
     *     ['type'=>'box',     'data'=>[[mean-sd, mean+sd], ...]],
     *     ['type'=>'mean',    'data'=>[mean, ...]],
     *     ['type'=>'point',   'data'=>[participantValue|null, ...], 'pointColors'=>[...], 'label'=>'Acceptable'],
     *     // ... one 'point' dataset per concordance category so the legend reads correctly
     *   ]
     */
    /**
     * Native box-and-whisker chart using the @sgratzl/chartjs-chart-boxplot plugin.
     * Replaces the stacked-bar fake in buildBoxRangeConfig — the plugin renders proper
     * Tukey-style whiskers with caps natively. Caller passes raw peer values; the
     * plugin computes Q1/median/Q3 and min/max.
     *
     * Expected $config:
     *   xAxis.labels: ['Sample 1', ...]
     *   datasets: [
     *     ['type'=>'box', 'data'=>[[v1,v2,v3,...], ...], 'color'=>'#1f3a68', 'mean'=>true],
     *     ['type'=>'point', 'data'=>[participantValue|null, ...], 'color'=>'#f39c12', 'pointStyle'=>'circle', 'label'=>'Acceptable'],
     *   ]
     */
    private function buildBoxplotConfig(array $config): array
    {
        $labels = $config['xAxis']['labels'] ?? [];
        $datasets = $config['datasets'] ?? [];

        $chartDatasets = [];
        foreach ($datasets as $ds) {
            $kind = $ds['type'] ?? 'box';
            if ($kind === 'box') {
                $color = $this->mapColor($ds['color'] ?? '#1f3a68');
                $chartDatasets[] = [
                    'type'             => 'boxplot',
                    'label'            => $ds['label'] ?? '',
                    'data'             => $ds['data'] ?? [],
                    'backgroundColor'  => 'rgba(255,255,255,1)',
                    'borderColor'      => $color,
                    'borderWidth'      => 12,
                    'outlierStyle'     => 'circle',
                    'outlierRadius'    => 10,
                    'outlierBackgroundColor' => $color,
                    'outlierBorderColor' => $color,
                    'meanRadius'       => 0,
                    'itemRadius'       => 0,
                    'coef'             => 1.5,
                ];
            } elseif ($kind === 'point') {
                $pointColor = $this->mapColor($ds['color'] ?? '#ff8c00');
                $pointRadius = $ds['pointRadius'] ?? 36;
                $chartDatasets[] = [
                    'type'                 => 'line',
                    'label'                => $ds['label'] ?? '',
                    'data'                 => $ds['data'] ?? [],
                    'showLine'             => false,
                    'borderColor'          => $pointColor,
                    'backgroundColor'      => $pointColor,
                    'pointBackgroundColor' => $pointColor,
                    'pointBorderColor'     => $pointColor,
                    'pointRadius'          => $pointRadius,
                    'pointHoverRadius'     => $pointRadius,
                    'pointStyle'           => $ds['pointStyle'] ?? 'circle',
                    'spanGaps'             => false,
                    'order'                => 0,
                    'clip'                 => false,
                ];
            }
        }

        $yAxis = $config['yAxis'] ?? [];
        $effectiveMin = $yAxis['min'] ?? null;
        $tickFont  = $yAxis['tickFontSize']  ?? 60;
        $titleFont = $yAxis['titleFontSize'] ?? 70;
        $yScale = [
            'beginAtZero' => false,
            'title' => [
                'display' => !empty($yAxis['title']),
                'text'    => $yAxis['title'] ?? '',
                'font'    => ['size' => $titleFont, 'weight' => 'bold'],
            ],
            'ticks' => ['font' => ['size' => $tickFont], 'hideNegative' => true, 'maxTicksLimit' => 6],
            'grid'  => ['color' => 'rgba(0,0,0,0.06)'],
            'grace' => $yAxis['grace'] ?? '10%',
        ];
        if ($effectiveMin !== null) {
            $yScale['min'] = $effectiveMin;
        }

        return [
            'type' => 'boxplot',
            'data' => ['labels' => $labels, 'datasets' => $chartDatasets],
            'options' => [
                'plugins' => [
                    'title'  => $this->buildTitlePlugin($config),
                    'legend' => ['display' => false],
                ],
                'scales' => [
                    'x' => [
                        'grid'  => ['display' => false],
                        'title' => [
                            'display' => !empty($config['xAxis']['title']),
                            'text'    => $config['xAxis']['title'] ?? '',
                            'font'    => ['size' => $config['xAxis']['titleFontSize'] ?? 70, 'weight' => 'bold'],
                        ],
                        'ticks' => ['font' => ['size' => $config['xAxis']['tickFontSize'] ?? 60]],
                    ],
                    'y' => $yScale,
                ],
            ],
        ];
    }

    private function buildBoxRangeConfig(array $config): array
    {
        $labels = $config['xAxis']['labels'] ?? [];
        $datasets = $config['datasets'] ?? [];

        $chartDatasets = [];
        foreach ($datasets as $ds) {
            $kind = $ds['type'] ?? 'box';
            switch ($kind) {
                case 'whisker':
                    $chartDatasets[] = [
                        'type'               => 'bar',
                        'data'               => $ds['data'] ?? [],
                        'backgroundColor'    => $this->mapColor($ds['color'] ?? '#7e7e7e'),
                        'borderColor'        => $this->mapColor($ds['color'] ?? '#7e7e7e'),
                        'borderWidth'        => 0,
                        'barPercentage'      => 0.06,
                        'categoryPercentage' => 0.9,
                        'grouped'            => false,
                        'order'              => 3,
                    ];
                    break;
                case 'whiskerCap':
                    // Tukey cap — a short horizontal bar drawn at the whisker
                    // extremity (min or max). Wider than the whisker stem so it
                    // reads as a "T" against the thin stem line.
                    $chartDatasets[] = [
                        'type'               => 'bar',
                        'data'               => $ds['data'] ?? [],
                        'backgroundColor'    => $this->mapColor($ds['color'] ?? '#1f3a68'),
                        'borderColor'        => $this->mapColor($ds['color'] ?? '#1f3a68'),
                        'borderWidth'        => 0,
                        'barPercentage'      => 0.32,
                        'categoryPercentage' => 0.9,
                        'grouped'            => false,
                        'order'              => 3,
                    ];
                    break;
                case 'box':
                    $chartDatasets[] = [
                        'type'               => 'bar',
                        'data'               => $ds['data'] ?? [],
                        'backgroundColor'    => $this->mapColor($ds['color'] ?? '#ffffff'),
                        'borderColor'        => $this->mapColor($ds['borderColor'] ?? '#222'),
                        'borderWidth'        => 2,
                        'barPercentage'      => 0.55,
                        'categoryPercentage' => 0.9,
                        'grouped'            => false,
                        'order'              => 2,
                    ];
                    break;
                case 'mean':
                    // A thin horizontal floating bar at [mean-eps, mean+eps] visualises the
                    // median/mean line inside the box. Computed in PHP so the chart payload
                    // is plain JSON.
                    $chartDatasets[] = [
                        'type'               => 'bar',
                        'data'               => $ds['data'] ?? [],
                        'backgroundColor'    => $this->mapColor($ds['color'] ?? '#222'),
                        'borderColor'        => $this->mapColor($ds['color'] ?? '#222'),
                        'borderWidth'        => 0,
                        'barPercentage'      => 0.55,
                        'categoryPercentage' => 0.9,
                        'grouped'            => false,
                        'order'              => 1,
                    ];
                    break;
                case 'point':
                default:
                    $pointColor = $this->mapColor($ds['color'] ?? '#ff8c00');
                    // pointRadius is in CANVAS pixels — since boxRange charts render at
                    // 1400px+ wide and display at 30–165mm, calibrate big enough that even
                    // the narrowest mini-chart shows a visible dot. Caller may override.
                    $pointRadius = $ds['pointRadius'] ?? 36;
                    $chartDatasets[] = [
                        'type'                 => 'line',
                        'data'                 => $ds['data'] ?? [],
                        'label'                => $ds['label'] ?? '',
                        'showLine'             => false,
                        'borderColor'          => $pointColor,
                        'backgroundColor'      => $pointColor,
                        'pointBackgroundColor' => $pointColor,
                        'pointBorderColor'     => $pointColor,
                        'pointRadius'          => $pointRadius,
                        'pointHoverRadius'     => $pointRadius,
                        'pointStyle'           => $ds['pointStyle'] ?? 'circle',
                        'spanGaps'             => false,
                        'order'                => 0,
                        // Render dots fully even when sitting on a clamped axis
                        // boundary (e.g. y=0 for negative-reference samples).
                        'clip'                 => false,
                    ];
                    break;
            }
        }

        $yAxis = $config['yAxis'] ?? [];
        // boxRange anchors at 0 by default (S/CO-style ratios cannot be negative) but
        // extends slightly below so dots at y=0 aren't bisected. Negative tick labels
        // are hidden via the hideNegative sentinel honoured in chart-render.js.
        $effectiveMin = $yAxis['min'] ?? null;
        // Font sizes need to be ~3x larger than a regular chart because mini-charts
        // display at 30mm wide (vs 165mm for the old single chart) — at 1400px canvas,
        // that's roughly 47px per mm, so 60px font reads as ~1.3mm on the PDF.
        $tickFont  = $yAxis['tickFontSize']  ?? 60;
        $titleFont = $yAxis['titleFontSize'] ?? 70;
        $yScale = [
            'beginAtZero' => false,
            'title' => [
                'display' => !empty($yAxis['title']),
                'text'    => $yAxis['title'] ?? '',
                'font'    => ['size' => $titleFont, 'weight' => 'bold'],
            ],
            'ticks' => ['font' => ['size' => $tickFont], 'hideNegative' => ($effectiveMin === 0), 'maxTicksLimit' => 6],
            'grid'  => ['color' => 'rgba(0,0,0,0.06)'],
            'grace' => $yAxis['grace'] ?? '10%',
        ];
        if ($effectiveMin === 0) {
            $yScale['min'] = -8;
        } elseif ($effectiveMin !== null) {
            $yScale['min'] = $effectiveMin;
        }
        if (isset($yAxis['max'])) {
            $yScale['max'] = $yAxis['max'];
        }

        return [
            'type' => 'bar',
            'data' => ['labels' => $labels, 'datasets' => $chartDatasets],
            'options' => [
                'plugins' => [
                    'title'  => $this->buildTitlePlugin($config),
                    'legend' => $this->buildBoxRangeLegend($config, $datasets),
                ],
                'scales' => [
                    'x' => [
                        'grid'  => ['display' => false],
                        'title' => [
                            'display' => !empty($config['xAxis']['title']),
                            'text'    => $config['xAxis']['title'] ?? '',
                            'font'    => ['size' => $config['xAxis']['titleFontSize'] ?? 70, 'weight' => 'bold'],
                        ],
                        'ticks' => array_merge(['font' => ['size' => $config['xAxis']['tickFontSize'] ?? 60]], (($config['xAxis']['labelAngle'] ?? 0) > 0
                            ? ['maxRotation' => $config['xAxis']['labelAngle'], 'minRotation' => $config['xAxis']['labelAngle']]
                            : [])),
                    ],
                    'y' => $yScale,
                ],
            ],
        ];
    }

    private function buildBoxRangeLegend(array $config, array $datasets): array
    {
        // Hide bar-only legend entries (whisker/box/mean); show only the labelled point datasets.
        // Chart.js's default legend would emit grey rectangles for the box/whisker which read
        // as noise — explicit filter keeps the legend focused on concordance categories.
        $hasLabelledPoint = false;
        foreach ($datasets as $ds) {
            if (($ds['type'] ?? '') === 'point' && !empty($ds['label'])) {
                $hasLabelledPoint = true;
                break;
            }
        }
        if (!$hasLabelledPoint) {
            return ['display' => false];
        }
        return [
            'display'  => true,
            'position' => $config['legend']['position'] ?? 'bottom',
            'labels'   => [
                'padding'       => 16,
                'usePointStyle' => true,
                'font'          => ['size' => 20],
                // Sentinel honoured by chart-render.js: installs a runtime filter that hides
                // legend items whose dataset has no label, so the bar-only whisker/box/mean
                // datasets don't appear alongside the labelled concordance categories.
                'filterEmpty'   => true,
            ],
        ];
    }

    // --- Options builders ---

    private function buildBarOptions(array $config): array
    {
        return [
            'plugins' => [
                'title'  => $this->buildTitlePlugin($config),
                'legend' => $this->buildLegendPlugin($config),
            ],
            'scales' => [
                'x' => [
                    'grid'  => ['display' => false],
                    'title' => [
                        'display' => !empty($config['xAxis']['title']),
                        'text'    => $config['xAxis']['title'] ?? '',
                        'font'    => ['size' => 24, 'weight' => 'bold'],
                    ],
                    'ticks' => $this->buildXTicks($config),
                ],
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => !empty($config['yAxis']['title']),
                        'text'    => $config['yAxis']['title'] ?? '',
                        'font'    => ['size' => 24, 'weight' => 'bold'],
                    ],
                    'ticks' => ['font' => ['size' => 26]],
                    'grid'  => ['color' => 'rgba(0,0,0,0.06)'],
                ],
            ],
        ];
    }

    private function buildTitlePlugin(array $config): array
    {
        return [
            'display' => !empty($config['title']),
            'text'    => $config['title'] ?? '',
            'font'    => ['size' => $config['titleSize'] ?? 28, 'weight' => 'bold'],
            'padding' => ['bottom' => 16],
        ];
    }

    private function buildLegendPlugin(array $config): array
    {
        $legend = $config['legend'] ?? [];
        if (empty($legend)) {
            return ['display' => false];
        }
        return [
            'display'  => true,
            'position' => $legend['position'] ?? 'bottom',
            'labels'   => ['padding' => 24, 'usePointStyle' => true, 'font' => ['size' => 22]],
        ];
    }

    private function buildXTicks(array $config): array
    {
        $ticks = ['font' => ['size' => 26]];
        $angle = $config['xAxis']['labelAngle'] ?? 0;
        if ($angle > 0) {
            $ticks['maxRotation'] = $angle;
            $ticks['minRotation'] = $angle;
        }
        return $ticks;
    }

    // --- Color mapping (JPGraph named colors → CSS) ---

    private const COLOR_MAP = [
        'brown4'      => '#8B2323',
        'hotpink'     => '#FF69B4',
        'darkgreen'   => '#006400',
        'forestgreen' => '#228B22',
        'dimgray'     => '#696969',
        'gainsboro'   => '#DCDCDC',
        'slategray'   => '#708090',
        'silver'      => '#C0C0C0',
        'lightgray'   => '#D3D3D3',
        'darkgray'    => '#A9A9A9',
        'gray'        => '#808080',
        'yellow'      => '#FFD700',
        'red'         => '#DC3545',
    ];

    private function mapColor(string $color): string
    {
        $lower = strtolower($color);
        return self::COLOR_MAP[$lower] ?? $color;
    }

    private function mapColors(array $colors): array
    {
        return array_map(fn (string $c) => $this->mapColor($c), $colors);
    }
}
