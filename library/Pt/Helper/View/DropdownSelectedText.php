<?php
class Pt_Helper_View_DropdownSelectedText extends Zend_View_Helper_Abstract
{
    public function dropdownSelectedText($allRecord, $selection = "")
    {
        $translator = $this->view->translate ?? Zend_Registry::get('translate');

        foreach ($allRecord as $key => $value) {
            if ($selection == $key) {
                // Keep selected-text rendering aligned with dropdownSelection translations.
                $translatedValue = ($value === null || $value === '')
                    ? ''
                    : htmlspecialchars($translator->_((string) $value), ENT_QUOTES, 'UTF-8');

                echo $translatedValue;
            }
        }
    }
}
