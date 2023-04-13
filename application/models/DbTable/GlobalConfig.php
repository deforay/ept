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
        $arr = [];
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
        $common = new Application_Service_Common();
        $logosDir = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logos';
        if (!is_dir($logosDir)) {
            mkdir($logosDir, 0777, true);
        }
        if (isset($params['delete_home_left_logo']) && !empty($params['delete_home_left_logo'])) {
            unlink($logosDir . DIRECTORY_SEPARATOR . $params['delete_home_left_logo']);
            $this->update(array("value" => NULL), "name = 'home_left_logo'");
        }
        if (isset($params['delete_home_right_logo']) && !empty($params['delete_home_right_logo'])) {
            unlink($logosDir . DIRECTORY_SEPARATOR . $params['delete_home_right_logo']);
            $this->update(array("value" => NULL), "name = 'home_right_logo'");
        }
        foreach (array("home_left_logo", "home_right_logo") as $field) {
            if (isset($_FILES[$field]) && !empty($_FILES[$field]['name'])) {
                $pathPrefix = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logos';
                $extension = strtolower(pathinfo($pathPrefix . DIRECTORY_SEPARATOR . $_FILES[$field]['name'], PATHINFO_EXTENSION));
                $fileName =   $common->generateRandomString(4) . '.' . $extension;
                if (move_uploaded_file($_FILES[$field]["tmp_name"], $pathPrefix . DIRECTORY_SEPARATOR . $fileName)) {
                    $this->update(array("value" => $fileName), "name = '" . $field . "'");
                }
            }
        }
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
        $auditDb->addNewAuditLog("Updated global config ", "config");
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
