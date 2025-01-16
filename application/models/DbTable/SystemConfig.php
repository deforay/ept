<?php

class Application_Model_DbTable_SystemConfig extends Zend_Db_Table_Abstract
{

    protected $_name = 'system_config';
    protected $_primary = 'config';

    public function getValue($version)
    {
        return $this->getAdapter()->fetchRow($this->select()
            ->from($this->_name, ['value'])
            ->where("value='$version' AND config='app_version'"));
    }

    public function getValueByName($configName)
    {
        return $this->getAdapter()->fetchRow($this->select()
            ->from($this->_name, ['value'])
            ->where("config='$configName'"));
    }
}
