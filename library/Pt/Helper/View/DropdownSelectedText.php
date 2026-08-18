<?php

class Pt_Helper_View_DropdownSelectedText extends Zend_View_Helper_Abstract
{
    /** @param bool $uppercase Upper-case after translation, matching dropdownSelection(). */
    public function dropdownSelectedText($allRecord, $selection = '', $uppercase = false)
    {
        $translator = $this->view->translate ?? Zend_Registry::get('translate');

        foreach ($allRecord as $key => $value) {
            if ($selection == $key) {
                // Keep selected-text rendering aligned with dropdownSelection translations.
                if ($value === null || $value === '') {
                    echo '';
                    continue;
                }
                $label = $translator->_((string) $value);
                if ($uppercase) {
                    $label = mb_strtoupper($label, 'UTF-8');
                }
                echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            }
        }
    }
}
