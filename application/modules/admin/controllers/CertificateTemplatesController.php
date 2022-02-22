<?php

class Admin_CertificateTemplatesController extends Zend_Controller_Action
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
        $service = new Application_Service_CertificateTemplates();
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $service->saveCertificateTemplate($params);
            $this->redirect("/admin/certificate-templates");
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        $this->view->certificateTemplates = $service->getAllCertificateTemplates();
    }
}
