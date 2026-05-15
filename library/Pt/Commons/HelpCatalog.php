<?php

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\Yaml\Yaml;

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
            if ($file === null) {
                continue;
            }
            $meta = $this->parseFrontmatter($file);
            if ($meta === null) {
                continue;
            }
            // Skip internal/schema files like guides/README.md
            if (!empty($meta['internal'])) {
                continue;
            }
            $out[] = [
                'slug' => $slug,
                'title' => Pt_Commons_TranslateUtility::safeTranslate((string) ($meta['title'] ?? $slug)),
                'summary' => Pt_Commons_TranslateUtility::safeTranslate((string) ($meta['summary'] ?? '')),
                'tags' => array_values(array_map(
                    fn ($t) => Pt_Commons_TranslateUtility::safeTranslate((string) $t),
                    (array) ($meta['tags'] ?? [])
                )),
            ];
        }

        usort($out, fn ($a, $b) => strcmp($a['title'], $b['title']));
        return $out;
    }

    /**
     * List all workflow guides (frontmatter only).
     *
     * @return list<array{slug:string,title:string,summary:string,estimated_minutes:int,tags:list<string>}>
     */
    public function guides(): array
    {
        $slugs = array_unique(array_merge(
            $this->guideSlugsIn($this->locale),
            $this->guideSlugsIn(self::FALLBACK_LOCALE)
        ));

        $out = [];
        foreach ($slugs as $slug) {
            $file = $this->resolveGuideFile($slug);
            if ($file === null) {
                continue;
            }
            $meta = $this->parseFrontmatter($file);
            if ($meta === null || !empty($meta['internal'])) {
                continue;
            }
            $out[] = [
                'slug' => $slug,
                'title' => Pt_Commons_TranslateUtility::safeTranslate((string) ($meta['title'] ?? $slug)),
                'summary' => Pt_Commons_TranslateUtility::safeTranslate((string) ($meta['summary'] ?? '')),
                'estimated_minutes' => (int) ($meta['estimated_minutes'] ?? 0),
                'tags' => array_values(array_map(
                    fn ($t) => Pt_Commons_TranslateUtility::safeTranslate((string) $t),
                    (array) ($meta['tags'] ?? [])
                )),
            ];
        }

        usort($out, fn ($a, $b) => strcmp($a['title'], $b['title']));
        return $out;
    }

    /**
     * Load a single guide with parsed steps.
     *
     * Each step has its own body, split from the markdown on H2 headings.
     * `target_pages` lets the drawer match the user's current screen and
     * decide whether to show "✓ You're here" or an "Open this screen" link.
     *
     * @return array{slug:string,title:string,summary:string,estimated_minutes:int,tags:list<string>,locale:string,steps:list<array{id:int,title:string,target_pages:list<string>,html:string}>}|null
     */
    public function findGuide(string $slug): ?array
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        $file = $this->resolveGuideFile($slug);
        if ($file === null) {
            return null;
        }

        $meta = $this->parseFrontmatter($file);
        if ($meta === null || !empty($meta['internal'])) {
            return null;
        }

        $body = $this->stripFrontmatter((string) file_get_contents($file));
        $stepBodies = $this->splitBodyOnH2($body);

        $steps = [];
        foreach ((array) ($meta['steps'] ?? []) as $i => $stepMeta) {
            $stepMeta = (array) $stepMeta;
            $id = (int) ($stepMeta['id'] ?? ($i + 1));
            $bodyMd = $stepBodies[$i] ?? '';
            $steps[] = [
                'id' => $id,
                'title' => Pt_Commons_TranslateUtility::safeTranslate((string) ($stepMeta['title'] ?? "Step $id")),
                'target_pages' => array_values(array_map(
                    fn ($s) => (string) $s,
                    (array) ($stepMeta['target_pages'] ?? [])
                )),
                'html' => $bodyMd === '' ? '' : (string) $this->md->convert($bodyMd),
            ];
        }

        return [
            'slug' => $slug,
            'title' => Pt_Commons_TranslateUtility::safeTranslate((string) ($meta['title'] ?? $slug)),
            'summary' => Pt_Commons_TranslateUtility::safeTranslate((string) ($meta['summary'] ?? '')),
            'estimated_minutes' => (int) ($meta['estimated_minutes'] ?? 0),
            'tags' => array_values(array_map(
                fn ($t) => Pt_Commons_TranslateUtility::safeTranslate((string) $t),
                (array) ($meta['tags'] ?? [])
            )),
            'locale' => basename(dirname(dirname($file))),
            'steps' => $steps,
        ];
    }

    /** @return array{slug:string,title:string,summary:string,tags:list<string>,html:string,locale:string}|null */
    public function find(string $slug): ?array
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        $file = $this->resolveFile($slug);
        if ($file === null) {
            return null;
        }

        $meta = $this->parseFrontmatter($file);
        if ($meta === null) {
            return null;
        }

        $body = $this->stripFrontmatter((string) file_get_contents($file));
        $html = (string) $this->md->convert($body);
        $usedLocale = basename(dirname($file));

        return [
            'slug' => $slug,
            'title' => Pt_Commons_TranslateUtility::safeTranslate((string) ($meta['title'] ?? $slug)),
            'summary' => Pt_Commons_TranslateUtility::safeTranslate((string) ($meta['summary'] ?? '')),
            'tags' => array_values(array_map(
                fn ($t) => Pt_Commons_TranslateUtility::safeTranslate((string) $t),
                (array) ($meta['tags'] ?? [])
            )),
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
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob($dir . '/*.md') ?: [] as $file) {
            $out[] = pathinfo($file, PATHINFO_FILENAME);
        }
        return $out;
    }

    /** @return list<string> */
    private function guideSlugsIn(string $locale): array
    {
        $dir = $this->rootDir . '/' . $this->audience . '/' . $locale . '/guides';
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob($dir . '/*.md') ?: [] as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if ($name === 'README') {
                continue; // schema doc, not a guide
            }
            $out[] = $name;
        }
        return $out;
    }

    private function resolveFile(string $slug): ?string
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        $primary = $this->rootDir . '/' . $this->audience . '/' . $this->locale . '/' . $slug . '.md';
        if (is_file($primary)) {
            return $primary;
        }

        $fallback = $this->rootDir . '/' . $this->audience . '/' . self::FALLBACK_LOCALE . '/' . $slug . '.md';
        if (is_file($fallback)) {
            return $fallback;
        }

        return null;
    }

    private function resolveGuideFile(string $slug): ?string
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        $primary = $this->rootDir . '/' . $this->audience . '/' . $this->locale . '/guides/' . $slug . '.md';
        if (is_file($primary)) {
            return $primary;
        }

        $fallback = $this->rootDir . '/' . $this->audience . '/' . self::FALLBACK_LOCALE . '/guides/' . $slug . '.md';
        if (is_file($fallback)) {
            return $fallback;
        }

        return null;
    }

    /**
     * Split a guide's markdown body into one chunk per H2.
     *
     * The H2 line itself is dropped — the drawer renders the step title
     * from frontmatter as a styled header, so leaving the H2 inside the
     * body too would duplicate the title.
     *
     * Anything before the first H2 is dropped — that's intro prose that
     * belongs above the steps, not inside step 1.
     *
     * @return list<string>
     */
    private function splitBodyOnH2(string $body): array
    {
        $lines = explode("\n", $body);
        $chunks = [];
        $buf = null;
        foreach ($lines as $line) {
            if (preg_match('/^##\s+/', $line)) {
                if ($buf !== null) {
                    $chunks[] = ltrim(rtrim($buf));
                }
                $buf = ''; // start a new chunk, drop the H2 line
            } elseif ($buf !== null) {
                $buf .= $line . "\n";
            }
        }
        if ($buf !== null) {
            $chunks[] = ltrim(rtrim($buf));
        }
        return $chunks;
    }

    private function sanitizeSlug(string $slug): string
    {
        return (string) preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
    }

    /** @return array<string,mixed>|null */
    private function parseFrontmatter(string $file): ?array
    {
        $raw = (string) file_get_contents($file);
        if ($raw === '') {
            return null;
        }

        if (!str_starts_with($raw, "---\n")) {
            return ['title' => pathinfo($file, PATHINFO_FILENAME)];
        }

        $end = strpos($raw, "\n---", 4);
        if ($end === false) {
            return null;
        }

        $block = substr($raw, 4, $end - 4);
        try {
            $parsed = Yaml::parse($block);
        } catch (Throwable $e) {
            return null;
        }
        return is_array($parsed) ? $parsed : null;
    }

    private function stripFrontmatter(string $raw): string
    {
        if (!str_starts_with($raw, "---\n")) {
            return $raw;
        }
        $end = strpos($raw, "\n---", 4);
        if ($end === false) {
            return $raw;
        }
        return ltrim(substr($raw, $end + 4));
    }
}
