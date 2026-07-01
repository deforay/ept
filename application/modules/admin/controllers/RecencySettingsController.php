<?php

class Admin_RecencySettingsController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
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
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $common = new Application_Service_Common();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            if (isset($params['recency']) && !empty($params['recency'])) {
                $recency = json_encode($params['recency']);
                $common->saveSchemeConfigByName($recency, 'recency');
            }

            // Settings changed: offer to re-evaluate shipments scored under the old config.
            $this->view->reEvalScheme = 'recency';
            $this->view->reEvalShipmentIds = (new Application_Service_Evaluation())->getReEvaluatableShipmentIds('recency');
        }
        $this->view->recencyConfig = Pt_Commons_SchemeConfig::get('recency');
    }
}
