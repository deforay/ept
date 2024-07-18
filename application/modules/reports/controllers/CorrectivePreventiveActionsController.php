<?php

class Reports_CorrectivePreventiveActionsController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('access-reports', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
        ->addActionContext('capa-export', 'html')
        ->initContext();
    }

    public function indexAction()
    {
        $common = new Application_Service_Common();
        $capaEnabled = $common->getConfig('enable_capa');
        if(!isset($capaEnabled) || empty($capaEnabled) || $capaEnabled != 'yes'){
            $this->redirect('/admin');
        }
        $this->_helper->layout()->activeMenu = 'capa-menu';
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getShipmentFinalaizedByrticipants($params);
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();

        $participantService = new Application_Service_Participants();
        $this->view->participants = $participantService->getAllActiveParticipants();
    }

    public function capaAction(){
        $common = new Application_Service_Common();
        $capaEnabled = $common->getConfig('enable_capa');
        if(!isset($capaEnabled) || empty($capaEnabled) || $capaEnabled != 'yes'){
            $this->redirect('/admin');
        }
        $shipmentService = new Application_Service_Shipments();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $result = $shipmentService->savePreventiveActions($params);
            $this->redirect('/reports/corrective-preventive-actions');
        }else if ($this->hasParam('id')) {
            $id = (int) base64_decode($this->_getParam('id'));
            $this->view->correctiveActions = $shipmentService->getCorrectiveActionByShipmentId($id, 'admin');
        }else{
            $this->redirect('/reports/corrective-preventive-actions');
        }
    }

    public function capaExportAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $shipmentService = new Application_Service_Shipments();
            $params = $this->getAllParams();
            if(isset($params['type']) && !empty($params['type']) && $params['type'] == 'view'){
                $this->view->result = $shipmentService->exportCaPaViewReport($params);
            }else{
                $this->view->result = $shipmentService->exportCaPaReport($params);
            }
        }
    }
}