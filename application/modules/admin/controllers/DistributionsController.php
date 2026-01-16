<?php

class Admin_DistributionsController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if (!in_array('manage-shipments', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('view-shipment', 'html')
            ->addActionContext('ship-distribution', 'html')
            ->addActionContext('generate-survey-code', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'manageMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $distributionService = new Application_Service_Distribution();
            $distributionService->getAllDistributions($params);
        } elseif ($this->hasParam('searchString')) {
            $this->view->searchData = $this->_getParam('searchString');
        }
    }

    public function addAction()
    {

        $distributionService = new Application_Service_Distribution();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $distributionId = $distributionService->addDistribution($params);
            if (isset($params['shipmentPage']) && $params['shipmentPage'] == 'true' && $distributionId > 0) {
                $this->redirect("/admin/shipment/index/did/" . base64_encode($distributionId));
            } else {
                $this->redirect("/admin/distributions");
            }
        }
        // For accessing the common service methods
        $commonServices = new Application_Service_Common();
        $this->view->autogeneratePtCode = $commonServices->getConfig('auto_generate_pt_survey_code');
        $this->view->distributionDates = $distributionService->getDistributionDates();
    }

    public function viewShipmentAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->hasParam('id')) {
            $id = (int)$this->_getParam('id');
            $distributionService = new Application_Service_Distribution();
            $this->view->shipments = $distributionService->getShipments($id);
        }
    }

    public function shipDistributionAction()
    {
        if ($this->hasParam('did')) {
            $id = (int)base64_decode($this->_getParam('did'));
            $distributionService = new Application_Service_Distribution();
            $this->view->message = $distributionService->shipDistribution($id);
        } else {
            $this->view->message = "Unable to ship. Please try again later or contact system admin for help";
        }
    }

    public function editAction()
    {
        $distributionService = new Application_Service_Distribution();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $distributionId = $distributionService->updateDistribution($params);
            if (isset($params['shipmentPage']) && $params['shipmentPage'] == 'true' && $distributionId > 0) {
                $this->redirect("/admin/shipment/index/did/" . base64_encode($distributionId));
            } else {
                $this->redirect("/admin/distributions");
            }
        } elseif ($this->hasParam('d8s5_8d')) {
            $id = (int)base64_decode($this->_getParam('d8s5_8d'));
            $this->view->result = $distributionService->getDistribution($id);
            $this->view->distributionDates = $distributionService->getDistributionDates();
            if ($this->hasParam('5h8pp3t')) {
                $this->view->fromStatus = 'shipped';
            }
        } else {
            $this->redirect('admin/distributions/index');
        }
    }

    public function generateSurveyCodeAction()
    {
        $distributionService = new Application_Service_Distribution();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $ptDate = $this->getParam('ptDate');
            $this->_helper->viewRenderer->setNoRender(true);

            echo $distributionService->generateSurveyCode($ptDate);
        }
    }
}
