<?php

class CommonController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
$ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('check-duplicate', 'html')
            ->addActionContext('delete-response', 'html')
            ->addActionContext('get-all-countries', 'html')
            ->addActionContext('get-country-wise-states', 'html')
            ->addActionContext('get-state-wise-districts', 'html')
            ->addActionContext('generate-password', 'html')
            ->addActionContext('get-state-districts-wise-institute', 'html')
            ->addActionContext('get-shipments-by-scheme', 'html')
            ->addActionContext('get-shipments-by-date', 'html')
            ->addActionContext('get-options-by-value', 'html')
            ->initContext();
    }

    public function indexAction()
    {
        // action body
    }

    public function sendMailAction()
    {
        $commonServices = new Application_Service_Common();
        $this->view->data = $commonServices->sendTempMail();
    }

    public function checkDuplicateAction()
    {
        if (!$this->hasParam('tableName')) {
            $this->view->data = "";
        } else {
            $params = $this->getAllParams();
            $commonServices = new Application_Service_Common();
            $this->view->data = $commonServices->checkDuplicate($params);
        }
    }

    public function deleteAction()
    {
    }

    public function deleteResponseAction()
    {
        if ($this->hasParam('mid')) {
            if ($this->getRequest()->isPost()) {
                $mapId = (int)base64_decode($this->_getParam('mid'));
                $schemeType = ($this->_getParam('schemeType'));
                $shipmentService = new Application_Service_Shipments();
                if ($schemeType == 'dts') {
                    $this->view->result = $shipmentService->removeDtsResults($mapId);
                } else if ($schemeType == 'eid') {
                    $this->view->result = $shipmentService->removeDtsEidResults($mapId);
                } else if ($schemeType == 'vl') {
                    $this->view->result = $shipmentService->removeDtsVlResults($mapId);
                } else if ($schemeType == 'recency') {
                    $this->view->result = $shipmentService->removeRecencyResults($mapId);
                } else if ($schemeType == 'covid19') {
                    $this->view->result = $shipmentService->removeCovid19Results($mapId);
                } else if ($schemeType == 'tb') {
                    $this->view->result = $shipmentService->removeTbResults($mapId);
                } else if ($schemeType == 'generic-test') {
                    $this->view->result = $shipmentService->removeGenericTestResults($mapId);
                }
            }
        } else {
            $this->view->message = "Unable to delete. Please try again later or contact system admin for help";
        }
    }

    public function notifyStatusAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->getRequest()->isPost()) {
            $id = (int)$this->_getParam('nid');
            $commonService = new Application_Service_Common();
            $this->view->result = $commonService->saveNotifyStatus($id);
        }
    }

    public function getAllCountriesAction()
    {
        $this->_helper->layout()->disableLayout();
        $commonService = new Application_Service_Common();
        if ($this->hasParam('search')) {
            $search = $this->_getParam('search');
            $this->view->countries = $commonService->getAllCountries($search);
        }
    }

    public function getCountryWiseStatesAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->getRequest()->isPost()) {
            $id = $this->_getParam('cid');
            $commonService = new Application_Service_Common();
            $this->view->states = $commonService->getParticipantsProvinceList($id);
        }
    }

    public function getStateWiseDistrictsAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->getRequest()->isPost()) {
            $id = $this->_getParam('pid');
            $commonService = new Application_Service_Common();
            $this->view->districts = $commonService->getParticipantsDistrictList($id);
        }
    }

    public function getStateDistrictsWiseInstituteAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->getRequest()->isPost()) {
            $pid = $this->_getParam('pid');
            $did = $this->_getParam('did');
            $commonService = new Application_Service_Common();
            $this->view->institutes = $commonService->getAllInstitutes($pid, $did);
        }
    }
    
    public function generatePasswordAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->getRequest()->isPost()) {
            $commonService = new Application_Service_Common();
            $this->view->institutes = $commonService->generatePassword();
        }
    }

    public function getShipmentsBySchemeAction()
    {
        if ($this->getRequest()->isPost()) {
            $schemeType = $this->_getParam('schemeType');
            $startDate = $this->_getParam('startDate');
            $endDate = $this->_getParam('endDate');
            $reportService = new Application_Service_Reports();
            $response = $reportService->getShipmentsByScheme($schemeType, $startDate, $endDate);
            $this->view->shipmentList = $response;
        }
    }

    public function getShipmentsByDateAction()
    {
        if ($this->getRequest()->isPost()) {
            $schemeType = $this->_getParam('schemeType');
            $startDate = $this->_getParam('startDate');
            $endDate = $this->_getParam('endDate');
            $reportService = new Application_Service_Reports();
            $response = $reportService->getShipmentsByDate($schemeType, $startDate, $endDate);
            $this->view->shipmentList = $response;
        }
    }

    public function getOptionsByValueAction()
    {
        if ($this->getRequest()->isPost()) {
            $commonService = new Application_Service_Common();
            $params = $this->getAllParams();
            $this->view->result = $commonService->getOptionsByValue($params);
            $this->view->params = $params;
        }
    }
}
