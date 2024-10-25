<?php

class Application_Model_DbTable_SystemMetaData extends Zend_Db_Table_Abstract
{

    protected $_name = 'system_metadata';
    protected $_primary = 'metadata_id';

    public function getValue($params)
    {
        return $this->getAdapter()->fetchRow($this->select()
            ->from($this->_name, array('metadata_value'))
            ->where(" metadata_id = '" . $params . "'"));
    }
}
