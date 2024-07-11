<?php

class CapaController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
        ->initContext();
    }

    public function indexAction()
    {
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

        $dataManagerService = new Application_Service_DataManagers();
        $this->view->dataManagers = $dataManagerService->getDataManagerList();
    }

    public function capaAction(){
        $shipmentService = new Application_Service_Shipments();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $result = $shipmentService->savePreventiveActions($params);
            $this->redirect('/capa');
        }else if ($this->hasParam('id')) {
            $id = (int) base64_decode($this->_getParam('id'));
            $this->view->correctiveActions = $shipmentService->getCorrectiveActionByShipmentId($id);
        }else{
            $this->redirect('/capa');
        }
    }
}

