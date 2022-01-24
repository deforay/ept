<?php

class Application_Model_DbTable_GlobalConfig extends Zend_Db_Table_Abstract
{

    protected $_name = 'global_config';
    protected $_primary = 'name';

    public function getValue($name)
    {
        $res = $this->getAdapter()->fetchCol($this->select()
            ->from($this->_name, array('value'))
            ->where("name='" . $name . "'"));
        return !empty($res[0]) ? $res[0] : null;
    }

    public function getGlobalConfig()
    {
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

    public function updateConfigDetails($params)
    {
        // Zend_Debug::dump($params);die;
        foreach ($params as $fieldName => $fieldValue) {
            if ($fieldName == 'schemeId') {
                $schemeDb = new Application_Model_DbTable_SchemeList();
                $schemeDb->update(array('status' => 'inactive'), "status='active'");
                foreach ($params["schemeId"] as $schemeId) {
                    $schemeDb->update(array('status' => 'active'), "scheme_id='" . $schemeId . "'");
                }
            } else {
                $this->update(array('value' => $fieldValue), "name='" . $fieldName . "'");
            }
        }
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("User " . $authNameSpace->primary_email . " updated a global-config ", "config");
    }

    public function getPTProgramName()
    {
        return $this->fetchRow('name = "pt_program_name"');
    }
    
    public function getPTProgramShortName()
    {
        return $this->fetchRow('name = "pt_program_short_name"');
    }
}
