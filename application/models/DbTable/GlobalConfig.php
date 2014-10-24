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
    public function getGlobalConfig() {
        $configValues = $this->fetchAll()->toArray();
        
        $size = sizeof($configValues);
        $arr = array();
        // now we create an associative array so that we can easily create view variables
        for ($i = 0; $i < $size; $i++) {
            $arr[$configValues[$i]["name"]] = $configValues[$i]["value"];
        }
        // using assign to automatically create view variables
        // the column names will now become view variables
        return $arr;
    }
    public function updateConfigDetails($params) {
        foreach ($params as $fieldName => $fieldValue) {
            $this->update(array('value' => $fieldValue),"name='" . $fieldName . "'");
        }
    }

}

