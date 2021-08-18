<?php

class Admin_EvaluateController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-shipments', 'html')
            ->addActionContext('update-shipment-comment', 'html')
            ->addActionContext('update-shipment-status', 'html')
            ->addActionContext('delete-dts-response', 'html')
            ->addActionContext('vl-range', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'analyze';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $evalService = new Application_Service_Evaluation();
            $evalService->getAllDistributions($params);
        }
        if ($this->hasParam('scheme') && $this->hasParam('showcalc')) {
            $this->view->showcalc = ($this->_getParam('showcalc'));
            $this->view->scheme = $this->_getParam('scheme');
        }
    }

    public function getShipmentsAction()
    {
        if ($this->hasParam('did')) {
            $id = (int)($this->_getParam('did'));
            $evalService = new Application_Service_Evaluation();
            $this->view->shipments = $evalService->getShipments($id);
        } else {
            $this->view->shipments = false;
        }
    }

    public function shipmentAction()
    {
        if ($this->hasParam('sid')) {
            $id = (int)base64_decode($this->_getParam('sid'));
            $reEvaluate = false;
            $override = "";
            if ($this->hasParam('override')) {
                if (base64_decode($this->_getParam('override')) == 'yes') {
                    $override = 'yes';
                }
                if (base64_decode($this->_getParam('override')) == 'no') {
                    $override = 'no';
                }
            }
            if ($this->hasParam('re')) {
                if (base64_decode($this->_getParam('re')) == 'yes') {
                    $reEvaluate = true;
                }
            }
            $evalService = new Application_Service_Evaluation();
            $this->view->override = $override;
            $this->view->id = $this->_getParam('sid');
            $shipment = $this->view->shipment = $evalService->getShipmentToEvaluate($id, $reEvaluate, $override);
            $this->view->shipmentsUnderDistro = $evalService->getShipments($shipment[0]['distribution_id']);
        } else {
            $this->redirect("/admin/evaluate/");
        }
    }

    public function editAction()
    {
        if ($this->getRequest()->isPost()) {

            $params = $this->getRequest()->getPost();
            $evalService = new Application_Service_Evaluation();
            $evalService->updateShipmentResults($params);
            $shipmentId = base64_encode($params['shipmentId']);
            $participantId = base64_encode($params['participantId']);
            $scheme = base64_encode($params['scheme']);
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = "Shipment Results for this participant updated successfully";
            if (isset($params['whereToGo']) && $params['whereToGo'] != "") {
                $this->redirect($params['whereToGo']);
            } else {
                $this->redirect("/admin/evaluate/shipment/sid/$shipmentId");
            }
        } else {
            if ($this->hasParam('sid') && $this->hasParam('pid')  && $this->hasParam('scheme')) {

                $this->view->currentUrl = "/admin/evaluate/edit/sid/" . $this->_getParam('sid') . "/pid/" . $this->_getParam('pid') . "/scheme/" . $this->_getParam('scheme');

                $sid = (int)base64_decode($this->_getParam('sid'));
                $pid = (int)base64_decode($this->_getParam('pid'));
                $this->view->scheme = $scheme = base64_decode($this->_getParam('scheme'));

                $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
                $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);

                $evalService = new Application_Service_Evaluation();
                $this->view->evaluateData = $evaluateData = $evalService->editEvaluation($sid, $pid, $scheme);

                $schemeService = new Application_Service_Schemes();
                
                $commonService = new Application_Service_Common();
                $this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();

                if ($scheme == 'eid') {
                    
                    $this->view->extractionAssay = $schemeService->getEidExtractionAssay();
                    $this->view->detectionAssay = $schemeService->getEidDetectionAssay();
                } else if ($scheme == 'dts') {
                    $this->view->allTestKits = $schemeService->getAllDtsTestKit();
                } else if ($scheme == 'dbs') {
                    $this->view->wb = $schemeService->getDbsWb();
                    $this->view->eia = $schemeService->getDbsEia();
                } else if ($scheme == 'vl') {
                    $this->view->vlRange = $schemeService->getVlRange($sid);
                    $this->view->vlAssay = $schemeService->getVlAssay();
                } else if ($scheme == 'recency') {
                    $this->view->recencyAssay = $schemeService->getRecencyAssay();
                } else if ($scheme == 'covid19') {
                    $this->view->allTestTypes = $schemeService->getAllCovid19TestType();
                    $this->view->allGeneTypes = $schemeService->getAllCovid19GeneTypeResponseWise();
                    $this->view->geneIdentifiedTypes = $schemeService->getAllCovid19IdentifiedGeneTypeResponseWise($evaluateData['shipment']['map_id']);
                }
                $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
                $this->view->customField1 = $globalConfigDb->getValue('custom_field_1');
                $this->view->customField2 = $globalConfigDb->getValue('custom_field_2');
                $this->view->haveCustom = $globalConfigDb->getValue('custom_field_needed');
            } else {
                $this->redirect("/admin/evaluate/");
            }
        }
    }

    public function updateShipmentCommentAction()
    {
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $params = $this->getRequest()->getPost();
        if ($this->getRequest()->isPost()) {
            $evalService = new Application_Service_Evaluation();
            $result = $evalService->updateShipmentComment($params);
            if ($result == 'Comment updated') {
                $alertMsg->message = "Comment updated";
                $this->redirect("/admin/evaluate/shipment/sid/" . $params['shipmentId']);
            }
        } else {
            $this->redirect("/admin/evaluate/shipment/sid/" . $params['shipmentId']);
            $alertMsg->message = "Unable to update shipment status. Please try again later.";
        }
        /* if ($this->hasParam('sid')) {
            $sid = (int)base64_decode($this->_getParam('sid'));
            $comment = $this->_getParam('comment');
            $evalService = new Application_Service_Evaluation();
            $this->view->message = $evalService->updateShipmentComment($sid, $comment);
        } else {
            $this->view->message = "Unable to update shipment comment. Please try again later.";
        } */
    }

    public function updateShipmentStatusAction()
    {
        if ($this->hasParam('sid')) {
            $sid = (int)base64_decode($this->_getParam('sid'));
            $status = $this->_getParam('status');
            $evalService = new Application_Service_Evaluation();
            $this->view->message = $evalService->updateShipmentStatus($sid, $status);
        } else {
            $this->view->message = "Unable to update shipment status. Please try again later.";
        }
    }

    public function deleteDtsResponseAction()
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
                } else if ($schemeType == 'covid19') {
                    $this->view->result = $shipmentService->removeCovid19Results($mapId);
                } else {
                    $this->view->result = "Failed to delete";
                }
            }
        } else {
            $this->view->message = "Unable to delete. Please try again later or contact system admin for help";
        }
    }

    public function vlRangeAction()
    {
        if ($this->hasParam('manualRange')) {
            $params = $this->getRequest()->getPost();
            $schemeService = new Application_Service_Schemes();
            $schemeService->updateVlInformation($params);
            $shipmentId = (int)base64_decode($this->_getParam('sid'));
            $this->redirect("/admin/evaluate/index/scheme/vl/showcalc/" . base64_encode($shipmentId));
        }
        if ($this->hasParam('sid')) {
            if ($this->getRequest()->isPost()) {
                $shipmentId = (int)base64_decode($this->_getParam('sid'));
                $schemeService = new Application_Service_Schemes();
                $this->view->result = $schemeService->getVlRangeInformation($shipmentId);
                $this->view->shipmentId = $shipmentId;
            }
        } else {
            $this->view->message = "Unable to fetch Viral Load Range for this Shipment.";
        } // action body

    }

    public function recalculateVlRangeAction()
    {
        if ($this->hasParam('sid')) {
            $shipmentId = (int)($this->_getParam('sid'));
            $methodOfEvaluation = ($this->_getParam('method'));
            $schemeService = new Application_Service_Schemes();
            $this->view->result = $schemeService->setVlRange($shipmentId, $methodOfEvaluation);
            $this->redirect("/admin/evaluate/index/scheme/vl/showcalc/" . base64_encode($shipmentId));
        } else {
            $this->redirect("/admin/evaluate/");
        }
    }

    public function vlSamplePlotAction()
    {
        $shipmentId = $this->_getParam('shipment');
        $sampleId = $this->_getParam('sample');

        $schemeService = new Application_Service_Schemes();
        //$this->view->sampleVldata = $schemeService->getVlRangeInformation($shipmentId,$sampleId);
        $this->view->vlRange = $schemeService->getVlRange($shipmentId, $sampleId);
        $this->view->shipmentId = $shipmentId;
        $this->view->sampleId = $sampleId;
    }

    public function addManualLimitsAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->layout()->setLayout('modal');
        $schemeService = new Application_Service_Schemes();
        if ($this->hasParam('id')) {
            $combineId = base64_decode($this->_getParam('id'));
            $expStr = explode("#", $combineId);
            $shipmentId = (int)$expStr[0];
            $sampleId = (int)$expStr[1];
            $vlAssay = (int)$expStr[2];
            $this->view->result = $schemeService->getVlManualValue($shipmentId, $sampleId, $vlAssay);
        }
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $updatedResult = $schemeService->updateVlManualValue($params);
            $this->view->updatedResult = $updatedResult;
            $this->view->sampleId = base64_decode($params['sampleId']);
            $this->view->vlAssay = base64_decode($params['vlAssay']);
            $this->view->mLowLimit = round($params['manualLowLimit'], 4);
            $this->view->mHighLimit = round($params['manualHighLimit'], 4);
        }
    }
}
