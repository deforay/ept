<?php

class Application_Model_DbTable_ModeOfReceipt extends Zend_Db_Table_Abstract
{

    protected $_name = 'r_modes_of_receipt';
    
    public function fetchAllModeOfReceipt(){
		$sql = $this->select()->order("mode_id ASC");
		return $this->fetchAll($sql);
	}


}

