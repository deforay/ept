<?php

class CustomTestController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('assay-formats', 'html')
            ->initContext();
    }

    public function indexAction()
    {
        // action body
    }

    public function responseAction()
    {

        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();
        $model = new Application_Model_CustomTest();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost();
            $shipmentService->updateGenericTestResults($data);
            if (isset($data['reqAccessFrom']) && !empty($data['reqAccessFrom']) && $data['reqAccessFrom'] == 'admin') {
                $this->redirect("/admin/evaluate/shipment/sid/" . base64_encode($data['shipmentId']));
            } elseif (isset($data['confirmForm']) && trim($data['confirmForm']) == 'yes') {
                $this->redirect("/participant/current-schemes");
            } else {
                $_SESSION['confirmForm'] = "yes";
                $this->redirect("/custom-test/response/sid/" . $data['shipmentId'] . "/pid/" . $data['participantId'] . "/eid/" . $data['evId'] . "/uc/yes");
            }
        } else {
            $sID = $request->getParam('sid');
            $pID = $request->getParam('pid');
            $eID = $request->getParam('eid');
            $uc = $request->getParam('uc');
            $this->view->comingFrom = $request->getParam('comingFrom');
            $reqFrom = $request->getParam('from');
            if (isset($reqFrom) && !empty($reqFrom) && $reqFrom == 'admin') {
                $evalService = new Application_Service_Evaluation();
                $this->view->evaluateData = $evalService->editEvaluation($sID, $pID, 'generic-test', $uc);
                $this->_helper->layout()->setLayout('admin');
            }
            $this->view->allSamples = $model->getSamplesForParticipant($sID, $pID);
            $participantService = new Application_Service_Participants();
            $this->view->participant = $participantService->getParticipantDetails($pID);
            $shipment = $schemeService->getShipmentData($sID, $pID);
            $this->view->allNotTestedReason = $schemeService->getNotTestedReasons($shipment['scheme_type']);
            $shipment['attributes'] = json_decode($shipment['attributes'], true);
            $this->view->otherTestsPossibleResults = $schemeService->getPossibleResults($shipment['scheme_type'], 'participant');
            $this->view->shipment = $shipment;
            $this->view->shipId = $sID;
            $this->view->participantId = $pID;
            $this->view->eID = $eID;
            $this->view->reqFrom = $reqFrom;
            $this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);
            $commonService = new Application_Service_Common();

            $this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
            $this->view->globalQcAccess = $commonService->getConfig('qc_access');
            $kitDb = new Application_Model_DbTable_Testkitnames();
            $this->view->allTestKits = $kitDb->getAllTestKitList($shipment['scheme_type']);


            $disableOtherTestkit = Pt_Commons_SchemeConfig::get('custom.disableOtherTestkit');

            $schemeCode = $shipment['scheme_type'];
            $this->view->disableOtherTestkit = $disableOtherTestkit ?? 'no';
        }
    }

    public function downloadAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $this->_helper->layout()->disableLayout();
        $sID = $request->getParam('sid');
        $pID = $request->getParam('pid');
        $eID = $request->getParam('eid');

        $reportService = new Application_Service_Reports();
        $this->view->header = $reportService->getReportConfigValue('report-header');
        $this->view->logo = $reportService->getReportConfigValue('logo');
        $this->view->logoRight = $reportService->getReportConfigValue('logo-right');


        //$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        // $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $this->view->config = Pt_Commons_SchemeConfig::get('custom');


        $participantService = new Application_Service_Participants();
        $this->view->participant = $participantService->getParticipantDetails($pID);
        $schemeService = new Application_Service_Schemes();
        $this->view->referenceDetails = $schemeService->getDtsReferenceData($sID);

        $shipment = $schemeService->getShipmentData($sID, $pID);
        $shipment['attributes'] = json_decode($shipment['attributes'], true);
        $this->view->shipment = $shipment;
    }
}
