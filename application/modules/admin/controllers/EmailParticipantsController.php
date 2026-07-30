<?php

class Admin_EmailParticipantsController extends Zend_Controller_Action
{
    public function init()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $this->_helper->layout()->pageName = 'configMenu';
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext
            ->addActionContext('get-mail-template', 'html')
            ->initContext();
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
        $participantService = new Application_Service_Participants();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost();
            $participantService->sendParticipantEmail($data);
            $subject = trim((string) ($data['subject'] ?? ''));
            $shipments = isset($data['shipments']) ? array_filter((array) $data['shipments']) : [];
            $detail = $subject !== '' ? " — \"$subject\"" : '';
            if (!empty($shipments)) {
                $detail .= ' (shipments: ' . implode(', ', $shipments) . ')';
            }
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog('Sent email to participants' . $detail, 'config');
        }
        $shipment = new Application_Service_Shipments();
        if ($this->hasParam('id')) {
            $this->view->distributionId = $this->_getParam('id');
        }
        if ($this->hasParam('sid')) {
            $this->view->shipmentId = base64_decode($this->_getParam('sid'));
        }
        $common = new Application_Service_Common();
        $this->view->templates = $common->getAllEmailTemplateDetails();
        $this->view->shipment = $shipment->getAllShipmentCode();
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }

    /**
     * Dry run for the Send button: returns the exact recipient list that
     * sendParticipantEmail() would queue for the chosen shipments/audiences,
     * so the whole list can be eyeballed before anything leaves the building.
     */
    public function previewRecipientsAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        header('Content-Type: application/json');

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if (!$request->isPost()) {
            echo json_encode(['recipients' => [], 'invalid' => [], 'counts' => []]);
            return;
        }

        $data = [
            'shipments' => array_filter((array) $this->_getParam('shipments', [])),
            'sendMail'  => array_filter((array) $this->_getParam('sendMail', [])),
            'skipEmail' => $this->_getParam('skipEmail'),
        ];

        if (empty($data['shipments']) || empty($data['sendMail'])) {
            echo json_encode(['recipients' => [], 'invalid' => [], 'counts' => []]);
            return;
        }

        $participantService = new Application_Service_Participants();
        $resolved = $participantService->resolveMailRecipients($data);
        // Roles across every audience, so we can flag an address that a
        // separate send (e.g. the PTCC one) would also reach.
        $roleMap = $participantService->getMailRecipientRoleMap($data);

        $subject = (string) $this->_getParam('subject', '');
        $message = (string) $this->_getParam('message', '');

        // How many messages land in each inbox, counting To and Cc alike. A
        // manager who runs one lab and oversees others legitimately appears on
        // several messages, and that has to be visible before sending.
        $inbox = [];
        foreach ($resolved['recipients'] as $pt) {
            foreach (array_merge([$pt['email']], $pt['cc'] ?? []) as $addr) {
                $addr = strtolower($addr);
                $inbox[$addr] = ($inbox[$addr] ?? 0) + 1;
            }
        }
        $multiple = array_filter($inbox, fn ($n) => $n > 1);
        arsort($multiple);

        $recipients = [];
        $counts = [];
        foreach ($resolved['recipients'] as $key => $pt) {
            $role = $pt['role'] ?? '';
            $counts[$role] = ($counts[$role] ?? 0) + 1;

            [$search, $replace] = Application_Service_Participants::mailMergeFields($pt);
            // Only audiences OUTSIDE this selection are worth flagging. An
            // address that is both participant and data manager is already
            // de-duplicated into a single email by this send; one that is also
            // a PTCC would get a second copy from the separate PTCC send.
            $otherRoles = array_values(array_diff($roleMap[$key] ?? [], $data['sendMail']));

            $recipients[] = [
                'email'        => $pt['email'],
                'name'         => $pt['name'] ?? '',
                'role'         => $role,
                'country'      => $pt['country'] ?? '',
                'shipmentCode' => $pt['shipment_code'] ?? '',
                'cc'           => $pt['cc'] ?? [],
                'otherRoles'   => $otherRoles,
                // >1 when this inbox also appears as a Cc on other messages
                'copies'       => $inbox[strtolower($pt['email'])] ?? 1,
                'subject'      => str_replace($search, $replace, $subject),
                'body'         => str_replace($search, $replace, $message),
            ];
        }

        $multipleList = [];
        foreach ($multiple as $addr => $n) {
            $multipleList[] = ['email' => $addr, 'copies' => $n];
        }

        echo json_encode([
            'recipients' => $recipients,
            'invalid'    => $resolved['invalid'],
            'counts'     => $counts,
            'multiple'   => $multipleList,
            'total'      => count($recipients),
        ]);
    }

    public function getMailTemplateAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $purpose = $request->getParam('mailPurpose');
            $common = new Application_Service_Common();
            $this->view->result = $common->getEmailTemplate($purpose);
        }
    }

}
