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

// Read full stdin
let inputData = '';
process.stdin.setEncoding('utf8');
process.stdin.on('data', chunk => { inputData += chunk; });
process.stdin.on('end', async () => {
    try {
        const { width, height, chart: chartConfig } = JSON.parse(inputData);

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
