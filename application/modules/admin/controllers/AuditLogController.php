<?php

class Admin_AuditLogController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('analyze-generate-reports', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                // init() returning does not abort ZF1 dispatch; halt so the
                // action never runs for unauthorized XHR callers.
                $this->getResponse()->setHttpResponseCode(403)->sendResponse();
                exit;
            }
            $this->redirect('/admin');
            return;
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('feed', 'json')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        $systemAdmin = new Application_Service_SystemAdmin();
        $this->view->systemAdmin = $systemAdmin->getSystemAllAdmin();

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $contextRows = $db->fetchCol(
            $db->select()
                ->from('audit_log', ['type'])
                ->where("type IS NOT NULL AND type != ''")
                ->group('type')
                ->order('type ASC')
        );
        $this->view->contextTypes = $contextRows;
    }

    public function feedAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $params = $this->getAllParams();
        $auditLog = new Application_Model_DbTable_AuditLog();
        $payload = $auditLog->fetchAuditLogFeed($params);

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode($payload));
    }
}
