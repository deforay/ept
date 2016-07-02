<?php
class  Pt_Helper_View_GetTextUnderLogo extends Zend_View_Helper_Abstract {
	
	public function getTextUnderLogo(){
	    $db = new Application_Model_DbTable_GlobalConfig();
	    return $db->getTextUnderLogoContent();
	}
}