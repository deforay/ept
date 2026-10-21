<?php

declare(strict_types=1);

namespace EptTestHarness\Reports;

/**
 * What a reader sees in a PDF, as data that can be compared: page count, each
 * word with its position, each image with its size and where it is drawn,
 * links and form fields. Also the problems a PDF can have on its own, without
 * another version to compare with.
 *
 * Bytes are never compared, since the creation date and file ID change on
 * every run. Words come from pdftotext -bbox, not -layout, which reorders text
 * between runs. Dates and times are masked so a report generated a minute
 * later still matches.
 */
final class PdfFacts
{
    private const TIMESTAMP = '/^(\d{1,2}[-\/ ][A-Za-z]{3}[-\/ ]\d{4}|\d{4}-\d{2}-\d{2}|\d{1,2}:\d{2}(:\d{2})?)$/';

    /** The fonts every PDF reader has, which TCPDF does not embed */
    private const STANDARD_FONTS = '/^(Helvetica|Times|Courier)(-(Bold|Oblique|BoldOblique|Roman|Italic|BoldItalic))?$|^(Symbol|ZapfDingbats)$/';

    /**
     * @param string[] $files
     * @return array<string, array{pages:int, words:list<string>, positions:list<string>, images:list<string>, placements:list<string>, links:list<string>, fields:int, problems:list<string>}>
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
                    "pdfinfo $arg; echo '@@@'; pdftotext -bbox $arg -; echo '@@@'; pdfimages -list $arg; echo '@@@'; pdffonts $arg",
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

    /** @return array{pages:int, words:list<string>, positions:list<string>, images:list<string>, placements:list<string>, links:list<string>, fields:int, problems:list<string>} */
    private static function parse(string $output, string $file): array
    {
        [$info, $bbox, $images, $fonts] = array_pad(explode("@@@\n", $output, 4), 4, '');

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

        $pages = preg_match('/Pages:\s+(\d+)/', $info, $m) ? (int) $m[1] : -1;

        return [
            'pages'     => $pages,
            'words'     => $words,
            'positions' => $positions,
            'images'    => $placed,
            'placements' => $placements,
            'links'     => $links,
            'fields'    => preg_match_all('#/FT\s*/#', $raw),
            'problems'  => self::problems($raw, $pages, $words, $fonts),
        ];
    }

    /**
     * What is wrong with the PDF by itself: a graphics state saved and not
     * restored or the other way round, a page-number placeholder left in the
     * text, a font a reader may not have, a page with no text.
     *
     * @param list<string> $words
     * @return list<string>
     */
    private static function problems(string $raw, int $pages, array $words, string $fonts): array
    {
        $problems = [];
        $text = implode(' ', $words);
        if ($pages <= 0) {
            $problems[] = 'no pages';
        } elseif (trim(str_replace('Powered by TCPDF (www.tcpdf.org)', '', $text)) === '') {
            // TCPDF writes that line, in white, on every document
            $problems[] = 'no text';
        }

        $page = 0;
        foreach (self::contentStreams($raw) as [$isPage, $content]) {
            $where = $isPage ? 'page ' . ++$page : 'a form XObject';
            $depth = 0;
            $restoredUnsaved = false;
            preg_match_all('/(?<=\s|^)[qQ](?=\s|$)/', self::withoutStrings($content), $operators);
            foreach ($operators[0] as $operator) {
                if ($operator === 'q') {
                    $depth++;
                } elseif ($depth === 0) {
                    $restoredUnsaved = true;
                } else {
                    $depth--;
                }
            }
            if ($restoredUnsaved) {
                $problems[] = "$where: Q without q";
            }
            if ($depth > 0) {
                $problems[] = "$where: q without Q";
            }
        }

        // TCPDF replaces these after the last page. One left in the text
        // means the footer was written in a way the replacement missed.
        if (preg_match('/\{:p[tn][pg]:\}|\{p?nb\}|\bPage \?/', $text, $m)) {
            $problems[] = "page number not filled in: \"$m[0]\"";
        }

        foreach (array_slice(explode("\n", trim($fonts)), 2) as $line) {
            $columns = preg_split('/\s+/', trim($line)) ?: [];
            $count = count($columns);
            // The name has no spaces. The type can, so emb is found from the end.
            if ($count < 7) {
                continue;
            }
            $name = (string) preg_replace('/^[A-Z]{6}\+/', '', $columns[0]);
            if ($columns[$count - 5] === 'no' && !preg_match(self::STANDARD_FONTS, $name)) {
                $problems[] = "font not embedded: $name";
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * The decoded content of each page, in page order, and of each form
     * XObject, which draws a template or an imported page
     *
     * @return list<array{bool, string}>
     */
    private static function contentStreams(string $raw): array
    {
        $objects = [];
        preg_match_all('/(?<=[\r\n])(\d+) 0 obj\b/', $raw, $starts, PREG_OFFSET_CAPTURE);
        foreach ($starts[1] as $i => [$id, $offset]) {
            $end = $starts[1][$i + 1][1] ?? strlen($raw);
            $objects[(int) $id] = substr($raw, (int) $offset, $end - (int) $offset);
        }
        $streamOf = static function (string $object): ?string {
            if (!preg_match('/\bstream\r?\n/', $object, $m, PREG_OFFSET_CAPTURE)) {
                return null;
            }
            $dict = substr($object, 0, $m[0][1]);
            $data = substr($object, $m[0][1] + strlen($m[0][0]));
            $data = preg_match('#/Length (\d+)(?! \d+ R)#', $dict, $length)
                ? substr($data, 0, (int) $length[1])
                : (string) preg_replace('/\r?\nendstream.*$/s', '', $data);
            if (!str_contains($dict, '/FlateDecode')) {
                return str_contains($dict, '/Filter') ? null : $data;
            }
            $decoded = @gzuncompress($data);
            return $decoded === false ? null : $decoded;
        };

        $streams = [];
        foreach ($objects as $object) {
            $head = substr($object, 0, (int) (strpos($object, 'stream') ?: strlen($object)));
            if (preg_match('#/Type\s*/Page\b#', $head) && preg_match('#/Contents\s*(\[[^\]]*\]|\d+ 0 R)#', $head, $contents)) {
                preg_match_all('/(\d+) 0 R/', $contents[1], $refs);
                $content = '';
                foreach ($refs[1] as $ref) {
                    $content .= ($streamOf($objects[(int) $ref] ?? '') ?? '') . "\n";
                }
                $streams[] = [true, $content];
            } elseif (preg_match('#/Subtype\s*/Form\b#', $head)) {
                $content = $streamOf($object);
                if ($content !== null) {
                    $streams[] = [false, $content];
                }
            }
        }
        return $streams;
    }

    /** Content with its text strings taken out, so a q or Q in printed text is not read as an operator */
    private static function withoutStrings(string $content): string
    {
        $content = (string) preg_replace('/(?<str>\((?:[^()\\\\]++|\\\\.|(?&str))*\))/s', ' ', $content);
        return (string) preg_replace('/<[0-9A-Fa-f\s]*>/', ' ', $content);
    }
}
