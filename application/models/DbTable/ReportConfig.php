<?php

/**
 * Report-config settings. Since 7.6.14 these live in global_config under
 * context='report' — the report_config table is gone — so every read and write
 * here is scoped to that context. The class stays as the report-side accessor
 * so its callers (and Reports::getReportConfigValue) are unaffected by the merge.
 */
class Application_Model_DbTable_ReportConfig extends Zend_Db_Table_Abstract
{
    protected $_name = 'global_config';
    protected $_primary = 'name';

    /** Rows this class owns; every statement is scoped to it. */
    private const CONTEXT = 'report';

    /** WHERE fragment pinning a statement to a single report-config row. */
    private function nameIs(string $name): string
    {
        $db = $this->getAdapter();
        return $db->quoteInto('name = ?', $name) . ' AND ' . $db->quoteInto('context = ?', self::CONTEXT);
    }

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
                $this->update(['value' => $imageName], $this->nameIs('logo'));
                $changedSections[] = 'logo';
            }
        }
        if (isset($params['reportLayout']) && !empty($params['reportLayout'])) {
            $this->update(['value' => $params['reportLayout']], $this->nameIs('report-layout'));
            $changedSections[] = 'layout';
        }

        if (isset($params['instituteAddressPosition'])) {
            $this->update(['value' => $params['instituteAddressPosition']], $this->nameIs('institute-address-postition'));
            $changedSections[] = 'institute address position';
        }
        if (isset($params['templateTopMargin'])) {
            $this->update(['value' => $params['templateTopMargin']], $this->nameIs('template-top-margin'));
            $changedSections[] = 'top margin';
        }
        if (isset($params['generate_reports_for_excluded'])) {
            $value = $params['generate_reports_for_excluded'] === 'yes' ? 'yes' : 'no';
            $this->update(['value' => $value], $this->nameIs('generate_reports_for_excluded'));
            $changedSections[] = 'reports for excluded submissions';
        }

        //$imageName ="logo_example.jpg";
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $common = new Application_Service_Common();

        $pdfFormatAllowedExtensions = ['pdf'];
        $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['reportTemplate']['name']);
        $fileName = str_replace(' ', '-', $fileName);
        $random = Pt_Commons_MiscUtility::generateRandomString(6);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileName = $random . '-' . $fileName;
        $uploadDirectory = realpath(UPLOAD_PATH);
        mkdir($uploadDirectory . DIRECTORY_SEPARATOR . 'report-formats', 0777, true);
        if (isset($params['deleteTemplate']) && !empty($params['deleteTemplate']) && $params['deleteTemplate'] == 'yes') {
            $this->update(['value' => null], $this->nameIs('report-format'));
            $changedSections[] = 'PDF template removed';
        }
        if (isset($_FILES['reportTemplate']['name']) && !empty($_FILES['reportTemplate']['name'])) {
            if (in_array($extension, $pdfFormatAllowedExtensions)) {
                if (move_uploaded_file($_FILES['reportTemplate']['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . 'report-formats' . DIRECTORY_SEPARATOR . $fileName)) {
                    $this->update(['value' => $fileName], $this->nameIs('report-format'));
                    $changedSections[] = 'PDF template';
                }
            } else {
                $alertMsg->message = 'Unable to upload file. Please upload only PDF files';
                return false;
            }
        }

        $alertMsg->message = 'PDF Config Updated';

        $authNameSpace = new Zend_Session_Namespace('administrators');
        $detail = ' — ' . implode(', ', array_unique($changedSections));
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog('Updated report config' . $detail, 'config');
        return $this->update($data, $this->nameIs('report-header'));
    }

    public function getValue($name)
    {
        $res = $this->getAdapter()->fetchRow($this->select()
            ->from($this->_name, ['value'])
            ->where('name = ?', $name)
            ->where('context = ?', self::CONTEXT));
        return $res['value'] ?? null;
    }
}
