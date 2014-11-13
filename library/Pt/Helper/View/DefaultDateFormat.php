<?php
class  Pt_Helper_View_DefaultDateFormat extends Zend_View_Helper_Abstract {

	public function defaultDateFormat(){

		$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
			
		// $config = new Zend_Config_Ini($file, APPLICATION_ENV, array('allowModifications'=>true, 'nestSeparator'=>"#"));
		$config = new Zend_Config_Ini($file, APPLICATION_ENV, array('allowModifications' => false));
			
		return $config->participant->dateformat;
	}

}