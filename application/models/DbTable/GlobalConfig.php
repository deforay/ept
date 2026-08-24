<?php

/**
 * Gateway for global_config — every application setting. Since 7.6.14 the
 * report_config table is merged in here and `context` names the admin page that
 * owns a row: 'global' for /admin/global-config, 'report' for /admin/report-config.
 * Names are unique across contexts, so context scopes writes and the admin pages
 * rather than forming part of a row's identity.
 */
class Application_Model_DbTable_GlobalConfig extends Zend_Db_Table_Abstract
{
    protected $_name = 'global_config';
    protected $_primary = 'name';

    public const CONTEXT_GLOBAL = 'global';
    public const CONTEXT_REPORT = 'report';

    /**
     * Per-process memo. Settings are read far more often than the query count
     * suggests — report layouts re-read the same key once per participant, so a
     * 6000-participant run repeated one identical query 6000 times. Config only
     * changes through the admin pages, which redirect afterwards, and a batch job
     * wants a fixed view of config for the length of its run either way.
     *
     * Every write in this class must call clearCache().
     */
    private static $valueCache = [];
    private static $allCache = null;

    public static function clearCache()
    {
        self::$valueCache = [];
        self::$allCache = null;
    }

    /**
     * $context ('global' | 'report') is optional: names are unique across contexts,
     * so a bare name resolves on its own. Pass it to scope the lookup to one
     * admin page's settings.
     */
    public function getValue($name, $context = null)
    {
        $cacheKey = $name . '|' . ($context ?? '*');
        if (array_key_exists($cacheKey, self::$valueCache)) {
            return self::$valueCache[$cacheKey];
        }

        $select = $this->select()
            ->from($this->_name, ['value'])
            ->where('name = ?', $name);
        if ($context !== null) {
            $select->where('context = ?', $context);
        }
        $res = $this->getAdapter()->fetchCol($select);

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

        self::$valueCache[$cacheKey] = $value;
        return $value;
    }

    public function getGlobalConfig(?string $configName = null)
    {
        if ($configName !== null) {
            $row = $this->fetchRow(['name = ?' => $configName]);
            return $row ? $row->value : null;
        }

        if (self::$allCache !== null) {
            return self::$allCache;
        }

        // Scoped to the global context: this runs on every page render, and since
        // 7.6.14 the table also holds report-config rows (report-header is a
        // mediumtext HTML blob) that no caller here has ever seen.
        $configValues = $this->fetchAll(['context = ?' => self::CONTEXT_GLOBAL])->toArray();

        $arr = [];
        foreach ($configValues as $config) {
            $arr[$config['name']] = $config['value'];
        }

        self::$allCache = $arr;
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

        // Covers the direct update()s above (logos) alongside saveConfigByName().
        self::clearCache();

        $detail = empty($changedSections) ? '' : ' — ' . implode(', ', array_unique($changedSections));
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog('Updated global config' . $detail, 'config');
    }

    /** WHERE fragment pinning a statement to a single report-context row. */
    private function reportRow(string $name): string
    {
        $db = $this->getAdapter();
        return $db->quoteInto('name = ?', $name) . ' AND ' . $db->quoteInto('context = ?', self::CONTEXT_REPORT);
    }

