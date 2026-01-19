<?php

/**
 * View helper for translating strings with proper escaping for different contexts.
 *
 * Usage in views:
 *   <?= $this->safeTranslate("Hello World", "js") ?>     // For JavaScript strings
 *   <?= $this->safeTranslate("Hello World", "html") ?>   // For HTML attributes
 *   <?= $this->safeTranslate("Hello World") ?>           // Plain (no escaping)
 *
 * Shorthand methods:
 *   <?= $this->jsTranslate("Hello World") ?>             // JavaScript-safe
 *   <?= $this->htmlTranslate("Hello World") ?>           // HTML attribute-safe
 */
class Pt_Helper_View_SafeTranslate extends Zend_View_Helper_Abstract
{
    /**
     * Translate a string and escape it for the specified context.
     *
     * @param string|null $text The text to translate
     * @param string $context The escaping context: 'js', 'html', or 'plain' (default)
     * @return string The translated and escaped string
     */
    public function safeTranslate(?string $text, string $context = 'plain'): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $translated = $this->getTranslatedText($text);

        return match ($context) {
            'js' => $this->escapeForJs($translated),
            'html' => htmlspecialchars($translated, ENT_QUOTES, 'UTF-8'),
            default => $translated,
        };
    }

    /**
     * Translate a string and escape it for use in JavaScript.
     *
     * @param string|null $text The text to translate
     * @return string The translated string safe for JavaScript
     */
    public function jsTranslate(?string $text): string
    {
        return $this->safeTranslate($text, 'js');
    }

    /**
     * Translate a string and escape it for use in HTML attributes.
     *
     * @param string|null $text The text to translate
     * @return string The translated string safe for HTML attributes
     */
    public function htmlTranslate(?string $text): string
    {
        return $this->safeTranslate($text, 'html');
    }

    /**
     * Get the translated text using the shared translation utility.
     *
     * @param string $text The text to translate
     * @return string The translated text
     */
    protected function getTranslatedText(string $text): string
    {
        return Pt_Commons_TranslateUtility::safeTranslate($text);
    }

    /**
     * Escape a string for safe use in JavaScript.
     *
     * Uses json_encode with flags to handle special characters,
     * then strips the surrounding quotes.
     *
     * @param string $text The text to escape
     * @return string The JavaScript-safe string
     */
    protected function escapeForJs(string $text): string
    {
        $encoded = json_encode(
            $text,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );

        // Remove surrounding quotes added by json_encode
        return substr($encoded, 1, -1);
    }
}
