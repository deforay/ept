<?php

class Application_Model_DbTable_SystemConfig extends Zend_Db_Table_Abstract
{

    protected $_name = 'system_config';
    protected $_primary = 'name';

    public function getValue($version)
    {
        return $this->getAdapter()->fetchRow($this->select()
            ->from($this->_name, array('value'))
            ->where("value='" . $version . "' AND name='app_version'"));    
    }
}

