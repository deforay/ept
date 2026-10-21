<?php

/**
 * Base class for every ePT PDF that renders HTML.
 *
 * Report HTML carries text that participants typed, such as lab names and
 * comments, and TCPDF 6 receives no security fixes. cleanHtml() checks the
 * HTML string before writeHTML() and writeHTMLCell() hand it to the library,
 * so the same check works on TCPDF 6, on TCPDF 7 and on tc-lib-pdf, which all
 * take that string.
 *
 * Each tag is split out the way both libraries split it (<[^>]+>), checked,
 * and written back with double-quoted attribute values, so the library sees
 * only the attributes that passed:
 *  - an image must be raster data, or a raster file inside public/, uploads,
 *    temporary files or downloads/. An approved file becomes its real path, so
 *    the library loads exactly the file that was checked.
 *  - a link may only be #..., mailto: or http(s)://, since admin-written
 *    report text such as contact blocks carries mailto and web links
 *  - a form field may only be a checkbox or a radio button, since response
 *    copies show the ticked boxes
 *  - forms, text boxes, selects and <tcpdf> tags render as plain text
 *  - <link>, inline <svg> and comments are removed, and so are on* attributes
 *    and any other attribute that names a file or URL
 *  - CSS may not use url(), @import or an image list bullet (img|...)
 * Anything else is dropped and logged. This stops injected HTML from fetching
 * URLs, embedding server files, adding scripts or script links, or stopping a
 * report batch (TCPDF 6 throws on a <form> without an action).
 *
 * vlsm's App\Helpers\BasePdf is the same check, so fixes belong in both.
 */
