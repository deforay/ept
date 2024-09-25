<?php

class Admin_CertificateTemplatesController extends Zend_Controller_Action
{

    public function init()
    {

        $adminSession = new Zend_Session_Namespace('administrators');
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'manageMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $service = new Application_Service_CertificateTemplates();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $service->saveCertificateTemplate($params);
            $this->redirect("/admin/certificate-templates");
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        $this->view->certificateTemplates = $service->getAllCertificateTemplates();
    }
}
