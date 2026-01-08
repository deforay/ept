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
		return Application_Service_Common::getConfig('date_format') ?? 'dd-M-yy';
	}
}
