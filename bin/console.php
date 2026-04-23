#!/usr/bin/env php
<?php
// bin/console.php - TUI launcher for all bin/ scripts

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

if (php_sapi_name() !== 'cli') {
    echo "This script can only be run from the command line." . PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';

if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () {
        echo PHP_EOL . "Cancelled." . PHP_EOL;
        exit(130);
    });
}

const BIN_DIR = __DIR__;
const SELF_NAME = 'console.php';
const BLACKLIST = ['console-helpers.php', 'shared-functions.sh'];

$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

/**
 * Discover runnable scripts in bin/.
 *
 * @return array<string, array{name:string, path:string, type:string, description:string, usage:?string}>
 */
function discoverScripts(): array
{
    $scripts = [];
    foreach (scandir(BIN_DIR) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if ($entry === SELF_NAME || in_array($entry, BLACKLIST, true)) {
            continue;
        }
        if (!preg_match('/\.(php|sh)$/', $entry, $m)) {
            continue;
        }

        $path = BIN_DIR . DIRECTORY_SEPARATOR . $entry;
        $contents = @file_get_contents($path);
        if ($contents === false) {
            continue;
        }

        // Must have a shebang to be considered runnable
        if (!str_starts_with($contents, '#!')) {
            continue;
        }

        $type = $m[1];
        $name = preg_replace('/\.(php|sh)$/', '', $entry);

        $scripts[$name] = [
            'name'        => $name,
            'path'        => $path,
            'type'        => $type,
            'description' => extractDescription($contents, $name, $type),
            'usage'       => extractUsage($contents, $type),
        ];
    }

    ksort($scripts);
    return $scripts;
}

function extractDescription(string $contents, string $name, string $type): string
{
    $lines = preg_split('/\r?\n/', $contents);
    $skipPrefixes = ['<?php', 'declare', 'use ', 'namespace', 'require', 'include'];

    // Lines that look like description but aren't — defensive CLI guards, filename echoes, usage block headers
    $noiseRegexes = [
        '/^only run from (the )?command line\.?$/i',
        '/^this script can only be run/i',
        '/^to use this script:?$/i',
    ];

    $inShellUsageBlock = false;

    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || str_starts_with($trim, '#!')) {
            $inShellUsageBlock = false;
            continue;
        }

        $isSkip = false;
        foreach ($skipPrefixes as $p) {
            if (str_starts_with($trim, $p)) {
                $isSkip = true;
                break;
            }
        }
        if ($isSkip) {
            continue;
        }

        // PHP: // comment
        if ($type === 'php' && preg_match('#^//\s*(.+)$#', $trim, $m)) {
            $text = trim($m[1]);
            // Ignore lines that just echo the filename
            if (preg_match('#^bin/' . preg_quote($name, '#') . '\.php$#', $text)) {
                continue;
            }
            if (isNoise($text, $noiseRegexes)) {
                continue;
            }
            return $text;
        }

        // Shell: # comment
        if ($type === 'sh' && preg_match('/^#\s*(.*)$/', $trim, $m)) {
            $text = trim($m[1]);

            if ($text === '') {
                continue;
            }
            if (isNoise($text, $noiseRegexes)) {
                // Start of a usage block — skip the entire contiguous # block that follows
                $inShellUsageBlock = true;
                continue;
            }
            if ($inShellUsageBlock) {
                continue;
            }
            // Shell scripts use `# ...` liberally as section headers inside the code
            // (e.g. "# Check if running as root"). Skip these and fall through to filename
            // synthesis; the usage block is the real documentation.
            continue;
        }

        // Hit a non-comment code line — stop looking
        if ($type === 'php' && !str_starts_with($trim, '/') && !str_starts_with($trim, '*')) {
            break;
        }
        if ($type === 'sh' && !str_starts_with($trim, '#')) {
            break;
        }
    }

    // Synthesize from filename
    return ucfirst(str_replace('-', ' ', $name));
}

function isNoise(string $text, array $noiseRegexes): bool
{
    foreach ($noiseRegexes as $rx) {
        if (preg_match($rx, $text)) {
            return true;
        }
    }
    return false;
}

