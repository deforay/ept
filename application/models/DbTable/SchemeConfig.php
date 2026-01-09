<?php

class Application_Model_DbTable_SchemeConfig extends Zend_Db_Table_Abstract
{

    protected $_name = 'scheme_config';

    public function getSchemeConfig(?string $name = null)
    {
        $result = null;
        // Check if we're requesting a nested JSON value
        if (str_contains($name, '.')) {
            [$configName, $jsonKey] = explode('.', $name, 2);
            $jsonExpr = $this->getAdapter()->quoteInto(
                "JSON_UNQUOTE(JSON_EXTRACT(scheme_config_value, CONCAT('$.', JSON_QUOTE(?))))",
                $jsonKey
            );
            $select = $this->select()
                ->from($this->_name, array(
                    'value' => new Zend_Db_Expr($jsonExpr)
                ))
                ->where("scheme_config_name = ?", $configName);
            $res = $this->getAdapter()->fetchCol($select);
            $result = !empty($res[0]) ? $res[0] : null;
        } else {
            // Original behavior for non-nested values
            $res = $this->getAdapter()->fetchCol($this->select()
                ->from($this->_name, array('scheme_config_value'))
                ->where("scheme_config_name = ?", $name));
            $result = !empty($res[0]) ? $res[0] : null;
        }

        return $result;
    }


    public function updateConfigDetails($params)
    {
        if (isset($params['emailConfig']) && !empty($params['emailConfig'])) {
            $this->insertOrUpdate('mail', json_encode($params['emailConfig'], true));
        }
        if (isset($params['covid19']) && !empty($params['covid19'])) {
            $this->insertOrUpdate('covid19', json_encode($params['covid19'], true));
        }
        if (isset($params['vl']) && !empty($params['vl'])) {
            $this->insertOrUpdate('vl', json_encode($params['vl'], true));
        }
        if (isset($params['recency']) && !empty($params['recency'])) {
            $this->insertOrUpdate('recency', json_encode($params['recency'], true));
        }
        if (isset($params['tb']) && !empty($params['tb'])) {
            $this->insertOrUpdate('tb', json_encode($params['tb'], true));
        }
        if (isset($params['dts']) && !empty($params['dts'])) {
            $this->insertOrUpdate('dts', json_encode($params['dts'], true));
        }
        if (isset($params['home']) && !empty($params['home'])) {
            $this->insertOrUpdate('home', json_encode($params['home'], true));
        }
        if (isset($params['faqQuestions']) && !empty($params['faqQuestions'])) {
            $faqResponse = [];
            foreach ($params['faqQuestions'] as $key => $faq) {
                $faqResponse[$faq] = $params['faqAnswers'][$key];
            }
            $this->insertOrUpdate('faqs', json_encode($faqResponse, true));
        }

        /* foreach ($params as $fieldName => $fieldValue) {
            $this->insertOrUpdate($fieldName, $fieldValue);
        } */

        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("Updated scheme config", "config");
    }

    public function saveConfigByName($value, $name)
    {
        return $this->insertOrUpdate($name, $value);
    }

    protected function insertOrUpdate($configName, $configValue)
    {
        // Check if config exists
        $row = $this->fetchRow(
            $this->select()->where('scheme_config_name = ?', $configName)
        );

        if ($row) {
            // Update existing
            $this->update(
                ['scheme_config_value' => $configValue],
                $this->getAdapter()->quoteInto('scheme_config_name = ?', $configName)
            );
        } else {
            // Insert new
            $this->insert(array(
                'scheme_config_name' => $configName,
                'scheme_config_value' => $configValue
            ));
        }
    }
}
