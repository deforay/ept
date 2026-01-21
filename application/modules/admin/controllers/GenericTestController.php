<?php

class Admin_GenericTestController extends Zend_Controller_Action
{

    public function init()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $this->getAllParams();
            $service = new Application_Service_Schemes();
            $service->getAllGenericTestInGrid($parameters);
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $common = new Application_Service_Common();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeCode = $params['schemeCode'];

            $generic = [];
            if (isset($params['genericConfig']['passingScore']) && !empty($params['genericConfig']['passingScore'])) {
                $generic['passingScore'] = $params['genericConfig']['passingScore'];
            }
            if (isset($params['genericConfig']['disableOtherTestkit']) && !empty($params['genericConfig']['disableOtherTestkit'])) {
                $generic['disableOtherTestkit'] = $params['genericConfig']['disableOtherTestkit'];
            }
            if (isset($generic) && !empty($generic)) {
                $common->saveSchemeConfigByName(json_encode($generic), $schemeCode);
            }

            $schemeService->saveGenericTest($params);
            $schemeService->setRecommededCustomTestTypes($params);
            $this->redirect("/admin/generic-test");
        }
        $dtsModel = new Application_Model_Dts();
        $this->view->allTestKits = $dtsModel->getAllDtsTestKitList(false, 'custom-tests');
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $common = new Application_Service_Common();
        $this->view->schemeList = $schemeService->getFullSchemeList();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeCode = $params['schemeCode'];
            $generic = [];
            if (isset($params['genericConfig']['passingScore']) && !empty($params['genericConfig']['passingScore'])) {
                $generic['passingScore'] = $params['genericConfig']['passingScore'];
            }
            if (isset($params['genericConfig']['disableOtherTestkit']) && !empty($params['genericConfig']['disableOtherTestkit'])) {
                $generic['disableOtherTestkit'] = $params['genericConfig']['disableOtherTestkit'];
            }
            if (isset($generic) && !empty($generic)) {
                $common->saveSchemeConfigByName(json_encode($generic), $schemeCode);
            }
            $schemeService->saveGenericTest($params);
            $schemeService->setRecommededCustomTestTypes($params);
            $this->redirect('admin/generic-test');
        } elseif ($this->hasParam('id')) {
            $id = base64_decode($this->_getParam('id'));
            $this->view->result = $result =  $schemeService->getGenericTest($id);
            $schemeCode = $result['schemeResult']['scheme_id'];
            $dtsModel = new Application_Model_Dts();
            $db = new Application_Model_GenericTest();
            $this->view->allTestKits = $dtsModel->getAllDtsTestKitList(false, 'custom-tests');
            $this->view->customTestsRecommendedTestkits = $db->getRecommededGenericTestkits($schemeCode);
        }
        $this->view->disableOtherTestkit = Pt_Commons_SchemeConfig::get($schemeCode . '.disableOtherTestkit');
        $this->view->passingScore = Pt_Commons_SchemeConfig::get($schemeCode . '.passingScore');
    }
}
