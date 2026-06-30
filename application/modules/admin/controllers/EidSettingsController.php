<?php

class Admin_EidSettingsController extends Zend_Controller_Action
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
        try {
            $common = new Application_Service_Common();
            /** @var Zend_Controller_Request_Http $request */
            $request = $this->getRequest();
            if ($request->isPost()) {
                $params = $this->getAllParams();
                $eid = (isset($params['eid']) && is_array($params['eid'])) ? $params['eid'] : [];

                // Passing Score is optional. Blank or out-of-range is stored as blank so
                // evaluation falls back to 100 (see Application_Model_Eid). A valid value
                // must be a whole number between 1 and 100.
                $passingScore = isset($eid['passPercentage']) ? trim((string) $eid['passPercentage']) : '';
                if ($passingScore === '' || !is_numeric($passingScore)) {
                    $eid['passPercentage'] = '';
                } else {
                    $passingScore = (int) $passingScore;
                    $eid['passPercentage'] = ($passingScore >= 1 && $passingScore <= 100) ? $passingScore : '';
                }

                $common->saveSchemeConfigByName(json_encode($eid), 'eid');

                $auditDb = new Application_Model_DbTable_AuditLog();
                $auditDb->addNewAuditLog('Updated EID settings', 'config');

                // Settings changed: offer to re-evaluate shipments scored under the old config.
                $this->view->reEvalScheme = 'eid';
                $this->view->reEvalShipmentIds = (new Application_Service_Evaluation())->getReEvaluatableShipmentIds('eid');
            }
            $this->view->eidConfig = Pt_Commons_SchemeConfig::get('eid');
        } catch (Exception $exc) {

            Pt_Commons_LoggerUtility::logError('Failed to save EID settings: ' . $exc->getMessage(), [
                'file'  => $exc->getFile(),
                'line'  => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            return '';
        }
    }
}
