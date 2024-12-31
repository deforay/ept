<?php

class Admin_ParticipantMessagesController extends Zend_Controller_Action
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
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
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
            $distributionService = new Application_Service_ParticipantMessages();
            $distributionService->getParticipantMessage($params);
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
            // Zend_Debug::dump($params);die;
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

    public function viewAction()
    {
        $distributionService = new Application_Service_ParticipantMessages();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($this->hasParam('d8s5_8d')) {
            $id = (int)base64_decode($this->_getParam('d8s5_8d'));
            $this->view->result = $distributionService->getParticipantMessageById($id);
        } else {
            $this->redirect('admin/participant-message/index');
        }
    }

}
