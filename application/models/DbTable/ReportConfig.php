<?php

class Application_Model_DbTable_ReportConfig extends Zend_Db_Table_Abstract
{

    protected $_name = 'report_config';
    protected $_primary = 'name';
    
    public function updateReportDetails($params){
        $data = array('value'=>$params['content']);
        return $this->update($data,"name='report-header'");   
    }
    
    public function getValue($name){
        $res = $this->getAdapter()->fetchCol($this->select()
                               ->from($this->_name, array('value'))
                              ->where("name='".$name."'"));
        return $res[0];
    }
}

