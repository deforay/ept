<?php

class Admin_SampleNotTestedReasonsController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-testkit', 'html')
            ->addActionContext('update-status', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $schemeService = new Application_Service_Schemes();
            $schemeService->getAllSampleNotTeastedReasonsInGrid($params);
        }
    }

    public function addAction()
    {
        $schemeService = new Application_Service_Schemes();
        $commonServices = new Application_Service_Common();
        $this->view->schemeList = $schemeService->getFullSchemeList();
        $this->view->allSchemes = $commonServices->getFullSchemesDetails();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $schemeService->addTestkit($params);
            $this->redirect("/admin/testkit");
        }
    }

    public function editAction()
    {
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getFullSchemeList();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $schemeService->updateTestkit($params);
            $this->redirect("/admin/testkit");
        } else if ($this->hasParam('53s5k85_8d')) {
            $id = base64_decode($this->_getParam('53s5k85_8d'));
            $this->view->result = $schemeService->getDtsTestkit($id);
        } else {
            $this->redirect('admin/testkit/index');
        }
    }

    public function updateStatusAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $dtsModel = new Application_Model_Dts();
            $this->view->testkitList = $dtsModel->updateTestKitStatus($params);
            $this->view->testkitStage = $stage;
        }
    }
}
