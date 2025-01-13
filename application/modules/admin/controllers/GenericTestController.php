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
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $sec = APPLICATION_ENV;
        if ($request->isPost()) {
            $params = $request->getPost();
            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $schemeCode = $params['schemeCode'];
            $config->$sec->evaluation->$schemeCode = [];
            $config->$sec->evaluation->$schemeCode->disableOtherTestkit = $params['disableOtherTestkit'] ?? 'no';
            $config->$sec->evaluation->$schemeCode->passPercentage = $params['genericConfig']['passingScore'] ?? '80';
            $writer = new Zend_Config_Writer_Ini(array(
                'config'   => $config,
                'filename' => $file
            ));
            $writer->write();
            $schemeService->saveGenericTest($params);
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
        $this->view->schemeList = $schemeService->getFullSchemeList();
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $sec = APPLICATION_ENV;
        if ($request->isPost()) {
            $params = $request->getPost();
            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $schemeCode = $params['schemeCode'];
            $config->$sec->evaluation->$schemeCode = [];
            $config->$sec->evaluation->$schemeCode->disableOtherTestkit = $params['disableOtherTestkit'] ?? 'no';
            $config->$sec->evaluation->$schemeCode->passPercentage = $params['genericConfig']['passingScore'] ?? '80';
            $writer = new Zend_Config_Writer_Ini(array(
                'config'   => $config,
                'filename' => $file
            ));
            $writer->write();
            $config1 = new Zend_Config_Ini($file, APPLICATION_ENV);
            $schemeService->saveGenericTest($params);
            $schemeService->setRecommededCustomTestTypes($params);
            $this->redirect('admin/generic-test');
        } elseif ($this->hasParam('id')) {
            $id = base64_decode($this->_getParam('id'));
            $this->view->result = $result =  $schemeService->getGenericTest($id);
            $config = new Zend_Config_Ini($file, APPLICATION_ENV);
            $schemeCode = $result['schemeResult']['scheme_id'];
            $dtsModel = new Application_Model_Dts();
            $db = new Application_Model_GenericTest();
            $this->view->allTestKits = $dtsModel->getAllDtsTestKitList(false, 'custom-tests');
            $this->view->customTestsRecommendedTestkits = $db->getRecommededGenericTestkits($schemeCode);
            $this->view->disableOtherTestkit = $config->evaluation->$schemeCode->disableOtherTestkit ?? 'no';
        }
    }
}
