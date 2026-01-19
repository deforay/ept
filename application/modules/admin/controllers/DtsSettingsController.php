<?php

class Admin_DtsSettingsController extends Zend_Controller_Action
{

    public function init()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $this->_helper->layout()->pageName = 'configMenu';
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
    }

    public function indexAction()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        // some config settings are in config file and some in global_config table.
        $common = new Application_Service_Common();
        $schemeService = new Application_Service_Schemes();
        $dtsModel = new Application_Model_Dts();
        if ($request->isPost()) {

            $params = $this->getAllParams();
            $testKits = [];
            $testKits[1] = $request->getPost('dtsTestkit1');
            $testKits[2] = $request->getPost('dtsTestkit2');
            $testKits[3] = $request->getPost('dtsTestkit3');
            $schemeService->setRecommededDtsTestkit($testKits, 'dts');

            $dtsSyphilisTestKits = [];
            $dtsSyphilisTestKits[1] = $request->getPost('dtsSyphilisTestkit1');
            $dtsSyphilisTestKits[2] = $request->getPost('dtsSyphilisTestkit2');
            $dtsSyphilisTestKits[3] = $request->getPost('dtsSyphilisTestkit3');
            $schemeService->setRecommededDtsTestkit($dtsSyphilisTestKits, 'dts+syphilis');

            $dtsRtriTestKits = [];
            $dtsRtriTestKits[1] = $request->getPost('dtsRtriTestkit1');
            $dtsRtriTestKits[2] = $request->getPost('dtsRtriTestkit2');
            $dtsRtriTestKits[3] = $request->getPost('dtsRtriTestkit3');
            $schemeService->setRecommededDtsTestkit($dtsRtriTestKits, 'dts+rtri');

            $allowedAlgorithms = $request->getPost('allowedAlgorithms');
            if ($allowedAlgorithms) {
                $allowedAlgorithms = implode(",", $allowedAlgorithms);
            }
            if (isset($params['dts']) && !empty($params['dts'])) {
                $dts = json_encode($params['dts']);
                $common->saveSchemeConfigByName($dts, 'dts');
            }
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Updated HIV Serology Settings", "config");
        }

        $this->view->dtsConfig = $common->getSchemeConfig('dts');

        $this->view->allTestKits = $dtsModel->getAllDtsTestKitList(true);
        $this->view->dtsRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts');
        $this->view->dtsSyphilisRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts+syphilis');
        $this->view->dtsRtriRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts+rtri');
    }
}
