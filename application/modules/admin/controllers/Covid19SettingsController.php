<?php

class Admin_Covid19SettingsController extends Zend_Controller_Action
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
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        $common = new Application_Service_Common();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        if ($request->isPost()) {
            $testPlatforms[1] = $request->getPost('testPlatform1');
            $testPlatforms[2] = $request->getPost('testPlatform2');
            $testPlatforms[3] = $request->getPost('testPlatform3');

            $schemeService->setRecommededCovid19TestTypes($testPlatforms);

            $params = $this->getAllParams();
            if (isset($params['covid19']) && !empty($params['covid19'])) {
                $covid19 = json_encode($params['covid19']);
                $common->saveConfigByName($covid19, 'covid19');
            }
        }
        $this->view->covid19Config = $common->getSchemeConfig('covid19');
        $this->view->allTestTypes = $schemeService->getAllCovid19TestTypeResponseWise(true);
        $this->view->recommendedTesttypes = $schemeService->getRecommededCovid19TestTypes();
    }
}
