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
        // Sender and the standing Cc/Bcc ride on every message, so the
        // message preview shows them alongside each recipient's own To/Cc.
        $mail = Application_Service_Common::getMailSettings();
        $this->view->mailSender = [
            'fromName'  => $mail['fromName'],
            'fromEmail' => $mail['fromEmail'],
            'cc'        => $mail['cc'],
            'bcc'       => $mail['bcc'],
        ];
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
        ];

        if (empty($data['shipments']) || empty($data['sendMail'])) {
            echo json_encode(['recipients' => [], 'invalid' => [], 'counts' => []]);
            return;
        }

        $participantService = new Application_Service_Participants();
        $resolved = $participantService->resolveMailRecipients($data);

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
        foreach ($resolved['recipients'] as $pt) {
            $role = $pt['role'] ?? '';
            $counts[$role] = ($counts[$role] ?? 0) + 1;

            $recipients[] = [
                'email'        => $pt['email'],
                'name'         => $pt['name'] ?? '',
                'role'         => $role,
                'country'      => $pt['country'] ?? '',
                'shipmentCode' => $pt['shipment_code'] ?? '',
                'cc'           => $pt['cc'] ?? [],
                // >1 when this inbox also appears as a Cc on other messages
                'copies'       => $inbox[strtolower($pt['email'])] ?? 1,
                'subject'      => Application_Service_Participants::mailMerge($subject, $pt),
                'body'         => Application_Service_Participants::mailMerge($message, $pt),
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
