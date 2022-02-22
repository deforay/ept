<?php

class Application_Service_CertificateTemplate
{

    public function getAllCertificateTemplates()
    {
        $certificateDb = new Application_Model_DbTable_CertificateTemplate();
        return $certificateDb->fetchAllCertificateTemplates();
    }

    public function saveCertificateTemplate($params)
    {
        $certificateDb = new Application_Model_DbTable_CertificateTemplate();
        return $certificateDb->saveCertificateTemplateDetails($params);
    }
}
