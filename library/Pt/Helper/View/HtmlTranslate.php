<?php

/**
 * View helper for translating strings safely for HTML attribute context.
 *
 * Usage in views:
 *   <input placeholder="<?= $this->htmlTranslate("Enter name") ?>">
 *
 * This escapes special characters to prevent XSS when embedding
 * translated strings in HTML attributes.
 */
class Pt_Helper_View_HtmlTranslate extends Zend_View_Helper_Abstract
{
    /**
     * Translate a string and escape it for use in HTML attributes.
     *
     * @param string|null $text The text to translate
     * @return string The translated string safe for HTML attributes
     */
    public function htmlTranslate(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $translator = $this->view->translate ?? Zend_Registry::get('translate');
        $translated = $translator->_($text);

        return htmlspecialchars($translated, ENT_QUOTES, 'UTF-8');
    }
}