class Pt_Reports_BasePdf extends TCPDF
{
    private const INERT_TAGS = ['form', 'textarea', 'select', 'tcpdf'];
    private const REMOVED_TAGS = ['link', 'svg', 'script', 'style', 'meta', 'base', 'object', 'embed', 'iframe', 'image', 'use'];
    private const ALLOWED_INPUT_TYPES = ['checkbox', 'radio'];
    private const URL_ATTRIBUTES = ['src', 'href', 'background', 'action', 'formaction', 'data', 'srcset', 'poster', 'lowsrc', 'dynsrc'];
    private const TAG_PATTERN = '/<[^>]+>/';
    private const ATTRIBUTE_PATTERN = '/([a-zA-Z_:][a-zA-Z0-9_:\-\.]*)\s*(?:=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'`=<>]+)))?/';

    /** @var array<string, string|false> real path of an approved image file, or false when refused */
    private static array $imageSources = [];

    /**
     * TCPDF 6's writeHTMLCell() passes its cleaned HTML on to writeHTML(), which
     * then skips cleaning it again. A header or footer drawn at a page break
     * inside writeHTML() is different HTML, so it is still cleaned.
     */
    private ?string $cleanedHtml = null;

    /**
     * Parameters as loose as TCPDF's own, since layouts pass 0 and 1 for flags.
     *
     * @param mixed $html
     * @param mixed $ln
     * @param mixed $fill
     * @param mixed $reseth
     * @param mixed $cell
     * @param mixed $align
     */
    public function writeHTML($html, $ln = true, $fill = false, $reseth = false, $cell = false, $align = '')
    {
        return $this->writeCleanHtml((string) $html, fn (string $clean) => parent::writeHTML($clean, $ln, $fill, $reseth, $cell, $align));
    }

    /**
     * @param mixed $w
     * @param mixed $h
     * @param mixed $x
     * @param mixed $y
     * @param mixed $html
     * @param mixed $border
     * @param mixed $ln
     * @param mixed $fill
     * @param mixed $reseth
     * @param mixed $align
     * @param mixed $autopadding
     */
    public function writeHTMLCell($w, $h, $x, $y, $html = '', $border = 0, $ln = 0, $fill = false, $reseth = true, $align = '', $autopadding = true)
    {
        return $this->writeCleanHtml((string) $html, fn (string $clean) => parent::writeHTMLCell($w, $h, $x, $y, $clean, $border, $ln, $fill, $reseth, $align, $autopadding));
    }

    /**
     * An approved image src is already a real path. TCPDF 6 prefixes
     * DOCUMENT_ROOT to a path that does not contain it, and then tries other
     * locations and URLs for a file it cannot find, so the library sees no
     * DOCUMENT_ROOT while it renders.
     */
    private function writeCleanHtml(string $html, callable $write): mixed
    {
        if ($html !== $this->cleanedHtml) {
            $html = self::cleanHtml($html);
            $this->cleanedHtml = $html;
        }
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = '';
        try {
            return $write($html);
        } finally {
            $_SERVER['DOCUMENT_ROOT'] = $documentRoot;
        }
    }

    /** The HTML with every tag checked and rebuilt, as described on the class */
    public static function cleanHtml(string $html): string
    {
        // Whole elements whose content is code or vector markup, not text
        $html = (string) preg_replace_callback('~<(svg|script|style)\b[^>]*>.*?</\1\s*>~is', function (array $match): string {
            self::logDropped($match[1], $match[0]);
            return '';
        }, $html);
        // A < that cannot start a tag is text, as in "± 2.0 > z < ± 3.0"
        $html = (string) preg_replace('/<(?![a-zA-Z\/!?])/', '&lt;', $html);

        return (string) preg_replace_callback(self::TAG_PATTERN, fn (array $match): string => self::cleanTag($match[0]), $html);
    }

    private static function cleanTag(string $tag): string
    {
        $inner = substr($tag, 1, -1);
        $closing = str_starts_with($inner, '/');
        preg_match('/^\/?([a-zA-Z0-9]*)/', $inner, $nameMatch);
        $name = strtolower($nameMatch[1] ?? '');

        if ($name === '' || in_array($name, self::REMOVED_TAGS, true)) {
            self::logDropped($name === '' ? 'markup' : $name, $tag);
            return '';
        }
        if (in_array($name, self::INERT_TAGS, true)) {
            if (!$closing) {
                self::logDropped($name, '');
            }
            return $closing ? '</span>' : '<span>';
        }
        if ($closing) {
            return '</' . $name . '>';
        }

        $attributes = [];
        preg_match_all(self::ATTRIBUTE_PATTERN, substr($inner, strlen($nameMatch[0])), $found, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        foreach ($found as $attribute) {
            // A valueless attribute such as "checked" stays valueless (null)
            $attributes[strtolower((string) $attribute[1])] = $attribute[2] ?? $attribute[3] ?? $attribute[4];
        }

        if ($name === 'input') {
            $type = strtolower((string) ($attributes['type'] ?? 'text'));
            if (!in_array($type, self::ALLOWED_INPUT_TYPES, true)) {
                self::logDropped('input', $type);
                return '';
            }
        }

        foreach ($attributes as $attribute => $value) {
            $keep = match (true) {
                str_starts_with($attribute, 'on') => false,
                $attribute === 'style' => true,
                $value !== null && str_starts_with(strtolower(trim($value)), 'img|') => false,
                $name === 'img' && $attribute === 'src' => true,
                $name === 'a' && $attribute === 'href' => (bool) preg_match('~^(#|mailto:|https?://)~i', trim((string) $value)),
                in_array($attribute, self::URL_ATTRIBUTES, true), str_contains($attribute, ':') => false,
                default => true,
            };
            if (!$keep) {
                self::logDropped($name . ' ' . $attribute, (string) $value);
                unset($attributes[$attribute]);
            }
        }

        if (isset($attributes['style'])) {
            $attributes['style'] = self::cleanCss(html_entity_decode((string) $attributes['style'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if ($name === 'img' && ($attributes['src'] ?? '') !== '') {
            $source = self::imageSource((string) $attributes['src']);
            if ($source === false) {
                self::logDropped('img', (string) $attributes['src']);
                return '';
            }
            $attributes['src'] = $source;
        }

        $out = '<' . $name;
        foreach ($attributes as $attribute => $value) {
            // A > in a value would end the tag early when the library splits it
            $out .= ' ' . $attribute . ($value === null ? '' : '="' . str_replace(['"', '<', '>'], ['&quot;', '&lt;', '&gt;'], $value) . '"');
        }
        return $out . (str_ends_with($inner, '/') ? ' />' : '>');
    }

    /** Removes the declarations that load a file: url(), @import, image list bullets and expression() */
    private static function cleanCss(string $css): string
    {
        return (string) preg_replace_callback('/[^;]*(url\s*\(|@import|img\||expression\s*\()[^;]*;?/i', function (array $match): string {
            self::logDropped('style', $match[0]);
            return '';
        }, $css);
    }

    /**
     * The src the library should load for an image, or false to drop it.
     *
     * A data image becomes data:image/<type>, with the type read from its
     * bytes, because TCPDF treats an '@' stream that contains "<svg" anywhere
     * as SVG, even a PNG with that text in its metadata. A file becomes its
     * real path, so the library stores an image used many times only once.
     */
    private static function imageSource(string $src): string|false
    {
        $data = null;
        if ($src[0] === '@') {
            $data = base64_decode(substr($src, 1), true);
        } elseif (preg_match('@^data:image/[^;]*;base64,(.*)@s', $src, $match)) {
            $data = base64_decode($match[1], true);
        }

        if ($data !== null) {
            // Raster only. SVG can make the library fetch the files it references.
            $size = $data === false ? false : @getimagesizefromstring($data);
            return $size === false ? false : 'data:' . $size['mime'] . ';base64,' . base64_encode($data);
        }

        // Data images are large and rarely repeat, so only files are cached
        if (!array_key_exists($src, self::$imageSources)) {
            $file = self::imageFileInAllowedFolder($src);
            // The library picks its parser for a file from the extension: .svg,
            // .ai and .eps go to the vector parsers whatever the file holds.
            $vector = $file !== null
                && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['svg', 'ai', 'eps'], true);
            self::$imageSources[$src] = $file !== null && !$vector && @getimagesize($file) !== false
                ? $file
                : false;
        }
        return self::$imageSources[$src];
    }

    /**
     * Report layouts pass absolute paths under public/ and downloads/. A
     * root-relative path such as /images/check.jpg also resolves under public/,
     * as TCPDF itself would resolve it on the web. Uploads and temporary files
     * are listed on their own, so they still match when a server keeps them
     * on another disk behind a symlink.
     */
    private static function imageFileInAllowedFolder(string $src): ?string
    {
        $webRoot = realpath(WEB_ROOT);
        $roots = array_filter([$webRoot, realpath(UPLOAD_PATH), realpath(TEMP_UPLOAD_PATH), realpath(DOWNLOADS_FOLDER)]);
        $candidates = [$src];
        if ($webRoot !== false && $src[0] === '/') {
            $candidates[] = $webRoot . urldecode($src);
        }
        foreach ($candidates as $candidate) {
            $path = realpath($candidate);
            if ($path === false || !is_file($path)) {
                continue;
            }
            foreach ($roots as $root) {
                if (str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
                    return $path;
                }
            }
        }
        return null;
    }

    private static function logDropped(string $tag, string $value): void
    {
        Pt_Commons_LoggerUtility::logWarning('PDF HTML: dropped a disallowed <' . $tag . '> tag or attribute', [
            'value' => mb_substr($value, 0, 200),
        ]);
    }
}
