<?php

class Application_Model_DbTable_ReportConfig extends Zend_Db_Table_Abstract
{

    protected $_name = 'report_config';
    protected $_primary = 'name';

    public function updateReportDetails($params)
    {
        // Zend_Debug::dump($_FILES);die;
        $data = array('value' => $params['content']);

        if (isset($_FILES['logo_image']['tmp_name']) && file_exists($_FILES['logo_image']['tmp_name']) && is_uploaded_file($_FILES['logo_image']['tmp_name'])) {

            $uploadDirectory = realpath(UPLOAD_PATH);
            $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
            $fileNameSanitized = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['logo_image']['name']);
            $fileNameSanitized = str_replace(" ", "-", $fileNameSanitized);
            $extension = strtolower(pathinfo($uploadDirectory . DIRECTORY_SEPARATOR . $fileNameSanitized, PATHINFO_EXTENSION));
            $imageName = "logo_example." . $extension;
            if (in_array($extension, $allowedExtensions)) {
                if (!file_exists($uploadDirectory . DIRECTORY_SEPARATOR . 'logo') && !is_dir($uploadDirectory . DIRECTORY_SEPARATOR . 'logo')) {
                    mkdir($uploadDirectory . DIRECTORY_SEPARATOR . 'logo');
                }
                if (move_uploaded_file($_FILES["logo_image"]["tmp_name"], $uploadDirectory . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $imageName)) {
                    $resizeObj = new Pt_Commons_ImageResize($uploadDirectory . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $imageName);
                    $resizeObj->resizeImage(300, 300, 'auto');
                    $resizeObj->saveImage($uploadDirectory . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $imageName, 100);
                }
                $this->update(['value' => $imageName], "name='logo'");
            }
        }
        if (isset($params['reportLayout']) && !empty($params['reportLayout'])) {
            $this->update(['value' => $params['reportLayout']], "name='report-layout'");
        }

        if (isset($params['instituteAddressPosition'])) {
            $this->update(['value' => $params['instituteAddressPosition']], "name='institute-address-postition'");
        }
        if (isset($params['templateTopMargin'])) {
            $this->update(['value' => $params['templateTopMargin']], "name='template-top-margin'");
        }

        //$imageName ="logo_example.jpg";
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $common = new Application_Service_Common();

        $pdfFormatAllowedExtensions = ['pdf'];
        $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['reportTemplate']['name']);
        $fileName = str_replace(" ", "-", $fileName);
        $random = Pt_Commons_MiscUtility::generateRandomString(6);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileName = $random . "-" . $fileName;
        $uploadDirectory = realpath(UPLOAD_PATH);
        mkdir($uploadDirectory . DIRECTORY_SEPARATOR . 'report-formats', 0777, true);
        if (isset($params['deleteTemplate']) && !empty($params['deleteTemplate']) && $params['deleteTemplate'] == 'yes') {
            $this->update(array('value' => null), "name='report-format'");
        }
        if (isset($_FILES['reportTemplate']['name']) && !empty($_FILES['reportTemplate']['name'])) {
            if (in_array($extension, $pdfFormatAllowedExtensions)) {
                if (move_uploaded_file($_FILES['reportTemplate']['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . 'report-formats' . DIRECTORY_SEPARATOR . $fileName)) {
                    $this->update(array('value' => $fileName), "name='report-format'");
                }
            } else {
                $alertMsg->message = 'Unable to upload file. Please upload only PDF files';
                return false;
            }
        }

        $alertMsg->message = 'PDF Config Updated';

        $authNameSpace = new Zend_Session_Namespace('administrators');
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("Updated Report Config ", "config");
        return $this->update($data, "name='report-header'");
    }

    public function getValue($name)
    {
        $res = $this->getAdapter()->fetchRow($this->select()
            ->from($this->_name, array('value'))
            ->where("name='" . $name . "'"));
        return $res["value"] ?? null;
    }
}
