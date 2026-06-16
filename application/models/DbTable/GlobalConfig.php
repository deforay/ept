<?php

class Application_Model_DbTable_GlobalConfig extends Zend_Db_Table_Abstract
{
    protected $_name = 'global_config';
    protected $_primary = 'name';

    public function getValue($name)
    {
        $res = $this->getAdapter()->fetchCol($this->select()
            ->from($this->_name, ['value'])
            ->where('name = ?', $name));

        $value = !empty($res[0]) ? $res[0] : null;

        // If value is null, check in global config or scheme config
        if ($value === null) {
            try {
                $value = Pt_Commons_SchemeConfig::get($name);
            } catch (\Throwable $e) {
                // Log error or handle exception as needed
                $value = null;
            }
        }

        return $value;
    }

    public function getGlobalConfig(?string $configName = null)
    {
        if ($configName !== null) {
            $row = $this->fetchRow(['name = ?' => $configName]);
            return $row ? $row->value : null;
        }

        $configValues = $this->fetchAll()->toArray();

        $arr = [];
        foreach ($configValues as $config) {
            $arr[$config['name']] = $config['value'];
        }

        return $arr;
    }

    public function updateConfigDetails($params)
    {
        $changedSections = [];

        $logosDir = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logos';
        if (!is_dir($logosDir)) {
            mkdir($logosDir, 0777, true);
        }
        if (isset($params['delete_home_left_logo']) && !empty($params['delete_home_left_logo'])) {
            unlink($logosDir . DIRECTORY_SEPARATOR . $params['delete_home_left_logo']);
            $this->update(['value' => null], "name = 'home_left_logo'");
            $changedSections[] = 'home left logo';
        }
        if (isset($params['delete_home_right_logo']) && !empty($params['delete_home_right_logo'])) {
            unlink($logosDir . DIRECTORY_SEPARATOR . $params['delete_home_right_logo']);
            $this->update(['value' => null], "name = 'home_right_logo'");
            $changedSections[] = 'home right logo';
        }
        foreach (['home_left_logo', 'home_right_logo'] as $field) {
            if (isset($_FILES[$field]) && !empty($_FILES[$field]['name'])) {
                $fileNameSanitized = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES[$field]['name']);
                $fileNameSanitized = str_replace(' ', '-', $fileNameSanitized);
                $pathPrefix = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logos';
                $extension = strtolower(pathinfo($pathPrefix . DIRECTORY_SEPARATOR . $fileNameSanitized, PATHINFO_EXTENSION));
                $fileName = Pt_Commons_MiscUtility::generateRandomString(4) . '.' . $extension;
                if (move_uploaded_file($_FILES[$field]['tmp_name'], $pathPrefix . DIRECTORY_SEPARATOR . $fileName)) {
                    $this->saveConfigByName($fileName, $field);
                    $changedSections[] = str_replace('_', ' ', $field);
                }
            }
        }

        if (isset($params['emailConfig']) && !empty($params['emailConfig'])) {
            $this->saveConfigByName(json_encode($params['emailConfig'], true), 'mail');
            unset($params['emailConfig']);
            $changedSections[] = 'email config';
        }
        if (isset($params['covid19']) && !empty($params['covid19'])) {
            $this->saveConfigByName(json_encode($params['covid19'], true), 'covid19');
            unset($params['covid19']);
            $changedSections[] = 'COVID-19 config';
        }
        if (isset($params['vl']) && !empty($params['vl'])) {
            $this->saveConfigByName(json_encode($params['vl'], true), 'vl');
            unset($params['vl']);
            $changedSections[] = 'VL config';
        }
        if (isset($params['recency']) && !empty($params['recency'])) {
            $this->saveConfigByName(json_encode($params['recency'], true), 'recency');
            unset($params['recency']);
            $changedSections[] = 'Recency config';
        }
        if (isset($params['tb']) && !empty($params['tb'])) {
            $this->saveConfigByName(json_encode($params['tb'], true), 'tb');
            unset($params['tb']);
            $changedSections[] = 'TB config';
        }
        if (isset($params['home']) && !empty($params['home'])) {
            $this->saveConfigByName(json_encode($params['home'], true), 'home');
            unset($params['home']);
            $changedSections[] = 'home page';
        }
        if (isset($params['faqQuestions']) && !empty($params['faqQuestions'])) {
            $faqResponse = [];
            foreach ($params['faqQuestions'] as $key => $faq) {
                $faqResponse[$faq] = $params['faqAnswers'][$key];
            }
            $this->saveConfigByName(json_encode($faqResponse, true), 'faqs');
            unset($params['faqQuestions']);
            unset($params['faqAnswers']);
            $changedSections[] = 'FAQs';
        }

        // Request plumbing that leaks in from the POST form but is not a real
        // global_config row — never persist or audit-log these.
        $ignoreFields = ['module', 'controller', 'action', 'csrf_token', 'submit'];

        $individualFields = [];
        foreach ($params as $fieldName => $fieldValue) {
            if ($fieldName == 'schemeId') {
                $schemeDb = new Application_Model_DbTable_SchemeList();
                $schemeDb->update(['status' => 'inactive'], "status='active'");
                foreach ($params['schemeId'] as $schemeId) {
                    $schemeDb->update(['status' => 'active'], "scheme_id='" . $schemeId . "'");
                }
                $changedSections[] = 'active schemes';
                continue;
            }
            // (A) Drop form plumbing so it never reaches the config table or log.
            if (in_array($fieldName, $ignoreFields, true)) {
                continue;
            }
            // (B) Only persist + log a field when its value actually changed, so
            // a no-op Save no longer produces a wall of "changed" field names.
            $currentValue = $this->getAdapter()->fetchOne(
                $this->select()->from($this->_name, ['value'])->where('name = ?', $fieldName)
            );
            $newValue = is_array($fieldValue) ? json_encode($fieldValue) : (string) $fieldValue;
            if ((string) $currentValue === $newValue) {
                continue;
            }
            $this->saveConfigByName(is_array($fieldValue) ? json_encode($fieldValue) : $fieldValue, $fieldName);
            $individualFields[] = $fieldName;
        }
        if (!empty($individualFields)) {
            $changedSections[] = count($individualFields) . ' setting' . (count($individualFields) === 1 ? '' : 's') . ' (' . implode(', ', $individualFields) . ')';
        }

        $detail = empty($changedSections) ? '' : ' — ' . implode(', ', array_unique($changedSections));
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog('Updated global config' . $detail, 'config');
    }

    public function getPTProgramName()
    {
        return $this->fetchRow('name = "pt_program_name"');
    }

    public function getPTProgramShortName()
    {
        return $this->fetchRow('name = "pt_program_short_name"');
    }

    public function saveConfigByName($value, $name)
    {
        $row = $this->fetchRow(['name = ?' => $name]);
        if ($row) {
            return $this->update(['value' => $value], ['name = ?' => $name]);
        }
        return $this->insert(['name' => $name, 'value' => $value]);
    }
}
