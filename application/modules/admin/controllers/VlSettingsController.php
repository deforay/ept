<?php

class Admin_VlSettingsController extends Zend_Controller_Action
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
            $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
            if ($request->isPost()) {
                $params = $this->getAllParams();
                if (isset($params['vl']) && !empty($params['vl'])) {
                    $vl = json_encode($params['vl']);
                    $common->saveSchemeConfigByName($vl, 'vl');
                }
                $auditDb = new Application_Model_DbTable_AuditLog();
                $auditDb->addNewAuditLog("Updated VL Settings", "config");
            }
            $this->view->vlConfig = Pt_Commons_SchemeConfig::get('vl');
        } catch (Exception $exc) {

            error_log("VL-SETTINGS-" . $exc->getMessage());
            error_log($exc->getTraceAsString());
            return "";
        }


        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
    }
}
