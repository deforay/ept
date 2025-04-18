<?php

class Admin_EvaluateController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('analyze-generate-reports', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-shipments', 'html')
            ->addActionContext('update-shipment-comment', 'html')
            ->addActionContext('update-shipment-status', 'html')
            ->addActionContext('delete-dts-response', 'html')
            ->addActionContext('vl-range', 'html')
            ->addActionContext('assay-formats', 'html')
            ->addActionContext('exclude-participant', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'analyze';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
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
            // $userConfig = ($this->_getParam('userConfig'));
            $evalService = new Application_Service_Evaluation();
            $this->view->shipments = $shipment = $evalService->getShipments($id);
            if (isset($shipment) && !empty($shipment)) {
                $this->view->shipmentStatus = $evalService->getReportStatus($shipment[0]['shipment_id'], 'generateReport', true);
            }
        } else {
            $this->view->shipments = false;
        }
    }

    public function scheduleEvaluationAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        if ($this->hasParam('sid')) {
            $shipmentId = base64_decode($this->_getParam('sid'));
            $evalService = new Application_Service_Evaluation();
            return $evalService->scheduleEvaluation($shipmentId);
        } else {
            return 0;
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $evalService = new Application_Service_Evaluation();
            $response = $evalService->updateShipmentResults($params);
            $shipmentId = base64_encode($params['shipmentId']);
            $participantId = base64_encode($params['participantId']);
            $scheme = base64_encode($params['scheme']);
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            if ($response === false) {
                $alertMsg->message = "Shipment Results NOT UPDATED for this participant";
            } else {
                $alertMsg->message = "Shipment Results for this participant updated successfully";
            }

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

                $vlModel = new Application_Model_Vl();

                $commonService = new Application_Service_Common();
                $this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
                $participantService = new Application_Service_Participants();
                $this->view->participant = $participantService->getParticipantDetails($pid);
                if ($scheme == 'eid') {
                    $this->view->extractionAssay = $schemeService->getEidExtractionAssay();
                    $this->view->detectionAssay = $schemeService->getEidDetectionAssay();
                } elseif ($scheme == 'dts') {
                    $this->view->allTestKits = $schemeService->getAllDtsTestKit();
                } elseif ($scheme == 'dbs') {
                    $this->view->wb = $schemeService->getDbsWb();
                    $this->view->eia = $schemeService->getDbsEia();
                } elseif ($scheme == 'vl') {
                    $this->view->vlRange = $vlModel->getVlRange($sid);
                    $this->view->vlAssay = $vlModel->getVlAssay();
                } elseif ($scheme == 'recency') {
                    $this->view->recencyAssay = $schemeService->getRecencyAssay();
                } elseif ($scheme == 'covid19') {
                    $this->view->allTestTypes = $schemeService->getAllCovid19TestType();
                    $this->view->allGeneTypes = $schemeService->getAllCovid19GeneTypeResponseWise();
                    $this->view->geneIdentifiedTypes = $schemeService->getAllCovid19IdentifiedGeneTypeResponseWise($evaluateData['shipment']['map_id']);
                } elseif ($scheme == 'tb') {
                    $tbModel = new Application_Model_Tb();
                    $shipmentService = new Application_Service_Shipments();
                    $shipment = $schemeService->getShipmentData($sid, $pid);
                    $this->view->allNotTestedReason = $schemeService->getNotTestedReasons("tb");
                    $shipment['attributes'] = json_decode($shipment['attributes'], true);
                    $this->view->shipment = $shipment;
                    $this->view->shipId = $sid;
                    $this->view->participantId = $pid;
                    $this->view->scheme = $scheme;
                    $this->view->assay = $tbModel->getAllTbAssays();
                    $this->view->isEditable = $shipmentService->isShipmentEditable($sid, $pid);
                } elseif ($scheme == "generic-test") {
                    $this->view->allNotTestedReason = $schemeService->getNotTestedReasons("generic-test");
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $params = $request->getPost();
        if ($request->isPost()) {
            $evalService = new Application_Service_Evaluation();
            $result = $evalService->updateShipmentComment($params);
            if (isset($params['from']) && !empty($params['from']) && $params['from'] == 'evaluate') {
                $alertMsg->message = $result;
                $this->redirect("/admin/evaluate/shipment/sid/" . $params['sid']);
            } else {
                $this->view->message = $result;
            }
        } else {
            $this->view->message = "Unable to update shipment status. Please try again later.";
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($this->hasParam('mid')) {
            if ($request->isPost()) {
                $mapId = (int)base64_decode($this->_getParam('mid'));
                $schemeType = ($this->_getParam('schemeType'));
                $shipmentService = new Application_Service_Shipments();
                if ($schemeType == 'dts') {
                    $this->view->result = $shipmentService->removeDtsResults($mapId);
                } elseif ($schemeType == 'eid') {
                    $this->view->result = $shipmentService->removeDtsEidResults($mapId);
                } elseif ($schemeType == 'vl') {
                    $this->view->result = $shipmentService->removeDtsVlResults($mapId);
                } elseif ($schemeType == 'covid19') {
                    $this->view->result = $shipmentService->removeCovid19Results($mapId);
                } elseif ($schemeType == 'tb') {
                    $this->view->result = $shipmentService->removeTbResults($mapId);
                } elseif ($schemeType == 'generic-test') {
                    $this->view->result = $shipmentService->removeGenericTestResults($mapId);
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
        $vlModel       = new Application_Model_Vl();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($this->hasParam('manualRange')) {
            $params = $request->getPost();
            $schemeService = new Application_Service_Schemes();
            $vlModel->updateVlInformation($params);
            $shipmentId = (int)base64_decode($this->_getParam('sid'));
            $this->redirect("/admin/evaluate/index/scheme/vl/showcalc/" . base64_encode($shipmentId));
        }
        if ($this->hasParam('sid')) {
            if ($request->isPost()) {
                $shipmentId = (int)base64_decode($this->_getParam('sid'));
                $schemeService = new Application_Service_Schemes();
                $this->view->result = $vlModel->getVlRangeInformation($shipmentId);
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
            $vlModel = new Application_Model_Vl();
            $this->view->result = $vlModel->setVlRange($shipmentId);
            $this->redirect("/admin/evaluate/index/scheme/vl/showcalc/" . base64_encode($shipmentId));
        } else {
            $this->redirect("/admin/evaluate/");
        }
    }

    public function vlSamplePlotAction()
    {
        $shipmentId = $this->_getParam('shipment');
        $sampleId = $this->_getParam('sample');

        $vlModel = new Application_Model_Vl();
        //$this->view->sampleVldata = $vlModel->getVlRangeInformation($shipmentId,$sampleId);
        $this->view->vlRange = $vlModel->getVlRange($shipmentId, $sampleId);
        $this->view->shipmentId = $shipmentId;
        $this->view->sampleId = $sampleId;
    }

    public function addManualLimitsAction()
    {
        $vlModel       = new Application_Model_Vl();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $this->_helper->layout()->disableLayout();
        $this->_helper->layout()->setLayout('modal');
        $schemeService = new Application_Service_Schemes();
        if ($this->hasParam('id')) {
            $combineId = base64_decode($this->_getParam('id'));
            $expStr = explode("#", $combineId);
            $shipmentId = (int)$expStr[0];
            $sampleId = (int)$expStr[1];
            $vlAssay = (int)$expStr[2];
            $this->view->result = $vlModel->getVlManualValue($shipmentId, $sampleId, $vlAssay);
        }
        if ($request->isPost()) {
            $params = $request->getPost();
            $updatedResult = $vlModel->updateVlManualValue($params);
            $this->view->updatedResult = $updatedResult;
            $this->view->sampleId = base64_decode($params['sampleId']);
            $this->view->vlAssay = base64_decode($params['vlAssay']);
            $this->view->mLowLimit = round($params['manualLowLimit'], 4);
            $this->view->mHighLimit = round($params['manualHighLimit'], 4);
        }
    }

    public function assayFormatsAction()
    {
        $schemeService = new Application_Service_Schemes();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $sID = base64_decode($request->getParam('sid'));
        $pID = base64_decode($request->getParam('pid'));
        $assayId = ($request->getParam('assayId'));
        $type = $request->getParam('type');
        $assayType = $request->getParam('assayType');
        $assayDrug = $request->getParam('assayDrug');
        $tbModel = new Application_Model_Tb();
        $this->view->allSamples = $tbModel->getTbSamplesForParticipant($sID, $pID);
        $shipment = $schemeService->getShipmentData($sID, $pID);
        $shipment['attributes'] = json_decode($shipment['attributes'], true);
        $this->view->shipment = $shipment;
        $this->view->shipId = $sID;
        $this->view->type = $type;
        $this->view->assayType = $assayType;
        $this->view->assayDrug = $assayDrug;
    }

    public function excludeParticipantAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $participantService = new Application_Service_Participants();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->view->result = $participantService->excludeParticipantById($params);
        }
    }
}
