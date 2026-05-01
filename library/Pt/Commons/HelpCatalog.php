<?php

use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * In-app help catalog. Reads markdown files from docs/help/{audience}/{locale}/
 * and exposes them as a list + per-slug detail. Content is authored in the
 * repo, not the DB — editing a help page is a code change.
 *
 * Each file starts with a small frontmatter block:
 *
 *     ---
 *     title: Participants
 *     summary: Add, edit, and manage participant records
 *     tags: [participants, labs, enrolment]
 *     ---
 *     # Heading
 *     Body markdown...
 *
 * Locales fall back per-file to en_US when a translated copy doesn't exist.
 */
final class Pt_Commons_HelpCatalog
{
    public const FALLBACK_LOCALE = 'en_US';

    private string $audience;
    private string $locale;
    private string $rootDir;
    private GithubFlavoredMarkdownConverter $md;

    /**
     * @param string $audience 'admin' or 'participant'
     * @param string|null $locale e.g. 'fr_FR'; defaults to current Zend_Registry locale
     * @param string|null $rootDir override base — defaults to <APPLICATION_PATH>/../docs/help
     */
    public function __construct(string $audience, ?string $locale = null, ?string $rootDir = null)
    {
        if (!in_array($audience, ['admin', 'participant'], true)) {
            throw new InvalidArgumentException("Unknown help audience: $audience");
        }
        $this->audience = $audience;
        $this->locale = $locale ?: $this->resolveLocale();
        $this->rootDir = rtrim($rootDir ?? (APPLICATION_PATH . '/../docs/help'), '/');
        $this->md = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /** @return list<array{slug:string,title:string,summary:string,tags:list<string>}> */
    public function all(): array
    {
        // Union of slugs across the user's locale dir and the fallback dir, so
        // partially-translated catalogs still surface every topic.
        $slugs = array_unique(array_merge(
            $this->slugsIn($this->locale),
            $this->slugsIn(self::FALLBACK_LOCALE)
        ));

        $out = [];
        foreach ($slugs as $slug) {
            $file = $this->resolveFile($slug);
            if ($file === null) continue;
            $meta = $this->parseFrontmatter($file);
            if ($meta === null) continue;
            $out[] = [
                'slug' => $slug,
                'title' => (string) ($meta['title'] ?? $slug),
                'summary' => (string) ($meta['summary'] ?? ''),
                'tags' => array_values(array_map('strval', (array) ($meta['tags'] ?? []))),
            ];
        }

        usort($out, fn ($a, $b) => strcmp($a['title'], $b['title']));
        return $out;
    }

    /** @return array{slug:string,title:string,summary:string,tags:list<string>,html:string,locale:string}|null */
    public function find(string $slug): ?array
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') return null;

        $file = $this->resolveFile($slug);
        if ($file === null) return null;

        $meta = $this->parseFrontmatter($file);
        if ($meta === null) return null;

        $body = $this->stripFrontmatter((string) file_get_contents($file));
        $html = (string) $this->md->convert($body);
        $usedLocale = basename(dirname($file));

        return [
            'slug' => $slug,
            'title' => (string) ($meta['title'] ?? $slug),
            'summary' => (string) ($meta['summary'] ?? ''),
            'tags' => array_values(array_map('strval', (array) ($meta['tags'] ?? []))),
            'html' => $html,
            'locale' => $usedLocale,
        ];
    }

    private function resolveLocale(): string
    {
        try {
            $loc = Zend_Registry::isRegistered('Zend_Locale') ? (string) Zend_Registry::get('Zend_Locale') : '';
        } catch (Throwable $e) {
            $loc = '';
        }
        return $loc !== '' ? $loc : self::FALLBACK_LOCALE;
    }

    /** @return list<string> */
    private function slugsIn(string $locale): array
    {
        $dir = $this->rootDir . '/' . $this->audience . '/' . $locale;
        if (!is_dir($dir)) return [];
        $out = [];
        foreach (glob($dir . '/*.md') ?: [] as $file) {
            $out[] = pathinfo($file, PATHINFO_FILENAME);
        }
        return $out;
    }

    private function resolveFile(string $slug): ?string
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') return null;

        $primary = $this->rootDir . '/' . $this->audience . '/' . $this->locale . '/' . $slug . '.md';
        if (is_file($primary)) return $primary;

        $fallback = $this->rootDir . '/' . $this->audience . '/' . self::FALLBACK_LOCALE . '/' . $slug . '.md';
        if (is_file($fallback)) return $fallback;

        return null;
    }

    private function sanitizeSlug(string $slug): string
    {
        return (string) preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
    }

    /** @return array<string,mixed>|null */
    private function parseFrontmatter(string $file): ?array
    {
        $raw = (string) file_get_contents($file);
        if ($raw === '') return null;

        if (!str_starts_with($raw, "---\n")) {
            return ['title' => pathinfo($file, PATHINFO_FILENAME)];
        }

        $end = strpos($raw, "\n---", 4);
        if ($end === false) return null;

        $block = substr($raw, 4, $end - 4);
        return $this->parseYamlLike($block);
    }

    private function stripFrontmatter(string $raw): string
    {
        if (!str_starts_with($raw, "---\n")) return $raw;
        $end = strpos($raw, "\n---", 4);
        if ($end === false) return $raw;
        return ltrim(substr($raw, $end + 4));
    }

    /**
     * Tiny YAML-ish parser — only what help frontmatter needs:
     *   key: value
     *   tags: [a, b, c]
     *
     * @return array<string,mixed>
     */
    private function parseYamlLike(string $block): array
    {
        $out = [];
        foreach (explode("\n", $block) as $line) {
            if (!preg_match('/^([a-z_]+)\s*:\s*(.*)$/i', trim($line), $m)) continue;
            $key = $m[1];
            $val = trim($m[2]);
            if ($val === '') { $out[$key] = ''; continue; }
            if (str_starts_with($val, '[') && str_ends_with($val, ']')) {
                $items = substr($val, 1, -1);
                $parts = array_filter(array_map('trim', explode(',', $items)), fn ($s) => $s !== '');
                $out[$key] = array_map(fn ($s) => trim($s, "'\""), $parts);
                continue;
            }
            $out[$key] = trim($val, "'\"");
        }
        return $out;
    }
}
