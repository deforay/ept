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

import ChartDataLabels from 'chartjs-plugin-datalabels';
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

// Remove every PNG chunk of the given 4-char type from a PNG buffer.
function stripPngChunk(buffer, chunkType) {
    const out = [buffer.subarray(0, 8)]; // PNG signature
    let pos = 8;
    while (pos < buffer.length) {
        const len = buffer.readUInt32BE(pos);
        const type = buffer.subarray(pos + 4, pos + 8).toString('ascii');
        const chunkLen = 12 + len;
        if (type !== chunkType) {
            out.push(buffer.subarray(pos, pos + chunkLen));
        }
        if (type === 'IEND') break;
        pos += chunkLen;
    }
    return Buffer.concat(out);
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
    DoughnutController
);

// Opt-in per chart via options.plugins.datalabels; without config, no labels are drawn.
Chart.register(ChartDataLabels);
Chart.defaults.set('plugins.datalabels', { display: false });

/**
 * Walk a Chart.js config and replace `_formatter: { type, ... }` sentinels
 * with real JS formatter functions. Sentinels are used because functions
 * can't survive PHP→Node JSON serialization.
 *
 * Supported:
 *   - { type: 'printf', format: '%d' | '%.2f%%' | ... } — sprintf-style on `value`
 *   - { type: 'stackTotal', format: '%d' } — sum of all datasets at the same index;
 *     only renders on the topmost (last) dataset to avoid duplicates.
 */
function expandFormatterSentinels(node) {
    if (!node || typeof node !== 'object') return;

    if (node._formatter && typeof node._formatter === 'object') {
        const spec = node._formatter;
        delete node._formatter;
        if (spec.type === 'printf') {
            const fmt = spec.format || '%s';
            node.formatter = (value) => sprintfLite(fmt, value);
        } else if (spec.type === 'stackTotal') {
            const fmt = spec.format || '%d';
            node.formatter = (value, ctx) => {
                const datasets = ctx.chart.data.datasets;
                if (ctx.datasetIndex !== datasets.length - 1) return '';
                const total = datasets.reduce((s, d) => s + (Number(d.data[ctx.dataIndex]) || 0), 0);
                return sprintfLite(fmt, total);
            };
        }
    }

    for (const key of Object.keys(node)) {
        const child = node[key];
        if (child && typeof child === 'object') expandFormatterSentinels(child);
    }
}

// Minimal printf: supports %d, %s, %f, %.Nf, and a trailing % literal (e.g. '%d%%').
function sprintfLite(fmt, value) {
    return fmt.replace(/%(?:\.(\d+))?([dsf%])/g, (m, prec, kind) => {
        if (kind === '%') return '%';
        const n = Number(value);
        if (kind === 'd') return Number.isFinite(n) ? String(Math.round(n)) : String(value);
        if (kind === 'f') return Number.isFinite(n) ? n.toFixed(prec != null ? Number(prec) : 6) : String(value);
        return String(value);
    });
}

// Read full stdin
let inputData = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', chunk => { inputData += chunk; });
process.stdin.on('end', async () => {
    try {
        const { width, height, chart: chartConfig } = JSON.parse(inputData);

        expandFormatterSentinels(chartConfig);

        const canvas = new Canvas(width, height);

        // Fill background white
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);

        const chart = new Chart(canvas, chartConfig);

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

        // Strip any pre-existing pHYs chunk (skia-canvas writes a default 72-DPI one),
        // then insert our 150-DPI chunk right after IHDR.
        const stripped = stripPngChunk(buffer, 'pHYs');
        const ihdrLen = stripped.readUInt32BE(8);
        const insertPos = 8 + 4 + 4 + ihdrLen + 4; // sig + len + type + data + crc
        const output = Buffer.concat([
            stripped.subarray(0, insertPos),
            chunk,
            stripped.subarray(insertPos),
        ]);

        process.stdout.write(output);

        chart.destroy();
    } catch (err) {
        process.stderr.write('chart-render error: ' + err.message + '\n');
        process.exit(1);
    }
});
