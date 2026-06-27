<?php

class Admin_DtsSettingsController extends Zend_Controller_Action
{
    public function init()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $this->_helper->layout()->pageName = 'configMenu';
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
    }

    public function indexAction()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        // some config settings are in config file and some in global_config table.
        $common = new Application_Service_Common();
        $schemeService = new Application_Service_Schemes();
        $dtsModel = new Application_Model_Dts();
        if ($request->isPost()) {

            $params = $this->getAllParams();
            $testKits = [];
            $testKits[1] = $request->getPost('dtsTestkit1');
            $testKits[2] = $request->getPost('dtsTestkit2');
            $testKits[3] = $request->getPost('dtsTestkit3');
            $schemeService->setRecommededDtsTestkit($testKits, 'dts');

            $dtsSyphilisTestKits = [];
            $dtsSyphilisTestKits[1] = $request->getPost('dtsSyphilisTestkit1');
            $dtsSyphilisTestKits[2] = $request->getPost('dtsSyphilisTestkit2');
            $dtsSyphilisTestKits[3] = $request->getPost('dtsSyphilisTestkit3');
            $schemeService->setRecommededDtsTestkit($dtsSyphilisTestKits, 'dts+syphilis');

            $dtsRtriTestKits = [];
            $dtsRtriTestKits[1] = $request->getPost('dtsRtriTestkit1');
            $dtsRtriTestKits[2] = $request->getPost('dtsRtriTestkit2');
            $dtsRtriTestKits[3] = $request->getPost('dtsRtriTestkit3');
            $schemeService->setRecommededDtsTestkit($dtsRtriTestKits, 'dts+rtri');

            $allowedAlgorithms = $request->getPost('allowedAlgorithms');
            if ($allowedAlgorithms) {
                $allowedAlgorithms = implode(',', $allowedAlgorithms);
            }
            if (isset($params['dts']) && !empty($params['dts'])) {
                $dts = json_encode($params['dts']);
                $common->saveSchemeConfigByName($dts, 'dts');
            }
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog('Updated HIV serology settings', 'config');

            // Settings just changed: any already-scored DTS shipment still holds a score
            // computed against the OLD settings. Surface them so the admin can opt to
            // re-evaluate. Finalized shipments are intentionally excluded (locked).
            $this->view->reEvalShipmentIds = $this->getReEvaluatableDtsShipmentIds();
        }

        $this->view->dtsConfig = Pt_Commons_SchemeConfig::get('dts');

        $this->view->allTestKits = $dtsModel->getAllDtsTestKitList(true);
        $this->view->dtsRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts');
        $this->view->dtsSyphilisRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts+syphilis');
        $this->view->dtsRtriRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts+rtri');
    }

    /**
     * DTS shipments whose stored scores were computed under the previous settings.
     * Excludes finalized shipments (locked) and anything not yet evaluated.
     *
     * @return int[]
     */
    private function getReEvaluatableDtsShipmentIds(): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from('shipment', ['shipment_id'])
            ->where('scheme_type = ?', 'dts')
            ->where('status IN (?)', ['evaluated', 'reports generated'])
            ->order('shipment_id ASC');

        return array_map('intval', $db->fetchCol($select));
    }

    /**
     * Re-evaluate a single DTS shipment (AJAX). One shipment per call so the client can
     * show progress and we avoid a single long-running request. Skips finalized/non-DTS
     * rows defensively in case status changed since the list was built.
     */
    public function reEvaluateAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!$request->isPost()) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
            return;
        }

        $shipmentId = (int) $request->getPost('shipmentId');
        if ($shipmentId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid shipment']);
            return;
        }

        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $row = $db->fetchRow(
                $db->select()
                    ->from('shipment', ['status', 'scheme_type'])
                    ->where('shipment_id = ?', $shipmentId)
            );

            // Only touch DTS shipments that are evaluated/reports-generated; never finalized.
            if (
                !$row
                || $row['scheme_type'] !== 'dts'
                || !in_array($row['status'], ['evaluated', 'reports generated'], true)
            ) {
                echo json_encode(['status' => 'skipped']);
                return;
            }

            $evalService = new Application_Service_Evaluation();
            $evalService->getShipmentToEvaluate($shipmentId, true);

            echo json_encode(['status' => 'success']);
        } catch (\Throwable $e) {
            Pt_Commons_LoggerUtility::logError('DTS re-evaluation failed for shipment ' . $shipmentId . ': ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
