<?php

/**
 * Translation utility for use in scheduled jobs and other non-view contexts.
 */
final class Pt_Commons_TranslateUtility
{
    /**
     * Translate a string using Zend_Translate from the registry.
     *
     * @param string|null $text The text to translate
     * @return string The translated text, or original text if translation fails
     */
    public static function safeTranslate(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        try {
            $translator = Zend_Registry::get('translate');
            return $translator->_($text);
        } catch (Exception $e) {
            return $text;
        }
    }

    /**
     * Translate a string and escape it for use in HTML content/attributes.
     *
     * @param string|null $text The text to translate
     * @return string The translated string safe for HTML
     */
    public static function htmlTranslate(?string $text): string
    {
        return htmlspecialchars(self::safeTranslate($text), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Translate a string and escape it for use in JavaScript.
     *
     * @param string|null $text The text to translate
     * @return string The translated string safe for JavaScript
     */
    public static function jsTranslate(?string $text): string
    {
        $translated = self::safeTranslate($text);
        $encoded = json_encode(
            $translated,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );
        // Remove surrounding quotes added by json_encode
        return substr($encoded, 1, -1);
    }
}
