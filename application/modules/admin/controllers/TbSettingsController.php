<?php

class Admin_TbSettingsController extends Zend_Controller_Action
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
                // init() returning does not abort ZF1 dispatch; halt so the
                // action never runs for unauthorized XHR callers.
                $this->getResponse()->setHttpResponseCode(403)->sendResponse();
                exit;
            }
            $this->redirect('/admin');
            return;
        }
    }

    public function indexAction()
    {
        $common = new Application_Service_Common();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            if (isset($params['tb']) && !empty($params['tb'])) {
                $tb = json_encode($params['tb']);
                $common->saveSchemeConfigByName($tb, 'tb');
            }
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog('Updated TB settings', 'config');

            // Settings changed: offer to re-evaluate shipments scored under the old config.
            $this->view->reEvalScheme = 'tb';
            $this->view->reEvalShipmentIds = (new Application_Service_Evaluation())->getReEvaluatableShipmentIds('tb');
        }
        $this->view->tbConfig = Pt_Commons_SchemeConfig::get('tb');
    }
}
