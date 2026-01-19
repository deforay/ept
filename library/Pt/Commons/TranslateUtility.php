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
}
