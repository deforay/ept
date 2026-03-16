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
        $filename = ($config['filename'] ?? 'chart') . '.png';
        $outputPath = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        $chartJsConfig = match ($type) {
            'bar'        => $this->buildBarConfig($config),
            'groupedBar' => $this->buildGroupedBarConfig($config),
            'stackedBar' => $this->buildStackedBarConfig($config),
            'pie'        => $this->buildPieConfig($config),
            'bar+line'   => $this->buildBarLineConfig($config),
            default      => throw new InvalidArgumentException("Unknown chart type: $type"),
        };

        $reqWidth = $config['width'] ?? 700;
        $reqHeight = $config['height'] ?? 400;

        // Ensure minimum 1400px render width for crisp text at PDF display sizes.
        // Font sizes (28px title, 26px ticks, etc.) are calibrated for 1400px.
        $minWidth = 1400;
        if ($reqWidth < $minWidth) {
            $scale = $minWidth / $reqWidth;
            $reqHeight = (int) round($reqHeight * $scale);
            $reqWidth = $minWidth;
        }

        $payload = json_encode([
            'width'  => $reqWidth,
            'height' => $reqHeight,
            'chart'  => $chartJsConfig,
        ]);

        $png = $this->callNodeScript($payload);

        if ($png === false || strlen($png) === 0) {
            throw new RuntimeException('chart-render.js produced no output');
        }

        file_put_contents($outputPath, $png);
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
            error_log('chart-render.js error: ' . $stderr);
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
        return array_map(fn(string $c) => $this->mapColor($c), $colors);
    }
}
