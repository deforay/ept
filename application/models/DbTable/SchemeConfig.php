<?php

class Application_Model_DbTable_SchemeConfig extends Zend_Db_Table_Abstract
{

    protected $_name = 'scheme_config';

    public function getValue($name)
    {
        $result = null;

        // Check if we're requesting a nested JSON value
        if (strpos($name, '.') !== false) {
            list($configName, $jsonKey) = explode('.', $name, 2);
            $select = $this->select()
                ->from($this->_name, array(
                    'value' => new Zend_Db_Expr("JSON_UNQUOTE(JSON_EXTRACT(scheme_config_value, '$.$jsonKey'))")
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

        // If no result from database, check config.ini
        if ($result === null) {
            try {
                $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
                if (file_exists($file)) {
                    $config = new Zend_Config_Ini($file, APPLICATION_ENV);

                    // Handle nested config values (e.g., "evaluation.dts.passPercentage")
                    if (strpos($name, '.') !== false) {
                        $keys = explode('.', $name);
                        $value = $config;
                        foreach ($keys as $key) {
                            if (isset($value->$key)) {
                                $value = $value->$key;
                            } else {
                                $value = null;
                                break;
                            }
                        }
                        $result = $value;
                    } else {
                        // Direct config key
                        $result = isset($config->$name) ? $config->$name : null;
                    }
                }
            } catch (Exception $e) {
                // Log error if needed
                error_log("Error reading config.ini: " . $e->getMessage());
            }
        }

        return $result;
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
                array('scheme_config_value' => $configValue),
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
