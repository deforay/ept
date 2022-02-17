<?php

class Application_Service_CertificateTemplate
{

    public function getAllCertificateTemplateInGrid($parameters)
    {
        $certificateDb = new Application_Model_DbTable_CertificateTemplate();
        return $certificateDb->fetchAllCertificateTemplateInGrid($parameters);
    }

    public function saveCertificateTemplate($params)
    {
        $certificateDb = new Application_Model_DbTable_CertificateTemplate();
        return $certificateDb->saveCertificateTemplateDetails($params);
    }
}
