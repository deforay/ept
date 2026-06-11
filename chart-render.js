/**
 * ePT Chart Renderer
 *
 * Reads a JSON payload from stdin, renders a Chart.js chart using skia-canvas,
 * and writes the PNG to stdout.
 *
 * Input (stdin): { width, height, chart: <Chart.js config object> }
 * Output (stdout): PNG binary data
 *
 * Usage: echo '<json>' | node chart-render.js
 */

import {
    Chart,
    CategoryScale,
    LinearScale,
    LogarithmicScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    LineController,
    BarController,
    PieController,
    DoughnutController,
} from 'chart.js';

import {
    BoxPlotController,
    BoxAndWiskers,
} from '@sgratzl/chartjs-chart-boxplot';

import { Canvas } from 'skia-canvas';

// PNG CRC32 implementation
const crcTable = new Int32Array(256);
for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
    crcTable[n] = c;
}
function pngCrc32(buf) {
    let crc = -1;
    for (let i = 0; i < buf.length; i++) crc = crcTable[(crc ^ buf[i]) & 0xFF] ^ (crc >>> 8);
    return (crc ^ -1) | 0;
}

// Word-wrap a string to fit within maxWidth (in px) using the ctx's current font.
// Returns an array of lines. A single word that overflows is kept on its own line
// (Chart.js will still render it; we never split mid-word).
function wrapTextToWidth(ctx, text, maxWidth) {
    const words = String(text).split(/\s+/).filter(Boolean);
    if (words.length <= 1) return [String(text)];
    const lines = [];
    let cur = words[0];
    for (let i = 1; i < words.length; i++) {
        const test = cur + ' ' + words[i];
        if (ctx.measureText(test).width <= maxWidth) {
            cur = test;
        } else {
            lines.push(cur);
            cur = words[i];
        }
    }
    lines.push(cur);
    return lines;
}

Chart.register(
    CategoryScale,
    LinearScale,
    LogarithmicScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    LineController,
    BarController,
    PieController,
    DoughnutController,
    BoxPlotController,
    BoxAndWiskers
);

// Read full stdin
let inputData = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', chunk => { inputData += chunk; });
process.stdin.on('end', async () => {
    try {
        const { width, height, format = 'png', chart: chartConfig } = JSON.parse(inputData);

        // Honor a sentinel on plugins.legend.labels.filterEmpty=true: install a filter that
        // suppresses legend items whose dataset has no label (used by the boxRange chart so
        // the auxiliary whisker/box/mean bar datasets don't pollute the legend).
        const legendLabels = chartConfig?.options?.plugins?.legend?.labels;
        if (legendLabels && legendLabels.filterEmpty === true) {
            legendLabels.filter = (item, _data) => !!item.text && item.text.trim() !== '';
            delete legendLabels.filterEmpty;
        }

        // Honor a sentinel on scales.y.ticks.hideNegative=true: install a tick callback that
        // hides negative tick labels. Lets callers extend the axis slightly below 0 (so dots
        // sitting at y=0 don't get bisected by the baseline) without exposing -10/-20 ticks.
        const yTicks = chartConfig?.options?.scales?.y?.ticks;
        if (yTicks && yTicks.hideNegative === true) {
            yTicks.callback = function (value) { return value < 0 ? '' : value; };
            delete yTicks.hideNegative;
        }

        const canvas = new Canvas(width, height);

        // Fill background white
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);

        // Defense-in-depth against the #1 chart-embedding bug: Chart.js draws a
        // single-string title centered and does NOT shrink or wrap it — if the
        // string is wider than the canvas it overflows and is clipped on BOTH
        // sides (the classic "ating laboratories…" cut-off title). Pre-measure
        // the title and, if it won't fit, word-wrap it into multiple lines.
        // Chart.js renders an array of strings as stacked title lines, so this
        // makes title clipping structurally impossible at any canvas size or
        // title length — no per-template font tuning required.
        const titleCfg = chartConfig?.options?.plugins?.title;
        if (titleCfg && titleCfg.display !== false
            && typeof titleCfg.text === 'string' && titleCfg.text.trim() !== '') {
            const fsize = titleCfg.font?.size ?? 28;
            const fweight = titleCfg.font?.weight ?? 'normal';
            ctx.font = `${fweight} ${fsize}px sans-serif`;
            const budget = width * 0.92; // leave a small margin for measurement error
            if (ctx.measureText(titleCfg.text).width > budget) {
                titleCfg.text = wrapTextToWidth(ctx, titleCfg.text, budget);
            }
        }

        // Give rotated axis labels and the title breathing room so they can't
        // clip against the canvas edge. Chart.js sizes the plot area but long
        // rotated tick labels can still overflow the bottom-left past x=0.
        chartConfig.options = chartConfig.options || {};
        if (chartConfig.options.layout === undefined) {
            chartConfig.options.layout = { padding: Math.max(8, Math.round(width * 0.012)) };
        }

        const chart = new Chart(canvas, chartConfig);

        if (format === 'svg') {
            // Vector output — no pHYs DPI hack needed. TCPDF embeds SVG via
            // ImageSVG() which renders at the caller's chosen mm size with no
            // raster downscaling, so fine lines stay crisp at any chart size.
            let svgText = (await canvas.toBuffer('svg')).toString();
            // skia-canvas wraps every text element in <clipPath> + <g clip-path>
            // for canvas-bounds clipping. TCPDF's ImageSVG parser doesn't handle
            // clip-path refs and silently drops the clipped content — we lose the
            // entire chart. Strip the clipPath defs and the clip-path attributes;
            // the chart is bounded by the SVG viewport anyway so unclipping is safe.
            svgText = svgText.replace(/<clipPath\b[^>]*>[\s\S]*?<\/clipPath>/g, '');
            svgText = svgText.replace(/\sclip-path="url\(#[^"]+\)"/g, '');
            process.stdout.write(svgText);
            chart.destroy();
            return;
        }

        const buffer = await canvas.toBuffer('png');

        // Inject pHYs chunk to set DPI (150 DPI) so TCPDF sizes images correctly.
        // Without this, TCPDF uses imgscale-based sizing which makes charts huge.
        const dpi = 150;
        const ppm = Math.round(dpi / 0.0254); // pixels per meter
        const pHYsData = Buffer.alloc(9);
        pHYsData.writeUInt32BE(ppm, 0); // X pixels per unit
        pHYsData.writeUInt32BE(ppm, 4); // Y pixels per unit
        pHYsData.writeUInt8(1, 8);       // unit = meter

        // Build pHYs chunk: length(4) + type(4) + data(9) + crc(4)
        const typeBuffer = Buffer.from('pHYs');
        const crcInput = Buffer.concat([typeBuffer, pHYsData]);
        const crcValue = pngCrc32(crcInput);

        const chunk = Buffer.alloc(4 + 4 + 9 + 4);
        chunk.writeUInt32BE(9, 0);           // data length
        typeBuffer.copy(chunk, 4);           // chunk type
        pHYsData.copy(chunk, 8);             // chunk data
        chunk.writeInt32BE(crcValue, 17);    // CRC

        // Insert after IHDR (8-byte signature + IHDR chunk)
        const ihdrLen = buffer.readUInt32BE(8);
        const insertPos = 8 + 4 + 4 + ihdrLen + 4; // sig + len + type + data + crc
        const output = Buffer.concat([
            buffer.subarray(0, insertPos),
            chunk,
            buffer.subarray(insertPos),
        ]);

        process.stdout.write(output);

        chart.destroy();
    } catch (err) {
        process.stderr.write('chart-render error: ' + err.message + '\n');
        process.exit(1);
    }
});
