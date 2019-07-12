<?php

class Application_Model_DbTable_Countries extends Zend_Db_Table_Abstract
{

    protected $_name = 'countries';
    
    public function getAllCountries(){
		$sql = $this->select();
		return $this->fetchAll($sql);
	}


}

