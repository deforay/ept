<?php

declare(strict_types=1);

namespace EptTestHarness\Reports;

/**
 * What a reader sees in a PDF, as data that can be compared: page count, each
 * word with its position, each image with its size and where it is drawn,
 * links and form fields.
 *
 * Bytes are never compared, since the creation date and file ID change on
 * every run. Words come from pdftotext -bbox, not -layout, which reorders text
 * between runs. Dates and times are masked so a report generated a minute
 * later still matches.
 */
final class PdfFacts
{
    private const TIMESTAMP = '/^(\d{1,2}[-\/ ][A-Za-z]{3}[-\/ ]\d{4}|\d{4}-\d{2}-\d{2}|\d{1,2}:\d{2}(:\d{2})?)$/';

    /**
     * @param string[] $files
     * @return array<string, array{pages:int, words:list<string>, positions:list<string>, images:list<string>, placements:list<string>, links:list<string>, fields:int}>
     */
    public static function collect(array $files, int $procs): array
    {
        $facts = [];
        $queue = $files;
        $running = [];
        while ($queue !== [] || $running !== []) {
            while ($queue !== [] && count($running) < $procs) {
                $file = array_shift($queue);
                $arg = escapeshellarg($file);
                // Output goes to a file: a word dump outgrows a pipe buffer,
                // and the child would block until someone read it.
                $out = (string) tempnam(sys_get_temp_dir(), 'ept-pdf-facts-');
                $process = proc_open(
                    "pdfinfo $arg; echo '@@@'; pdftotext -bbox $arg -; echo '@@@'; pdfimages -list $arg",
                    [1 => ['file', $out, 'w'], 2 => ['file', '/dev/null', 'w']],
                    $pipes
                );
                $running[] = [$process, $out, $file];
            }
            foreach ($running as $i => [$process, $out, $file]) {
                if (proc_get_status($process)['running']) {
                    continue;
                }
                proc_close($process);
                unset($running[$i]);
                $facts[$file] = self::parse((string) file_get_contents($out), $file);
                unlink($out);
            }
            usleep(2000);
        }
        return $facts;
    }

    /** @return array{pages:int, words:list<string>, positions:list<string>, images:list<string>, placements:list<string>, links:list<string>, fields:int} */
    private static function parse(string $output, string $file): array
    {
        [$info, $bbox, $images] = array_pad(explode("@@@\n", $output, 3), 3, '');

        $words = [];
        $positions = [];
        preg_match_all('#<word xMin="([\d.]+)" yMin="([\d.]+)"[^>]*>([^<]*)</word>#', $bbox, $matches, PREG_SET_ORDER);
        foreach ($matches as [, $x, $y, $text]) {
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match(self::TIMESTAMP, $text)) {
                $text = '<time>';
            }
            $words[] = $text;
            $positions[] = sprintf('%s @ %.1f,%.1f', $text, (float) $x, (float) $y);
        }

        $placed = [];
        foreach (array_slice(explode("\n", trim($images)), 2) as $line) {
            $columns = preg_split('/\s+/', trim($line)) ?: [];
            if (count($columns) > 4) {
                $placed[] = sprintf('page %s: %sx%s', $columns[0], $columns[3], $columns[4]);
            }
        }

        $raw = (string) file_get_contents($file);

        // Where each image is drawn: "w 0 0 h x y cm /Name Do" in the page
        // content. A count alone misses a logo that moved. The name is left
        // out, since numbering differs between runs.
        $placements = [];
        preg_match_all('#stream\r?\n(.*?)\r?\nendstream#s', $raw, $streams);
        foreach ($streams[1] as $stream) {
            $content = @gzuncompress($stream);
            if ($content === false || !str_contains($content, ' Do')) {
                continue;
            }
            preg_match_all('#(-?[\d.]+) (-?[\d.]+) (-?[\d.]+) (-?[\d.]+) (-?[\d.]+) (-?[\d.]+) cm\s*/\S+ Do#', $content, $draws, PREG_SET_ORDER);
            foreach ($draws as $d) {
                $placements[] = sprintf('%.1fx%.1f at %.1f,%.1f', (float) $d[1], (float) $d[4], (float) $d[5], (float) $d[6]);
            }
        }

        preg_match_all('#/URI\s*\(([^)]*)\)#', $raw, $uris);
        // TCPDF adds a hidden link to tcpdf.org on every document
        $links = array_values(array_filter($uris[1], static fn (string $uri): bool => $uri !== 'http://www.tcpdf.org'));

        return [
            'pages'     => preg_match('/Pages:\s+(\d+)/', $info, $m) ? (int) $m[1] : -1,
            'words'     => $words,
            'positions' => $positions,
            'images'    => $placed,
            'placements' => $placements,
            'links'     => $links,
            'fields'    => preg_match_all('#/FT\s*/#', $raw),
        ];
    }
}
