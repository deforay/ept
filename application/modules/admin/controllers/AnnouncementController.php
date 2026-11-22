<?php

class Admin_AnnouncementController extends Zend_Controller_Action
{
    public function init()
    {

        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
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
        $ajaxContext->addActionContext('index', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'manageMenu';
    }

    public function composeAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $service = new Application_Service_Announcement();
            $service->composeNewAnnouncement($params);

            $subject = trim((string) ($params['subject'] ?? ''));
            $detail = $subject !== '' ? " - \"{$subject}\"" : '';
            $participantIds = array_values(array_filter(array_map('intval', (array) ($params['participants'] ?? []))));
            $labIds = [];
            if ($participantIds !== []) {
                $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                $labIds = $db->fetchCol(
                    $db->select()->from('participant', ['unique_identifier'])->where('participant_id IN (?)', $participantIds)->order('unique_identifier')
                );
            }
            Application_Model_DbTable_AuditLog::record("Composed announcement{$detail}", 'announcement', [
                'facts' => ['Participants' => count($participantIds)],
                'list' => ['title' => 'Participants', 'rows' => $labIds],
            ]);

            $this->redirect('/admin/announcement');
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        if (isset($_COOKIE['did']) && $_COOKIE['did'] != '' && $_COOKIE['did'] != null && $_COOKIE['did'] != 'NULL') {
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipmentDetails = $data = $shipmentService->getShipment($_COOKIE['did']);
            $this->view->schemeDetails = $scheme->getScheme($data['scheme_type']);
        }
        $participantService = new Application_Service_Participants();
        $this->view->participantCity    = $participantService->getUniqueCity();
        $this->view->participantState   = $participantService->getUniqueState();
        $this->view->participants       = $participantService->getAllActiveParticipants();
    }
}
