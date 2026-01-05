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
                return null;
            } else {
                $this->redirect('/admin');
            }
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
                $common->saveConfigByName($tb, 'tb_configuration');
            }
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Updated TB Settings", "config");
        }
        $this->view->tbConfig = $common->getConfig('tb_configuration');
    }
}
