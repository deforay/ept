<?php

class CapaController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('capa-export', 'html')
            ->initContext();
    }

    public function indexAction()
    {
        $common = new Application_Service_Common();
        $capaEnabled = $common->getConfig('enable_capa');
        if (!isset($capaEnabled) || empty($capaEnabled) || $capaEnabled != 'yes') {
            $this->redirect('/participant/dashboard');
        }
        $this->_helper->layout()->activeMenu = 'capa-menu';
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getShipmentFinalizedByParticipants($params);
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();

        $participantService = new Application_Service_Participants();
        $this->view->participants = $participantService->getAllActiveParticipants();
    }

    public function capaAction()
    {
        $common = new Application_Service_Common();
        $capaEnabled = $common->getConfig('enable_capa');
        if (!isset($capaEnabled) || empty($capaEnabled) || $capaEnabled != 'yes') {
            $this->redirect('/participant/dashboard');
        }
        $shipmentService = new Application_Service_Shipments();
        // A data manager sees and saves only their own labs' corrective actions.
        $dmId = Application_Service_Common::getScopedDmId();
        if ($dmId === null) {
            $this->redirect('/participant/dashboard');
        }
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $shipmentService->savePreventiveActions($params, $dmId);
            $this->redirect('/capa');
        } elseif ($this->hasParam('id')) {
            $id = (int) base64_decode((string) $this->_getParam('id'));
            if ($id <= 0 || !$shipmentService->dmManagesParticipant($dmId, $id)) {
                $this->redirect('/capa');
            }
            // $id is a participant id. The query is scoped to the data manager too.
            $this->view->correctiveActions = $shipmentService->getCorrectiveActionByShipmentId($id, '', $dmId);
        } else {
            $this->redirect('/capa');
        }
    }

    public function capaExportAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $shipmentService = new Application_Service_Shipments();
            $params = $this->getAllParams();
            if (isset($params['type']) && !empty($params['type']) && $params['type'] == 'view') {
                $this->view->result = $shipmentService->exportCaPaViewReport($params);
            } else {
                $this->view->result = $shipmentService->exportCaPaReport($params);
            }
        }
    }
}
