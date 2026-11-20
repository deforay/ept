<?php

class Pt_Helper_View_DropdownSelection extends Zend_View_Helper_Abstract
{
    /**
     * @param bool $uppercase Upper-case the label *after* translation. Doing it before
     *                        would force the catalog to carry uppercase duplicates of
     *                        every DB-backed label, and strtoupper() is byte-based so it
     *                        mangles accented translations.
     * @param array $retired  Keys of retired (status='inactive') options. They are left out
     *                        unless one is the current selection, so a value already saved
     *                        still shows while new entry cannot pick a retired result.
     */
    public function dropdownSelection($allRecord, $selection = '', $ShowEmpty = false, $uppercase = false, $retired = [])
    {
        $retiredSet = [];
        foreach ((array) $retired as $r) {
            $retiredSet[(string) $r] = true;
        }

        //	$allRecord = get_usertype_list();
        $translator = $this->view->translate ?? Zend_Registry::get('translate');

        if ($ShowEmpty == true) {
            echo '<option value="">--' . htmlspecialchars($translator->_('Select'), ENT_QUOTES, 'UTF-8') . '--</option>';
        }
        foreach ($allRecord as $key => $value) {
            if (isset($retiredSet[(string) $key]) && (string) $selection !== (string) $key) {
                continue;
            }
            // Option labels come from config/database sources, so translate and escape them before rendering.
            if ($value === null || $value === '') {
                $translatedValue = '';
            } else {
                $label = $translator->_((string) $value);
                if ($uppercase) {
                    $label = mb_strtoupper($label, 'UTF-8');
                }
                $translatedValue = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            }

            echo '<option value="' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . '"';
            if ($selection == $key) {
                echo ' selected ';
            }
            echo '>' . $translatedValue . '</option>';
        }
    }
}
