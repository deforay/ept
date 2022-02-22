<?php

class Application_Service_CertificateTemplates
{

    public function getAllCertificateTemplates()
    {
        $certificateDb = new Application_Model_DbTable_CertificateTemplates();
        return $certificateDb->fetchAllCertificateTemplates();
    }

    public function saveCertificateTemplate($params)
    {
        $certificateDb = new Application_Model_DbTable_CertificateTemplates();
        return $certificateDb->saveCertificateTemplateDetails($params);
    }
}
