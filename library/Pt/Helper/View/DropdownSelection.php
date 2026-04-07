<?php
class Pt_Helper_View_DropdownSelection extends Zend_View_Helper_Abstract
{

	public function dropdownSelection($allRecord, $selection = "", $ShowEmpty = false)
	{
		//	$allRecord = get_usertype_list();
		$translator = $this->view->translate ?? Zend_Registry::get('translate');

		if ($ShowEmpty == true) {
			echo '<option value="">--' . htmlspecialchars($translator->_("Select"), ENT_QUOTES, 'UTF-8') . '--</option>';
		}
		foreach ($allRecord as $key => $value) {
			// Option labels come from config/database sources, so translate and escape them before rendering.
			$translatedValue = ($value === null || $value === '')
				? ''
				: htmlspecialchars($translator->_((string) $value), ENT_QUOTES, 'UTF-8');

			echo '<option value="' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . '"';
			if ($selection == $key)
				echo " selected ";
			echo ">" . $translatedValue . '</option>';
		}
	}
}
