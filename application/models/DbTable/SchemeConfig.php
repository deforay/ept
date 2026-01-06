<?php

class Application_Model_DbTable_SchemeConfig extends Zend_Db_Table_Abstract
{

    protected $_name = 'scheme_config';
    protected $_primary = 'scheme_config_name';

    public function getValue($name)
    {
        // Check if we're requesting a nested JSON value
        if (strpos($name, '.') !== false) {
            list($configName, $jsonKey) = explode('.', $name, 2);

            $select = $this->select()
                ->from($this->_name, array(
                    'value' => new Zend_Db_Expr("JSON_UNQUOTE(JSON_EXTRACT(scheme_config_value, '$.$jsonKey'))")
                ))
                ->where("scheme_config_name = ?", $configName);

            $res = $this->getAdapter()->fetchCol($select);

            return !empty($res[0]) ? $res[0] : null;
        }

        // Original behavior for non-nested values
        $res = $this->getAdapter()->fetchCol($this->select()
            ->from($this->_name, array('scheme_config_value'))
            ->where("scheme_config_name = ?", $name));

        return !empty($res[0]) ? $res[0] : null;
    }

    public function getSchemeConfig(?string $configName = null)
    {
        if ($configName !== null) {
            $row = $this->fetchRow(['scheme_config_name = ?' => $configName]);
            return $row ? $row->value : null;
        }

        $configValues = $this->fetchAll()->toArray();

        $arr = [];
        foreach ($configValues as $config) {
            $arr[$config['scheme_config_name']] = $config['scheme_config_value'];
        }

        return $arr;
    }


    public function updateConfigDetails($params)
    {
        if (isset($params['emailConfig']) && !empty($params['emailConfig'])) {
            $this->update(array("scheme_config_value" => json_encode($params['emailConfig'], true)), "scheme_config_name = 'mail'");
            unset($params['emailConfig']);
        }
        if (isset($params['covid19']) && !empty($params['covid19'])) {
            $this->update(array("scheme_config_value" => json_encode($params['covid19'], true)), "scheme_config_name = 'covid19'");
            unset($params['covid19']);
        }
        if (isset($params['vl']) && !empty($params['vl'])) {
            $this->update(array("scheme_config_value" => json_encode($params['vl'], true)), "scheme_config_name = 'vl'");
            unset($params['vl']);
        }
        if (isset($params['recency']) && !empty($params['recency'])) {
            $this->update(array("scheme_config_value" => json_encode($params['recency'], true)), "scheme_config_name = 'recency'");
            unset($params['recency']);
        }
        if (isset($params['tb']) && !empty($params['tb'])) {
            $this->update(array("scheme_config_value" => json_encode($params['tb'], true)), "scheme_config_name = 'tb'");
            unset($params['tb']);
        }
        if (isset($params['home']) && !empty($params['home'])) {
            $this->update(array("scheme_config_value" => json_encode($params['home'], true)), "scheme_config_name = 'home'");
            unset($params['home']);
        }
        if (isset($params['faqQuestions']) && !empty($params['faqQuestions'])) {
            $faqResponse = [];
            foreach ($params['faqQuestions'] as $key => $faq) {
                $faqResponse[$faq] = $params['faqAnswers'][$key];
            }
            $this->update(array("scheme_config_value" => json_encode($faqResponse, true)), "scheme_config_name = 'faqs'");
            unset($params['faqQuestions']);
            unset($params['faqAnswers']);
        }

        foreach ($params as $fieldName => $fieldValue) {
            $this->update(array('scheme_config_value' => $fieldValue), "scheme_config_name='" . $fieldName . "'");
        }
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("Updated scheme config ", "config");
    }

    public function saveConfigByName($value, $name)
    {
        return $this->update(array("scheme_config_value" => $value), "scheme_config_name = '" . $name . "'");
    }
}
