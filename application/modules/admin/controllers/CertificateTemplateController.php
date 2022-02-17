<?php

class Admin_CertificateTemplateController extends Zend_Controller_Action
{

    public function init()
    {

        $adminSession = new Zend_Session_Namespace('administrators');
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'manageMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $service = new Application_Service_CertificateTemplate();
            $service->getAllCertificateTemplateInGrid($params);
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        $systemAdmin = new Application_Service_SystemAdmin();
        $this->view->systemAdmin = $systemAdmin->getSystemAllAdmin();
    }

    public function addAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $service = new Application_Service_CertificateTemplate();
            $service->saveCertificateTemplate($params);
            $this->redirect("/admin/certificate-template");
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }
}
