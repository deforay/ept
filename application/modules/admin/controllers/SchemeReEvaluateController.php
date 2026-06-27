<?php

/**
 * Shared AJAX endpoint for the post-save "re-evaluate" nudge on the scheme settings
 * pages (DTS, VL, COVID-19, Recency, TB, custom tests). Re-evaluates ONE shipment per
 * call so the client can show progress and we avoid a single long-running request.
 *
 * Gated on 'config-ept' to match the settings pages that invoke it.
 */
class Admin_SchemeReEvaluateController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

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

    public function reEvaluateAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        // Enforce authorization at the action level: returning null from init() does NOT
        // stop dispatch in Zend 1, so the init() guard alone leaves this XHR endpoint
        // reachable by a logged-in admin lacking 'config-ept'. Re-check here.
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = $adminSession->privileges ? explode(',', $adminSession->privileges) : [];
        if (!in_array('config-ept', $privileges, true)) {
            $this->getResponse()->setHttpResponseCode(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
            return;
        }

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!$request->isPost()) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
            return;
        }

        $shipmentId = (int) $request->getPost('shipmentId');
        $scheme = (string) $request->getPost('scheme');
        if ($shipmentId <= 0 || $scheme === '') {
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

            // Defensive guard: only touch the requested scheme's evaluated/reports-generated
            // shipments; never finalized (status may have changed since the list was built).
            if (
                !$row
                || $row['scheme_type'] !== $scheme
                || !in_array($row['status'], ['evaluated', 'reports generated'], true)
            ) {
                echo json_encode(['status' => 'skipped']);
                return;
            }

            $evalService = new Application_Service_Evaluation();
            $evalService->getShipmentToEvaluate($shipmentId, true);

            echo json_encode(['status' => 'success']);
        } catch (\Throwable $e) {
            Pt_Commons_LoggerUtility::logError('Scheme re-evaluation failed for shipment ' . $shipmentId . ': ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
