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
              if($fieldName=='schemeId'){
                  $schemeDb = new Application_Model_DbTable_SchemeList();
                  $schemeDb->update(array('status' =>'inactive'),"status='active'");
                  foreach($params["schemeId"] as $schemeId){
                       $schemeDb->update(array('status' => 'active'),"scheme_id='" . $schemeId . "'");
                  }
              }else{
                 $this->update(array('value' => $fieldValue),"name='" . $fieldName . "'");
              }
        }
    }
    
    public function getTextUnderLogoContent(){
        return $this->fetchRow('name = "text_under_logo"');
    }

}

