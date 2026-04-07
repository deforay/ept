#!/usr/bin/env php
<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit(0);
}

$options = getopt('', ['locale::', 'source-locale::', 'help']);

if (isset($options['help'])) {
    echo <<<TXT
Usage:
  php bin/ai-pretranslate.php [--locale=fr_FR]

Options:
  --locale         Pre-translate one or more locales. Repeat the flag to pass multiple locales.
  --source-locale  Source locale. Defaults to en_US.
  --help           Show this help text.

Environment:
  EPT_AI_API_URL   Full chat completions endpoint URL
  EPT_AI_API_KEY   API key
  EPT_AI_MODEL     Model name

TXT;
    exit(0);
}

require_once __DIR__ . '/../constants.php';

set_include_path(implode(PATH_SEPARATOR, [
    realpath(ROOT_PATH . '/vendor'),
    realpath(ROOT_PATH . '/library'),
    get_include_path(),
]));

require_once ROOT_PATH . '/vendor/autoload.php';

chdir(ROOT_PATH);

ini_set('memory_limit', '-1');
set_time_limit(0);

const DEFAULT_SOURCE_LOCALE = 'en_US';
const DEFAULT_BATCH_SIZE = 25;
const DEFAULT_TIMEOUT_SECONDS = 60;
const TRANSLATION_GUIDE_PATH = ROOT_PATH . '/docs/TranslationGuide.md';

