<?php

class Reports_TestingFacilityByOwnershipController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('access-reports', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-report-data', 'json')
            ->addActionContext('export-testing-facility-by-ownership', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction()
    {
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();

        // Get initial data for display
        $reportService = new Application_Service_Reports();
        $this->view->availableShipments = $reportService->getAvailableShipments();
    }

    public function getReportDataAction()
    {
        $this->_helper->layout()->disableLayout();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $result = $reportService->getTestingFacilityByOwnerShips($params);

            $this->view->result = $result;
            echo Zend_Json::encode($result);
        }
    }

    public function exportTestingFacilityByOwnershipAction()
    {
        $this->_helper->layout()->disableLayout();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();

            try {
                $fileName = $reportService->exportTestingFacilityByOwnership($params);
                echo $fileName;
            } catch (Exception $e) {
                echo '';
            }
        }
    }
}
