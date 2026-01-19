<?php

/**
 * View helper for translating strings safely for JavaScript context.
 *
 * Usage in views:
 *   <?= $this->jsTranslate("Hello World") ?>
 *
 * This escapes special characters to prevent XSS and syntax errors
 * when embedding translated strings in JavaScript.
 */
class Pt_Helper_View_JsTranslate extends Zend_View_Helper_Abstract
{
    /**
     * Translate a string and escape it for use in JavaScript.
     *
     * @param string|null $text The text to translate
     * @return string The translated string safe for JavaScript
     */
    public function jsTranslate(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $translator = $this->view->translate ?? Zend_Registry::get('translate');
        $translated = $translator->_($text);

        $encoded = json_encode(
            $translated,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );

        // Remove surrounding quotes added by json_encode
        return substr($encoded, 1, -1);
    }
}
