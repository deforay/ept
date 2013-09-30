<?php

class Application_Model_DbTable_GlobalConfig extends Zend_Db_Table_Abstract
{

    protected $_name = 'global_config';
    protected $_primary = 'name';

    public function getValue($name){
        $res = $this->getAdapter()->fetchCol($this->select()
                               ->from($this->_name, array('value'))
                              ->where("name='".$name."'"));
        return $res[0];
    }

}