    /** Saves the /admin/report-config form, the report-context counterpart of updateConfigDetails(). */
    public function updateReportDetails($params)
    {
        $data = ['value' => $params['content']];
        $changedSections = ['report header'];

        if (isset($_FILES['logo_image']['tmp_name']) && file_exists($_FILES['logo_image']['tmp_name']) && is_uploaded_file($_FILES['logo_image']['tmp_name'])) {

            $uploadDirectory = realpath(UPLOAD_PATH);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            $fileNameSanitized = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['logo_image']['name']);
            $fileNameSanitized = str_replace(' ', '-', $fileNameSanitized);
            $extension = strtolower(pathinfo($uploadDirectory . DIRECTORY_SEPARATOR . $fileNameSanitized, PATHINFO_EXTENSION));
            $imageName = 'logo_example.' . $extension;
            if (in_array($extension, $allowedExtensions)) {
                if (!file_exists($uploadDirectory . DIRECTORY_SEPARATOR . 'logo') && !is_dir($uploadDirectory . DIRECTORY_SEPARATOR . 'logo')) {
                    mkdir($uploadDirectory . DIRECTORY_SEPARATOR . 'logo');
                }
                if (move_uploaded_file($_FILES['logo_image']['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $imageName)) {
                    $resizeObj = new Pt_Commons_ImageResize($uploadDirectory . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $imageName);
                    $resizeObj->resizeImage(300, 300, 'auto');
                    $resizeObj->saveImage($uploadDirectory . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $imageName, 100);
                }
                $this->update(['value' => $imageName], $this->reportRow('logo'));
                $changedSections[] = 'logo';
            }
        }
        if (isset($params['reportLayout']) && !empty($params['reportLayout'])) {
            $this->update(['value' => $params['reportLayout']], $this->reportRow('report-layout'));
            $changedSections[] = 'layout';
        }

        if (isset($params['instituteAddressPosition'])) {
            $this->update(['value' => $params['instituteAddressPosition']], $this->reportRow('institute-address-postition'));
            $changedSections[] = 'institute address position';
        }
        if (isset($params['templateTopMargin'])) {
            $this->update(['value' => $params['templateTopMargin']], $this->reportRow('template-top-margin'));
            $changedSections[] = 'top margin';
        }
        if (isset($params['generate_reports_for_excluded'])) {
            $value = $params['generate_reports_for_excluded'] === 'yes' ? 'yes' : 'no';
            $this->update(['value' => $value], $this->reportRow('generate_reports_for_excluded'));
            $changedSections[] = 'reports for excluded submissions';
        }

        if (isset($params['reportApproverName'])) {
            $this->update(['value' => trim((string) $params['reportApproverName'])], $this->reportRow('report-approver-name'));
            $changedSections[] = 'default approver name';
        }

        //$imageName ="logo_example.jpg";
        $alertMsg = new Zend_Session_Namespace('alertSpace');

        $pdfFormatAllowedExtensions = ['pdf'];
        $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['reportTemplate']['name']);
        $fileName = str_replace(' ', '-', $fileName);
        $random = Pt_Commons_MiscUtility::generateRandomString(6);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileName = $random . '-' . $fileName;
        $uploadDirectory = realpath(UPLOAD_PATH);
        mkdir($uploadDirectory . DIRECTORY_SEPARATOR . 'report-formats', 0777, true);
        if (isset($params['deleteTemplate']) && !empty($params['deleteTemplate']) && $params['deleteTemplate'] == 'yes') {
            $this->update(['value' => null], $this->reportRow('report-format'));
            $changedSections[] = 'PDF template removed';
        }
        if (isset($_FILES['reportTemplate']['name']) && !empty($_FILES['reportTemplate']['name'])) {
            if (in_array($extension, $pdfFormatAllowedExtensions)) {
                if (move_uploaded_file($_FILES['reportTemplate']['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . 'report-formats' . DIRECTORY_SEPARATOR . $fileName)) {
                    $this->update(['value' => $fileName], $this->reportRow('report-format'));
                    $changedSections[] = 'PDF template';
                }
            } else {
                $alertMsg->message = 'Unable to upload file. Please upload only PDF files';
                return false;
            }
        }

        $alertMsg->message = 'PDF Config Updated';

        $detail = ' — ' . implode(', ', array_unique($changedSections));
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog('Updated report config' . $detail, 'config');
        $result = $this->update($data, $this->reportRow('report-header'));

        self::clearCache();
        return $result;
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
        self::clearCache();
        $row = $this->fetchRow(['name = ?' => $name]);
        if ($row) {
            return $this->update(['value' => $value], ['name = ?' => $name]);
        }
        return $this->insert(['name' => $name, 'value' => $value]);
    }
}
