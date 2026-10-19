<?php

class Reports_ShipmentsController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('access-reports', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                // init() returning does not abort ZF1 dispatch; halt so the
                // action never runs for unauthorized XHR callers.
                $this->getResponse()->setHttpResponseCode(403)->sendResponse();
                exit;
            }
            $this->redirect('/admin');
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-shipment-participant-list', 'html')
            ->addActionContext('shipments-export', 'html')
            ->addActionContext('vl-assay-distribution-plot', 'html')
            ->addActionContext('bulk-download-shipment-report-excel', 'html')
            ->addActionContext('participation-stats', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $reportService->getAllShipments($params);
        }

        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();

        $dataManagerService = new Application_Service_DataManagers();
        $this->view->dataManagers = $dataManagerService->getDataManagerList();
    }

    public function participationStatsAction()
    {
        $sid = (int) base64_decode((string) $this->_getParam('sid'));
        $this->view->stats = $sid > 0
            ? Application_Service_Shipments::getShipmentParticipationStats($sid)
            : [];
    }

    public function responseChartAction()
    {
        if ($this->hasParam('id')) {
            $shipmentId = (int) base64_decode($this->_getParam('id'));
            $reportService = new Application_Service_Reports();
            $this->view->responseCount = $reportService->getShipmentResponseCount($shipmentId, base64_decode($this->_getParam('shipmentDate')));
            $this->view->shipmentDate = base64_decode($this->_getParam('shipmentDate'));
            $this->view->shipmentCode = base64_decode($this->_getParam('shipmentCode'));

            // Fetch shipment details for display
            $shipmentService = new Application_Service_Shipments();
            $shipment = $shipmentService->getShipment($shipmentId);
            if ($shipment) {
                $shipmentArray = is_array($shipment) ? $shipment : $shipment->toArray();
                $this->view->shipmentStatus = ucfirst($shipmentArray['shipment_status'] ?? $shipmentArray['status'] ?? '');
                $this->view->resultDueDate = $shipmentArray['response_deadline'] ?? null;
            }
        } else {
            $this->redirect('/admin/index');
        }
    }

    public function getShipmentParticipantListAction()
    {
        $reportService = new Application_Service_Reports();
        if ($this->hasParam('shipmentId')) {
            $shipmentId = base64_decode($this->_getParam('shipmentId'));
            $schemeType = ($this->_getParam('schemeType'));
            $this->view->result = $reportService->getShipmentParticipant($shipmentId, $schemeType);
        }
    }

    public function shipmentsExportAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $reportService = new Application_Service_Reports();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->view->exported = $reportService->exportShipmentsReport($params);
        }
    }

    public function vlSampleAnalysisAction()
    {
        $reportService = new Application_Service_Reports();
        $adminSession = new Zend_Session_Namespace('administrators');
        $this->view->vlActive = in_array('vl', (array) ($adminSession->activeSchemes ?? []));
        $this->view->shipments = $reportService->getResponseReportShipments('vl', null, null);
    }

    public function vlSampleAnalysisDataAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getVlSampleAnalysis((int) $this->getParam('shipmentId')));
    }

    public function vlSampleAnalysisExportAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json(['file' => $reportService->exportVlSampleAnalysis((int) $this->getParam('shipmentId'))]);
    }

    public function vlAssayDistributionAction()
    {
        $participants = new Application_Service_Participants();
        $adminSession = new Zend_Session_Namespace('administrators');
        $this->view->vlActive = in_array('vl', (array) ($adminSession->activeSchemes ?? []));
        $this->view->countries = $participants->getParticipantCountriesList();
        $this->view->regions = $participants->getAllParticipantRegion();
        $this->view->states = $participants->getAllParticipantStates();
        $this->view->districts = $participants->getAllParticipantDistricts();
    }

    public function vlAssayDistributionDataAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getVlAssayDistribution($this->getAllParams()));
    }

    public function vlAssayDistributionExportAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json(['file' => $reportService->exportVlAssayDistribution($this->getAllParams())]);
    }

    /** The Shipment filter options of the VL reports. */
    public function vlShipmentsAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getResponseReportShipments('vl', $this->getParam('startDate'), $this->getParam('endDate')));
    }

    public function vlAssayDistributionPlotAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $this->view->distributions = $reportService->getVlAssayResponseDistributions($params);
        }
    }
    public function bulkDownloadShipmentReportExcelAction()
    {
        $shipments = $this->getRequest()->getPost('shipments', []);
        if (empty($shipments)) {
            echo '';
            return;
        }
        $reportService = new Application_Service_Reports();
        $reportService->bulkDownloadExcel($shipments);
    }
}
