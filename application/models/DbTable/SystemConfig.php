<?php

class Application_Model_DbTable_SystemConfig extends Zend_Db_Table_Abstract
{

    protected $_name = 'system_config';

    public function getValueByName($configName)
    {
        if(empty($configName)) {
            return null;
        }

        return $this->getAdapter()->fetchRow($this->select()
            ->from($this->_name, ['value'])
            ->where("`config`='$configName'"));
    }
}
