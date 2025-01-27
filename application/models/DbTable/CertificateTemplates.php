<?php

class Application_Model_DbTable_CertificateTemplates extends Zend_Db_Table_Abstract
{
    protected $_name = 'certificate_templates';
    protected $_primary = 'ct_id';

    public function saveCertificateTemplateDetails($params)
    {
        try {
            $authNameSpace = new Zend_Session_Namespace('administrators');
            if (isset($params['scheme']) && sizeof($params['scheme']) > 0) {
                foreach ($params['scheme'] as $key => $scheme) {
                    $id = base64_decode($params['ctId'][$key]);
                    if (isset($id) && $id > 0) {
                        $this->update(array(
                            "updated_on"                => new Zend_Db_Expr('now()')
                        ), array("ct_id" => $id));
                    } else {
                        $id = $this->insert(array(
                            "scheme_type"               => $scheme,
                            "created_by"                => $authNameSpace->admin_id,
                            "updated_on"                => new Zend_Db_Expr('now()')
                        ));
                    }
                    $appDirectory = realpath(APPLICATION_PATH);
                    if (!file_exists($appDirectory . '/../scheduled-jobs')) {
                        mkdir($appDirectory . '/../scheduled-jobs', 0777, true);
                    }
                    if (!file_exists($appDirectory . '/../scheduled-jobs' . DIRECTORY_SEPARATOR . 'certificate-templates')) {
                        mkdir($appDirectory . '/../scheduled-jobs' . DIRECTORY_SEPARATOR . 'certificate-templates', 0777, true);
                    }

                    if (!empty($_FILES['pCertificate']['name'][$key])) {
                        $fileNameSanitized = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['pCertificate']['name'][$key]);
                        $fileNameSanitized = str_replace(" ", "-", $fileNameSanitized);
                        $pathPrefix = SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'certificate-templates';
                        $extension = strtolower(pathinfo($pathPrefix . DIRECTORY_SEPARATOR . $fileNameSanitized, PATHINFO_EXTENSION));
                        $fileName = $scheme . "-p." . $extension;
                        if (move_uploaded_file($_FILES["pCertificate"]["tmp_name"][$key], $pathPrefix . DIRECTORY_SEPARATOR . $fileName)) {
                            $this->update(array("participation_certificate" => $fileName), "ct_id = " . $id);
                        }
                    }
                    if (!empty($_FILES['eCertificate']['name'][$key])) {
                        $fileNameSanitized = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['eCertificate']['name'][$key]);
                        $fileNameSanitized = str_replace(" ", "-", $fileNameSanitized);
                        $pathPrefix = SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'certificate-templates';
                        $extension = strtolower(pathinfo($pathPrefix . DIRECTORY_SEPARATOR . $fileNameSanitized, PATHINFO_EXTENSION));
                        $fileName = $scheme . "-e." . $extension;
                        if (move_uploaded_file($_FILES["eCertificate"]["tmp_name"][$key], $pathPrefix . DIRECTORY_SEPARATOR . $fileName)) {
                            $this->update(array("excellence_certificate" => $fileName), "ct_id = " . $id);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
        }
    }

    public function fetchAllCertificateTemplates()
    {
        $certificateTemplate = [];
        $resulkt = $this->fetchAll();
        foreach ($resulkt as $ct) {
            $certificateTemplate[$ct['scheme_type']] = array(
                "ct_id"                     => $ct["ct_id"],
                "scheme_type"               => $ct["scheme_type"],
                "participation_certificate" => $ct["participation_certificate"],
                "excellence_certificate"    => $ct["excellence_certificate"]
            );
        }
        return $certificateTemplate;
    }
}
