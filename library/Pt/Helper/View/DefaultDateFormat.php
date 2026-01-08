<?php
class  Pt_Helper_View_DefaultDateFormat extends Zend_View_Helper_Abstract
{

	public function defaultDateFormat()
	{
		return Application_Service_Common::getConfig('date_format') ?? 'dd-M-yy';
	}
}