try {
    assertBinaryAvailable('msgfmt');

    $requestedLocales = normalizeLocaleFilter($options['locale'] ?? []);
    $sourceLocale = trim((string)($options['source-locale'] ?? DEFAULT_SOURCE_LOCALE));

    $client = new AiTranslationClient(
        trim((string)getenv('EPT_AI_API_URL')),
        trim((string)getenv('EPT_AI_API_KEY')),
        trim((string)getenv('EPT_AI_MODEL'))
    );

    $context = buildTranslationContext();
    $locales = discoverLocales($requestedLocales);
    if ($locales === []) {
        throw new RuntimeException('No locale PO files were found to pre-translate.');
    }

    foreach ($locales as $locale => $poFile) {
        if ($locale === $sourceLocale) {
            echo "Skipping source locale {$locale}" . PHP_EOL;
            continue;
        }

        echo "AI pre-translating locale {$locale}" . PHP_EOL;
        $updatedCount = aiPretranslatePoFile($poFile, $locale, $sourceLocale, $client, $context);
        echo "Filled {$updatedCount} empty translations in {$locale}" . PHP_EOL;

        compileCatalog($poFile, localePoToMoPath($poFile));
    }

    echo 'AI pre-translation completed successfully.' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'AI pre-translation failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @param mixed $localeOption
 * @return string[]
 */
function normalizeLocaleFilter($localeOption): array
{
    if ($localeOption === []) {
        return [];
    }

    $locales = is_array($localeOption) ? $localeOption : [$localeOption];
    $locales = array_map(static fn($locale): string => trim((string)$locale), $locales);
    $locales = array_values(array_filter($locales, static fn(string $locale): bool => $locale !== ''));

    foreach ($locales as $locale) {
        if (!preg_match('/^[A-Za-z_@.]+$/', $locale)) {
            throw new InvalidArgumentException("Invalid locale '{$locale}'.");
        }
    }

    return array_values(array_unique($locales));
}

function assertBinaryAvailable(string $binary): void
{
    $path = trim((string)shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null'));
    if ($path === '') {
        throw new RuntimeException("Required binary '{$binary}' was not found in PATH.");
    }
}

/**
 * @param string[] $requestedLocales
 * @return array<string, string>
 */
function discoverLocales(array $requestedLocales): array
{
    $languageRoot = APPLICATION_PATH . '/languages';
    if (!is_dir($languageRoot)) {
        throw new RuntimeException('Language directory does not exist.');
    }

    $localeFiles = [];
    $iterator = new DirectoryIterator($languageRoot);

    /** @var DirectoryIterator $directory */
    foreach ($iterator as $directory) {
        if (!$directory->isDir() || $directory->isDot()) {
            continue;
        }

        $locale = $directory->getFilename();
        $poFile = $directory->getPathname() . DIRECTORY_SEPARATOR . $locale . '.po';

        if (!is_file($poFile)) {
            continue;
        }

        $localeFiles[$locale] = $poFile;
    }

    ksort($localeFiles, SORT_NATURAL | SORT_FLAG_CASE);

    if ($requestedLocales === []) {
        return $localeFiles;
    }

    $filteredLocales = array_intersect_key($localeFiles, array_flip($requestedLocales));
    $missingLocales = array_diff($requestedLocales, array_keys($filteredLocales));

    if ($missingLocales !== []) {
        throw new RuntimeException(
            'Requested locale PO file not found for: ' . implode(', ', $missingLocales)
        );
    }

    return $filteredLocales;
}

function buildTranslationContext(): string
{
    $guide = @file_get_contents(TRANSLATION_GUIDE_PATH);
    if ($guide === false || trim($guide) === '') {
        return implode("\n", [
            'PT means Proficiency Testing in a laboratory quality assurance context.',
            'Participant means laboratory/facility, not an individual person.',
            'Scheme means testing program/category, not a diagram.',
            'Use formal, professional language.',
            'Preserve placeholders, HTML tags, emails, URLs, and technical identifiers exactly.',
        ]);
    }

    $sections = [];
    foreach (['## Project Context', '## Core Terminology', '## Translation Rules', '## Common Mistakes'] as $heading) {
        $section = extractMarkdownSection($guide, $heading);
        if ($section !== '') {
            $sections[] = $section;
        }
    }

    $context = trim(implode("\n\n", $sections));
    if ($context === '') {
        $context = trim($guide);
    }

    // Keep prompts compact while preserving the business/domain rules that materially affect quality.
    if (mb_strlen($context) > 7000) {
        $context = mb_substr($context, 0, 7000);
    }

    return $context;
}

function extractMarkdownSection(string $markdown, string $heading): string
{
    $quotedHeading = preg_quote($heading, '/');
    if (preg_match('/(' . $quotedHeading . '.*?)(?=\n## |\z)/s', $markdown, $matches) !== 1) {
        return '';
    }

    return trim($matches[1]);
}

function aiPretranslatePoFile(
    string $poFile,
    string $locale,
    string $sourceLocale,
    AiTranslationClient $client,
    string $context
): int {
    $entries = parsePoFile($poFile);
    $targetLanguageName = localeToLanguageName($locale);
    $sourceLanguageName = localeToLanguageName($sourceLocale);
    $pendingEntries = [];

    foreach ($entries as $index => $entry) {
        if (shouldAiPretranslateEntry($entry)) {
            $pendingEntries[$index] = $entry['msgid'];
        }
    }

    if ($pendingEntries === []) {
        return 0;
    }

    $translations = [];
    foreach (array_chunk($pendingEntries, DEFAULT_BATCH_SIZE, true) as $batch) {
        $batchTranslations = $client->translateBatch(
            array_values($batch),
            $sourceLanguageName,
            $targetLanguageName,
            $locale,
            $context
        );

        foreach ($batch as $index => $msgid) {
            if (!isset($batchTranslations[$msgid]) || trim($batchTranslations[$msgid]) === '') {
                continue;
            }

            $translations[$index] = trim($batchTranslations[$msgid]);
        }
    }

    if ($translations === []) {
        return 0;
    }

    foreach ($translations as $index => $translatedText) {
        $entries[$index]['msgstr'][''] = $translatedText;
    }

    writePoFile($poFile, $entries);

    return count($translations);
}

/**
 * @return array<int, array{
 *   comments: string[],
 *   msgctxt: ?string,
 *   msgid: string,
 *   msgid_plural: ?string,
 *   msgstr: array<string, string>
 * }>
 */
function parsePoFile(string $poFile): array
{
    $content = file_get_contents($poFile);
    if ($content === false) {
        throw new RuntimeException("Failed to read PO file '{$poFile}'.");
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $content);
    $trimmed = trim($normalized);
    if ($trimmed === '') {
        return [];
    }

    $blocks = preg_split("/\n{2,}/", $trimmed) ?: [];
    $entries = [];

    foreach ($blocks as $block) {
        $entries[] = parsePoEntry($block);
    }

    return $entries;
}

/**
 * @return array{
 *   comments: string[],
 *   msgctxt: ?string,
 *   msgid: string,
 *   msgid_plural: ?string,
 *   msgstr: array<string, string>
 * }
 */
function parsePoEntry(string $block): array
{
    $entry = [
        'comments' => [],
        'msgctxt' => null,
        'msgid' => '',
        'msgid_plural' => null,
        'msgstr' => [],
    ];

    $currentField = null;
    $currentIndex = '';
    $lines = explode("\n", $block);

    foreach ($lines as $line) {
        if ($line === '') {
            continue;
        }

        if (str_starts_with($line, '#')) {
            $entry['comments'][] = $line;
            $currentField = null;
            continue;
        }

        if (preg_match('/^(msgctxt|msgid|msgid_plural|msgstr(?:\[(\d+)\])?)\s+(".*")$/', $line, $matches) === 1) {
            $field = $matches[1];
            $value = decodePoString($matches[3]);
            $currentField = $field;
            $currentIndex = $matches[2] ?? '';

            if ($field === 'msgctxt') {
                $entry['msgctxt'] = $value;
            } elseif ($field === 'msgid') {
                $entry['msgid'] = $value;
            } elseif ($field === 'msgid_plural') {
                $entry['msgid_plural'] = $value;
            } else {
                $entry['msgstr'][$currentIndex] = $value;
            }

            continue;
        }

        if ($currentField !== null && preg_match('/^(".*")$/', $line, $matches) === 1) {
            $value = decodePoString($matches[1]);

            if ($currentField === 'msgctxt') {
                $entry['msgctxt'] .= $value;
            } elseif ($currentField === 'msgid') {
                $entry['msgid'] .= $value;
            } elseif ($currentField === 'msgid_plural') {
                $entry['msgid_plural'] .= $value;
            } else {
                $entry['msgstr'][$currentIndex] = ($entry['msgstr'][$currentIndex] ?? '') . $value;
            }
        }
    }

    if (!isset($entry['msgstr'][''])) {
        $entry['msgstr'][''] = '';
    }

    return $entry;
}

/**
 * @param array{
 *   comments: string[],
 *   msgctxt: ?string,
 *   msgid: string,
 *   msgid_plural: ?string,
 *   msgstr: array<string, string>
 * } $entry
 */
function shouldAiPretranslateEntry(array $entry): bool
{
    if ($entry['msgid'] === '') {
        return false;
    }

    if ($entry['msgid_plural'] !== null) {
        return false;
    }

    if ($entry['msgctxt'] !== null) {
        return false;
    }

    return trim($entry['msgstr'][''] ?? '') === '';
}

/**
 * @param array<int, array{
 *   comments: string[],
 *   msgctxt: ?string,
 *   msgid: string,
 *   msgid_plural: ?string,
 *   msgstr: array<string, string>
 * }> $entries
 */
function writePoFile(string $poFile, array $entries): void
{
    $blocks = [];

    foreach ($entries as $entry) {
        $lines = $entry['comments'];

        if ($entry['msgctxt'] !== null) {
            $lines[] = renderPoField('msgctxt', $entry['msgctxt']);
        }

        $lines[] = renderPoField('msgid', $entry['msgid']);

        if ($entry['msgid_plural'] !== null) {
            $lines[] = renderPoField('msgid_plural', $entry['msgid_plural']);
            ksort($entry['msgstr'], SORT_NUMERIC);
            foreach ($entry['msgstr'] as $index => $value) {
                $lines[] = renderPoField("msgstr[{$index}]", $value);
            }
        } else {
            $lines[] = renderPoField('msgstr', $entry['msgstr'][''] ?? '');
        }

        $blocks[] = implode(PHP_EOL, $lines);
    }

    $content = implode(PHP_EOL . PHP_EOL, $blocks) . PHP_EOL;
    if (file_put_contents($poFile, $content) === false) {
        throw new RuntimeException("Failed to write PO file '{$poFile}'.");
    }
}

function decodePoString(string $quotedString): string
{
    $quotedString = trim($quotedString);
    if (!str_starts_with($quotedString, '"') || !str_ends_with($quotedString, '"')) {
        throw new RuntimeException("Invalid PO string literal '{$quotedString}'.");
    }

    return stripcslashes(substr($quotedString, 1, -1));
}

function renderPoField(string $name, string $value): string
{
    return $name . ' "' . escapePoString($value) . '"';
}

function escapePoString(string $value): string
{
    return addcslashes($value, "\0..\37\"\\");
}

function localeToLanguageName(string $locale): string
{
    if (class_exists(Locale::class)) {
        $displayName = Locale::getDisplayLanguage($locale, 'en');
        if (is_string($displayName) && $displayName !== '') {
            return $displayName;
        }
    }

    $parts = preg_split('/[_@.]/', $locale) ?: [];
    return strtolower($parts[0] ?? $locale);
}

function compileCatalog(string $poFile, string $moFile): void
{
    $command = [
        'msgfmt',
        '--check-format',
        '--output-file=' . $moFile,
        $poFile,
    ];

    echo 'Compiling ' . basename($moFile) . PHP_EOL;
    runCommand($command);
}

function localePoToMoPath(string $poFile): string
{
    return preg_replace('/\.po$/', '.mo', $poFile) ?: $poFile . '.mo';
}

/**
 * @param string[] $command
 */
function runCommand(array $command): void
{
    $escapedCommand = implode(' ', array_map('escapeshellarg', $command));
    $output = [];
    $exitCode = 0;

    exec($escapedCommand . ' 2>&1', $output, $exitCode);

    if ($output !== []) {
        echo implode(PHP_EOL, $output) . PHP_EOL;
    }

    if ($exitCode !== 0) {
        throw new RuntimeException('Command failed: ' . implode(' ', $command));
    }
}

final class AiTranslationClient
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $model
    ) {
        if ($this->apiUrl === '' || $this->apiKey === '' || $this->model === '') {
            throw new RuntimeException(
                'AI translation is not configured. Set EPT_AI_API_URL, EPT_AI_API_KEY, and EPT_AI_MODEL.'
            );
        }
    }

    /**
     * @param string[] $strings
     * @return array<string, string>
     */
    public function translateBatch(
        array $strings,
        string $sourceLanguageName,
        string $targetLanguageName,
        string $targetLocale,
        string $businessContext
    ): array {
        $payload = [
            'model' => $this->model,
            'temperature' => 0,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => implode("\n\n", [
                        'You translate gettext application strings for a medical laboratory proficiency testing platform.',
                        "Source language: {$sourceLanguageName}",
                        "Target language: {$targetLanguageName} ({$targetLocale})",
                        'Preserve placeholders, HTML tags, URLs, emails, punctuation, and technical identifiers exactly.',
                        'Do not translate empty strings.',
                        'Return only valid JSON as an object mapping original msgid to translated text.',
                        'Never omit any key from the response.',
                        "Business context:\n{$businessContext}",
                    ]),
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'instructions' => 'Translate each value and return a JSON object with the same keys.',
                        'strings' => array_values($strings),
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ],
        ];

        $response = $this->requestJson($payload);
        $content = $response['choices'][0]['message']['content'] ?? null;

        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('AI translation response did not include message content.');
        }

        $decoded = $this->decodeJsonObject($content);
        $translations = [];

        foreach ($strings as $sourceText) {
            if (!array_key_exists($sourceText, $decoded) || !is_string($decoded[$sourceText])) {
                continue;
            }

            $translations[$sourceText] = $decoded[$sourceText];
        }

        return $translations;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function requestJson(array $payload): array
    {
        $handle = curl_init($this->apiUrl);
        if ($handle === false) {
            throw new RuntimeException('Failed to initialize cURL for AI translation request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => DEFAULT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => DEFAULT_TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $responseBody = curl_exec($handle);
        $httpCode = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if ($responseBody === false) {
            throw new RuntimeException('AI translation request failed: ' . $curlError);
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("AI translation request failed with HTTP {$httpCode}: {$responseBody}");
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('AI translation response was not valid JSON.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $content): array
    {
        $trimmed = trim($content);
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches) === 1) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('AI translation content was not valid JSON: ' . $content);
    }
}
