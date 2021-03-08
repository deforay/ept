<?php
class  Pt_Helper_View_PTProgramName extends Zend_View_Helper_Abstract {
	
	public function PTProgramName(){
	    $db = new Application_Model_DbTable_GlobalConfig();
	    return $db->getPTProgramName();
	}
}