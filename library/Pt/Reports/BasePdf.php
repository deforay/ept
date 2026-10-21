<?php

/**
 * Base class for every ePT PDF that renders HTML.
 *
 * Report HTML carries text that participants typed, such as lab names and
 * comments, and TCPDF 6 receives no security fixes. Each HTML tag passes
 * through openHTMLTagHandler() after TCPDF has parsed it, so the checks here
 * see exactly what TCPDF would act on:
 *  - an image must be raster data, or an image file inside public/ or downloads/
 *  - a link may only be #..., mailto: or http(s)://, since admin-written
 *    report text such as contact blocks carries mailto and web links
 *  - a form field may only be a checkbox or a radio button, with no script
 *  - forms, text boxes, selects and <tcpdf> tags render as plain text
 * Anything else is dropped and logged. This stops injected HTML from fetching
 * URLs, embedding server files, adding scripts or script links, or stopping a report
 * batch (TCPDF throws on a <form> without an action).
 */
class Pt_Reports_BasePdf extends TCPDF
{
    private const INERT_TAGS = ['form', 'textarea', 'select', 'tcpdf'];
    private const ALLOWED_INPUT_TYPES = ['checkbox', 'radio'];

    /** @var array<string, bool> */
    private static array $imageVerdicts = [];

    protected function openHTMLTagHandler($dom, $key, $cell)
    {
        $tag = (string) ($dom[$key]['value'] ?? '');
        $attributes = $dom[$key]['attribute'] ?? [];

        if (is_array($attributes)) {
            foreach (array_keys($attributes) as $name) {
                if (str_starts_with(strtolower((string) $name), 'on')) {
                    unset($dom[$key]['attribute'][$name]);
                }
            }
        }

        if (in_array($tag, self::INERT_TAGS, true)) {
            self::logDropped($tag, '');
            $dom[$key]['value'] = 'span';
        } elseif ($tag === 'input') {
            $type = strtolower((string) ($attributes['type'] ?? 'text'));
            if (!in_array($type, self::ALLOWED_INPUT_TYPES, true)) {
                self::logDropped('input', $type);
                $dom[$key]['value'] = 'span';
            }
        } elseif ($tag === 'img' && isset($attributes['src'])) {
            $src = (string) $attributes['src'];
            if ($src !== '' && !self::isAllowedImage($src)) {
                self::logDropped('img', $src);
                $dom[$key]['attribute']['src'] = '';
            }
        } elseif ($tag === 'a' && isset($attributes['href'])) {
            $href = trim((string) $attributes['href']);
            if (!preg_match('~^(#|mailto:|https?://)~i', $href)) {
                self::logDropped('a', $href);
                unset($dom[$key]['attribute']['href']);
            }
        }

        return parent::openHTMLTagHandler($dom, $key, $cell);
    }

    private static function isAllowedImage(string $src): bool
    {
        if (isset(self::$imageVerdicts[$src])) {
            return self::$imageVerdicts[$src];
        }

        $data = null;
        if ($src[0] === '@') {
            $data = base64_decode(substr($src, 1), true);
        } elseif (preg_match('@^data:image/[^;]*;base64,(.*)@s', $src, $match)) {
            $data = base64_decode($match[1], true);
        }

        if ($data !== null) {
            // Raster only. SVG can make TCPDF fetch the files it references.
            $allowed = $data !== false && @getimagesizefromstring($data) !== false;
        } else {
            $path = realpath($src);
            $allowed = $path !== false && is_file($path)
                && self::isInsideAllowedFolder($path)
                && @getimagesize($path) !== false;
        }

        // Data images are large and rarely repeat, so only file paths are cached
        if ($data === null) {
            self::$imageVerdicts[$src] = $allowed;
        }
        return $allowed;
    }

    private static function isInsideAllowedFolder(string $path): bool
    {
        foreach ([WEB_ROOT, DOWNLOADS_FOLDER] as $folder) {
            $root = realpath($folder);
            if ($root !== false && str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }
        return false;
    }

    private static function logDropped(string $tag, string $value): void
    {
        Pt_Commons_LoggerUtility::logWarning('PDF HTML: dropped a disallowed <' . $tag . '> tag or attribute', [
            'value' => mb_substr($value, 0, 200),
        ]);
    }
}
