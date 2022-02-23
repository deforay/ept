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
                            "scheme_type"               => $scheme,
                            "created_by"                => $authNameSpace->primary_email,
                            "updated_on"                => new Zend_Db_Expr('now()')
                        ), array("ct_id" => $id));
                    } else {
                        $id = $this->insert(array(
                            "scheme_type"               => $scheme,
                            "created_by"                => $authNameSpace->primary_email,
                            "updated_on"                => new Zend_Db_Expr('now()')
                        ));
                    }
                    if (isset($_FILES['pCertificate']['name'][$key]) && $_FILES['pCertificate']['name'][$key] != "") {
                        // Define the path
                        $pathPrefix = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'scheduled-jobs' . DIRECTORY_SEPARATOR . 'certificate-templates';
                        $extension = strtolower(pathinfo($pathPrefix . DIRECTORY_SEPARATOR . $_FILES["pCertificate"]['name'][$key], PATHINFO_EXTENSION));
                        $fileName = $scheme . "-p." . $extension;
                        if (move_uploaded_file($_FILES["pCertificate"]["tmp_name"][$key], $pathPrefix . DIRECTORY_SEPARATOR . $fileName)) {
                            $this->update(array("participation_certificate" => $fileName), "ct_id = " . $id);
                        }
                        move_uploaded_file($_FILES["pCertificate"]["tmp_name"][$key], UPLOAD_PATH . DIRECTORY_SEPARATOR . "certificate-template" . DIRECTORY_SEPARATOR . $fileName);
                    }
                    if (isset($_FILES['eCertificate']['name'][$key]) && $_FILES['eCertificate']['name'][$key] != "") {
                        // Define the path
                        $pathPrefix = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'scheduled-jobs' . DIRECTORY_SEPARATOR . 'certificate-templates';
                        $extension = strtolower(pathinfo($pathPrefix . DIRECTORY_SEPARATOR . $_FILES["eCertificate"]['name'][$key], PATHINFO_EXTENSION));
                        $fileName = $scheme . "-e." . $extension;
                        if (move_uploaded_file($_FILES["eCertificate"]["tmp_name"][$key], $pathPrefix . DIRECTORY_SEPARATOR . $fileName)) {
                            $this->update(array("excellence_certificate" => $fileName), "ct_id = " . $id);
                        }
                        move_uploaded_file($_FILES["eCertificate"]["tmp_name"][$key], UPLOAD_PATH . DIRECTORY_SEPARATOR . "certificate-template" . DIRECTORY_SEPARATOR . $fileName);
                    }
                }
            }
        } catch (Exception $e) {
            echo 'Message: ' . $e->getMessage();
        }
    }

    public function fetchAllCertificateTemplates()
    {
        $certificateTemplate = array();
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
