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
            $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
            $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['logo_image']['name'], PATHINFO_EXTENSION));
            $imageName = "logo_example." . $extension;

            if (in_array($extension, $allowedExtensions)) {
                if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo')) {
                    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo');
                }
                if (move_uploaded_file($_FILES["logo_image"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $imageName)) {
                    $resizeObj = new Pt_Commons_ImageResize(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $imageName);
                    $resizeObj->resizeImage(300, 300, 'auto');
                    $resizeObj->saveImage(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $imageName, 100);
                }
                $this->update(array('value' => $imageName), "name='logo'");
            }
        }
        if (isset($params['reportLayout']) && !empty($params['reportLayout']) && $params['reportLayout'] != 'default') {
            $this->update(array('value' => $params['reportLayout']), "name='report-layout'");
        }

        if (isset($params['instituteAddressPosition'])) {
            $this->update(array('value' => $params['instituteAddressPosition']), "name='institute-address-postition'");
        }
        if (isset($params['templateTopMargin'])) {
            $this->update(array('value' => $params['templateTopMargin']), "name='template-top-margin'");
        }
        // if(isset($_FILES['logo_image_right']) && !file_exists($_FILES['logo_image_right']['tmp_name']) || !is_uploaded_file($_FILES['logo_image_right']['tmp_name'])){


        // }else{
        //     $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
        //     $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['logo_image_right']['name'], PATHINFO_EXTENSION));
        //     $imageName ="logo_right.".$extension;

        //     if (in_array($extension, $allowedExtensions)) {
        //         if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo')) {
        //             mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo');
        //         }
        //         if(move_uploaded_file($_FILES["logo_image_right"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR."logo". DIRECTORY_SEPARATOR.$imageName)){
        //             $resizeObj = new Pt_Commons_ImageResize(UPLOAD_PATH . DIRECTORY_SEPARATOR."logo". DIRECTORY_SEPARATOR . $imageName);
        //             $resizeObj->resizeImage(300, 300, 'auto');
        //             $resizeObj->saveImage(UPLOAD_PATH . DIRECTORY_SEPARATOR."logo". DIRECTORY_SEPARATOR . $imageName, 100);
        //         }
        //         $this->update(array('value'=>$imageName),"name='logo-right'");
        //     }

        // }

        //$imageName ="logo_example.jpg";
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $common = new Application_Service_Common();

        $pdfFormatAllowedExtensions = ['pdf'];
        $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['reportTemplate']['name']);
        $fileName = str_replace(" ", "-", $fileName);
        $random = $common->generateRandomString(6);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileName = $random . "-" . $fileName;
        $response = [];
        $lastInsertedId = 0;
        mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'report-formats', 0777, true);
        if (isset($_FILES['reportTemplate']['name']) && !empty($_FILES['reportTemplate']['name'])) {
            if (in_array($extension, $pdfFormatAllowedExtensions)) {
                if (move_uploaded_file($_FILES['reportTemplate']['tmp_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR . 'report-formats' . DIRECTORY_SEPARATOR . $fileName)) {
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