function extractUsage(string $contents, string $type): ?string
{
    if ($type === 'php') {
        // Find all /* ... */ blocks with their end positions
        if (!preg_match_all('#/\*(.*?)\*/#s', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $candidates = $matches[0];
        $bodies = $matches[1];

        // Accept a block as the usage block only if:
        //   (a) it contains "USAGE" (case-insensitive), or
        //   (b) it is the last non-whitespace content in the file (truly trailing)
        foreach (array_reverse(array_keys($candidates)) as $i) {
            [$full, $offset] = $candidates[$i];
            $body = $bodies[$i][0];

            $endPos = $offset + strlen($full);
            $tail = substr($contents, $endPos);

            $isTrailing = (trim($tail) === '');
            $hasUsageMarker = (stripos($body, 'USAGE') !== false);

            if (!$isTrailing && !$hasUsageMarker) {
                continue;
            }

            $lines = preg_split('/\r?\n/', trim($body));
            // Strip decorative "===" lines
            $filtered = array_filter($lines, fn($l) => !preg_match('/^\s*={3,}\s*$/', $l));
            $result = trim(implode("\n", $filtered));
            return $result !== '' ? $result : null;
        }
        return null;
    }

    // Shell: leading # comment block after shebang
    $lines = preg_split('/\r?\n/', $contents);
    $collected = [];
    $started = false;
    foreach ($lines as $line) {
        $trim = trim($line);
        if (!$started) {
            // Skip shebang and blank lines until we find a comment
            if (str_starts_with($trim, '#!') || $trim === '') {
                continue;
            }
            if (str_starts_with($trim, '#')) {
                $started = true;
                $collected[] = ltrim(substr($trim, 1));
                continue;
            }
            // Non-comment line before any comment — no usage block
            return null;
        }
        if (str_starts_with($trim, '#')) {
            $collected[] = ltrim(substr($trim, 1));
            continue;
        }
        // End of comment block
        break;
    }

    $result = trim(implode("\n", $collected));
    return $result !== '' ? $result : null;
}

function printList(SymfonyStyle $io, array $scripts, bool $numbered = false): void
{
    $i = 0;
    $maxName = 0;
    foreach ($scripts as $s) {
        $maxName = max($maxName, strlen($s['name']));
    }

    foreach ($scripts as $s) {
        $i++;
        $prefix = $numbered ? sprintf('[%2d] ', $i) : '  ';
        $io->writeln(sprintf(
            '%s<info>%s</info>   %s',
            $prefix,
            str_pad($s['name'], $maxName),
            $s['description']
        ));
    }
}

function printUsage(SymfonyStyle $io, array $script): void
{
    $io->section($script['name']);
    $io->text('<comment>Description:</comment> ' . $script['description']);
    $io->text('<comment>File:</comment>        bin/' . basename($script['path']));
    $io->newLine();

    if ($script['usage'] === null) {
        $io->note('No usage block documented — see file source.');
        return;
    }

    $io->writeln('<comment>Usage:</comment>');
    foreach (explode("\n", $script['usage']) as $line) {
        $io->writeln('  ' . $line);
    }
}

function findScript(array $scripts, string $query): array
{
    $query = preg_replace('/\.(php|sh)$/', '', trim($query));

    if (isset($scripts[$query])) {
        return [$scripts[$query]];
    }

    // Partial match
    $matches = [];
    foreach ($scripts as $name => $s) {
        if (stripos($name, $query) !== false) {
            $matches[] = $s;
        }
    }
    return $matches;
}

function suggest(array $scripts, string $query): array
{
    // Simple substring-based suggestions ordered by similarity
    $names = array_keys($scripts);
    $suggestions = [];
    foreach ($names as $n) {
        $suggestions[$n] = levenshtein($query, $n);
    }
    asort($suggestions);
    return array_slice(array_keys($suggestions), 0, 3);
}

function runScript(SymfonyStyle $io, array $script, string $argsString): int
{
    $interpreter = $script['type'] === 'php' ? 'php' : 'bash';
    $cmd = sprintf(
        '%s %s %s',
        escapeshellcmd($interpreter),
        escapeshellarg($script['path']),
        $argsString
    );

    $io->newLine();
    $io->writeln('<comment>$ ' . $cmd . '</comment>');
    $io->newLine();

    $exitCode = 0;
    passthru($cmd, $exitCode);

    $io->newLine();
    if ($exitCode === 0) {
        $io->success(sprintf('%s finished (exit 0)', $script['name']));
    } else {
        $io->warning(sprintf('%s exited with code %d', $script['name'], $exitCode));
    }

    return $exitCode;
}

// ============================================================================
// Main
// ============================================================================

$scripts = discoverScripts();
if ($scripts === []) {
    $io->error('No scripts discovered in bin/.');
    exit(1);
}

$argv = $_SERVER['argv'];
$subcommand = $argv[1] ?? null;

// Mode: list
if ($subcommand === 'list') {
    $io->title('Available bin/ scripts');
    printList($io, $scripts);
    exit(0);
}

// Mode: info <name>
if ($subcommand === 'info') {
    $query = $argv[2] ?? null;
    if ($query === null || $query === '') {
        $io->error('Usage: php bin/console.php info <script-name>');
        exit(1);
    }

    $matches = findScript($scripts, $query);
    if ($matches === []) {
        $io->error("No script matches '$query'.");
        $suggestions = suggest($scripts, $query);
        if ($suggestions !== []) {
            $io->text('Did you mean:');
            $io->listing($suggestions);
        }
        exit(1);
    }

    if (count($matches) > 1) {
        $io->warning(sprintf("'%s' matches %d scripts:", $query, count($matches)));
        foreach ($matches as $m) {
            $io->writeln(sprintf('  <info>%s</info> — %s', $m['name'], $m['description']));
        }
        $io->newLine();
        $io->text('Be more specific, or run without arguments for interactive mode.');
        exit(1);
    }

    $io->title('Script info');
    printUsage($io, $matches[0]);
    exit(0);
}

if ($subcommand !== null) {
    $io->error("Unknown subcommand: $subcommand");
    $io->text('Usage:');
    $io->listing([
        'php bin/console.php               (interactive TUI)',
        'php bin/console.php list          (plain listing)',
        'php bin/console.php info <name>   (show usage for one script)',
    ]);
    exit(1);
}

// Mode: interactive TUI
$isTty = function_exists('stream_isatty') ? @stream_isatty(STDIN) : true;

if (!$isTty) {
    $io->title('Available bin/ scripts');
    printList($io, $scripts);
    $io->newLine();
    $io->note("Not a TTY — interactive menu disabled. Use 'info <name>' to see usage for a script.");
    exit(0);
}

$io->title('ePT bin/ console');
$io->text('Browse and launch scripts from the bin/ directory.');

$scriptList = array_values($scripts);

while (true) {
    $io->newLine();
    $io->section('Available scripts');
    printList($io, $scriptList, true);
    $io->newLine();

    $answer = $io->ask("Select a script (number, or 'q' to quit)", 'q');
    $answer = trim((string) $answer);

    if ($answer === '' || strtolower($answer) === 'q') {
        $io->text('Bye.');
        exit(0);
    }

    if (!ctype_digit($answer)) {
        $io->warning('Please enter a number or q.');
        continue;
    }

    $idx = ((int) $answer) - 1;
    if ($idx < 0 || $idx >= count($scriptList)) {
        $io->warning('Out of range.');
        continue;
    }

    $selected = $scriptList[$idx];
    printUsage($io, $selected);

    $argsString = $io->ask(
        "Enter arguments (blank = run with no args, 'b' = back, 'q' = quit)",
        ''
    );
    $argsString = (string) $argsString;
    $trimmedArgs = trim($argsString);

    if (strtolower($trimmedArgs) === 'q') {
        $io->text('Bye.');
        exit(0);
    }
    if (strtolower($trimmedArgs) === 'b') {
        continue;
    }

    runScript($io, $selected, $argsString);

    if (!$io->confirm('Back to menu?', true)) {
        exit(0);
    }
}
