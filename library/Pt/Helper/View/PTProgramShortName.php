<?php
class  Pt_Helper_View_PTProgramShortName extends Zend_View_Helper_Abstract {
	
	public function PTProgramShortName(){
	    $db = new Application_Model_DbTable_GlobalConfig();
	    return $db->getPTProgramShortName();
	}
}