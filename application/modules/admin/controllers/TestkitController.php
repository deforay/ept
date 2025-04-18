<?php

class Admin_TestkitController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');

        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-testkit', 'html')
            ->addActionContext('update-status', 'html')
            ->initContext();

        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }

        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $schemeService = new Application_Service_Schemes();
            $schemeService->getAllDtsTestKitInGrid($params);
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getFullSchemeList();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeService->addTestkit($params);
            $this->redirect("/admin/testkit");
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getFullSchemeList();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeService->updateTestkit($params);
            $this->redirect("/admin/testkit");
        } elseif ($this->hasParam('53s5k85_8d')) {
            $id = base64_decode($this->_getParam('53s5k85_8d'));
            $this->view->result = $schemeService->getDtsTestkit($id);
        } else {
            $this->redirect('admin/testkit/index');
        }
    }

    public function standardKitAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeService->updateTestkitStage($params);
            $this->redirect("/admin/testkit/standard-kit");
        }
        $this->view->schemeList = $schemeService->getGenericSchemeLists();
    }

    public function getTestkitAction()
    {
        if ($this->hasParam('stage')) {
            $stage = $this->_getParam('stage');
            $dtsModel = new Application_Model_Dts();
            $this->view->testkitList = $dtsModel->getAllDtsTestKitList(true, $stage);
            $this->view->testkitStage = $stage;
        }
    }

    public function updateStatusAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $stage = $this->_getParam('stage');
            $params = $request->getPost();
            $dtsModel = new Application_Model_Dts();
            $this->view->testkitList = $dtsModel->updateTestKitStatus($params);
            $this->view->testkitStage = $stage;
        }
    }
}
