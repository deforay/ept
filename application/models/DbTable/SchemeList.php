<?php

class Application_Model_DbTable_SchemeList extends Zend_Db_Table_Abstract
{

    protected $_name = 'scheme_list';
    protected $_primary = 'scheme_id';

    public function getAllSchemes(){
        return $this->fetchAll($this->select()->where("status='active'"));
    }


}

