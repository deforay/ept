<?php
class  Pt_Helper_View_DateFormat extends Zend_View_Helper_Abstract
{

	public function dateFormat($dateIn, $returnTime = false)
	{
		$format = $this->getDateFormat();
		if ($format == 'dd-M-yy' || empty($format)) {
			$format = 'd-M-Y';
		}
		return Application_Service_Common::humanReadableDateFormat($dateIn, false, $format);
	}
	public function getDateFormat()
	{

		$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";

		// $config = new Zend_Config_Ini($file, APPLICATION_ENV, array('allowModifications'=>true, 'nestSeparator'=>"#"));
		$config = new Zend_Config_Ini($file, APPLICATION_ENV, array('allowModifications' => false));
		return $config->participant->dateformat;
	}
}
