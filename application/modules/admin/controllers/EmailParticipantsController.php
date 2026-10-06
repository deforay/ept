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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $this->saveEmail($request->getPost());
            return;
        }

        $shipment = new Application_Service_Shipments();
        if ($this->hasParam('id')) {
            $this->view->distributionId = $this->_getParam('id');
        }
        if ($this->hasParam('sid')) {
            $this->view->shipmentId = base64_decode($this->_getParam('sid'));
        }
        // Opening a scheduled email to change it. Only one still waiting can be
        // edited; one already queued has its messages written.
        $editId = (int) $this->_getParam('edit', 0);
        if ($editId > 0) {
            $email = (new Application_Model_DbTable_EmailParticipants())->fetchEmail($editId);
            if ($email !== null && in_array($email['status'], Application_Model_DbTable_EmailParticipants::EDITABLE_STATUSES, true) && empty($email['dispatched_at'])) {
                $this->view->editEmail = $email;
            } else {
                $alertMsg = new Zend_Session_Namespace('alertSpace');
                $alertMsg->message = $this->view->translate->_('This email has already been queued or cancelled, so it can no longer be edited.');
                $this->redirect('/admin/email-participants?tab=scheduled');
                return;
            }
        }
        $this->view->tab = in_array($this->_getParam('tab'), ['scheduled', 'history'], true) ? $this->_getParam('tab') : 'compose';
        $this->view->timezone = date_default_timezone_get();

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
     * The compose form's three outcomes: send now, schedule, or save changes to
     * a scheduled email (emailId set). Redirects afterwards, so a reload of the
     * result page can never send the email a second time.
     */
    private function saveEmail(array $post): void
    {
        $t = $this->view->translate;
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $adminSession = new Zend_Session_Namespace('administrators');
        $adminEmail = (string) $adminSession->primary_email;
        $participantService = new Application_Service_Participants();
        $emailParticipantDb = new Application_Model_DbTable_EmailParticipants();
        $auditDb = new Application_Model_DbTable_AuditLog();

        $data = [
            'subject'   => trim((string) ($post['subject'] ?? '')),
            'message'   => (string) ($post['message'] ?? ''),
            'shipments' => array_values(array_filter((array) ($post['shipments'] ?? []), 'ctype_digit')),
            'sendMail'  => array_values(array_intersect((array) ($post['sendMail'] ?? []), ['participant', 'datamanager', 'ptcc'])),
        ];
        $editId = (int) ($post['emailId'] ?? 0);
        $schedule = ($post['sendMode'] ?? 'now') === 'schedule';
        $back = $editId > 0 ? "/admin/email-participants?edit={$editId}" : '/admin/email-participants';

        if ($data['subject'] === '' || trim(strip_tags($data['message'])) === '' || empty($data['shipments']) || empty($data['sendMail'])) {
            $alertMsg->message = $t->_('Select shipments and who gets the email, and enter a subject and message.');
            $this->redirect($back);
            return;
        }

        $scheduledAt = null;
        if ($schedule) {
            $scheduledAt = self::parseScheduledAt((string) ($post['scheduledAt'] ?? ''));
            if ($scheduledAt === null) {
                $alertMsg->message = $t->_('Choose a send time in the future.');
                $this->redirect($back);
                return;
            }
        }

        $detail = " — \"{$data['subject']}\" (shipments: " . implode(', ', $data['shipments']) . ')';

        if ($editId > 0) {
            // Send now from the edit screen keeps the time it had, then sends
            $saved = $emailParticipantDb->updateScheduledEmail(
                $editId,
                $data,
                $scheduledAt ?? (string) ($emailParticipantDb->fetchEmail($editId)['scheduled_at'] ?? date('Y-m-d H:i:s')),
                $adminEmail
            );
            if (!$saved) {
                $alertMsg->message = $t->_('This email has already been queued or cancelled, so your changes were not saved.');
                $this->redirect('/admin/email-participants?tab=scheduled');
                return;
            }
            $auditDb->addNewAuditLog("Edited scheduled email #{$editId} to participants" . $detail, 'config');
            if ($schedule) {
                $alertMsg->message = sprintf($t->_('Changes saved. The email will be sent at %s.'), self::formatScheduledAt($scheduledAt));
            } else {
                $result = $participantService->changeParticipantEmailState($editId, 'send-now', $adminEmail);
                $alertMsg->message = $this->describeOutcome($result);
            }
            $this->redirect('/admin/email-participants?tab=scheduled');
            return;
        }

        $result = $participantService->saveParticipantEmail($data, $scheduledAt, $adminEmail);
        if ($schedule) {
            $auditDb->addNewAuditLog("Scheduled email #{$result['id']} to participants for {$scheduledAt}" . $detail, 'config');
            $alertMsg->message = sprintf($t->_('Email scheduled. It will be sent at %s.'), self::formatScheduledAt($scheduledAt));
            $this->redirect('/admin/email-participants?tab=scheduled');
            return;
        }

        $auditDb->addNewAuditLog('Sent email to participants' . $detail, 'config');
        $message = $result['queued'] > 0
            ? sprintf($t->_('Emails queued for sending: %s.'), number_format($result['queued']))
            : $t->_('Nothing was queued: no recipients matched.');
        if (!empty($result['invalid'])) {
            $message .= ' ' . sprintf($t->_('Invalid addresses, skipped: %s'), implode(', ', $result['invalid']));
        }
        $alertMsg->message = $message;
        $this->redirect('/admin/email-participants?tab=history');
    }

    /**
     * Reads the datetime-local value (2026-10-06T14:30) in the application
     * timezone. Null unless it parses and is still in the future.
     */
    private static function parseScheduledAt(string $value): ?string
    {
        $when = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', trim($value));
        if ($when === false || $when->getTimestamp() <= time()) {
            return null;
        }
        return $when->format('Y-m-d H:i:s');
    }

    private static function formatScheduledAt(string $value): string
    {
        return Pt_Commons_DateUtility::humanReadableDateFormat($value) . ' ' . substr($value, 11, 5)
            . ' (' . date_default_timezone_get() . ')';
    }

    /** Words a changeParticipantEmailState() outcome for the admin */
    private function describeOutcome(array $result): string
    {
        $t = $this->view->translate;
        $count = number_format((int) ($result['count'] ?? 0));
        return match ($result['code']) {
            'paused'             => $t->_('Paused. It will not be sent until you resume it.'),
            'paused_held'        => sprintf($t->_('Paused. Unsent messages held: %s.'), $count),
            'resumed'            => $t->_('Resumed. It will be sent at its scheduled time.'),
            'resumed_released'   => sprintf($t->_('Resumed. Held messages queued again: %s.'), $count),
            'cancelled'          => $t->_('Cancelled. It will not be sent.'),
            'cancelled_messages' => sprintf($t->_('Cancelled. Unsent messages that will not be sent: %s.'), $count),
            'queued'             => sprintf($t->_('Emails queued for sending: %s.'), $count),
            'no_recipients'      => $t->_('Nothing was queued: no recipients matched.'),
            'missing'            => $t->_('This email no longer exists.'),
            'stale'              => $t->_('This email changed since the page loaded. Reload the list and try again.'),
            default              => $t->_('That action is not available.'),
        };
    }

    /** Rows for the Scheduled and History tabs */
    public function listAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        header('Content-Type: application/json');

        $view = $this->_getParam('view') === 'history' ? 'history' : 'scheduled';
        $rows = (new Application_Model_DbTable_EmailParticipants())->fetchEmailList($view);
        $roles = ['participant', 'datamanager', 'ptcc'];

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id'            => (int) $row['id'],
                'subject'       => (string) $row['subject'],
                'shipments'     => $row['shipment_codes'],
                'audiences'     => array_values(array_intersect($roles, explode(',', (string) $row['receivers']))),
                'status'        => (string) $row['status'],
                'inFlight'      => !empty($row['dispatched_at']),
                'editable'      => in_array($row['status'], Application_Model_DbTable_EmailParticipants::EDITABLE_STATUSES, true) && empty($row['dispatched_at']),
                'scheduledAt'   => $row['scheduled_at'],
                'dispatchedAt'  => $row['dispatched_at'],
                'createdAt'     => $row['date_initiated'],
                'createdBy'     => (string) $row['initiated_by'],
                'updatedBy'     => (string) $row['updated_by'],
                'queuedCount'   => $row['queued_count'] !== null ? (int) $row['queued_count'] : null,
                'failureReason' => (string) $row['failure_reason'],
                'progress'      => $row['progress'],
            ];
        }
        echo json_encode(['rows' => $out, 'now' => date('Y-m-d H:i:s')]);
    }

    /** Pause, resume, cancel or send now, from the Scheduled tab */
    public function changeStateAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        header('Content-Type: application/json');

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $id = (int) $this->_getParam('id', 0);
        $action = (string) $this->_getParam('do', '');
        if (!$request->isPost() || $id <= 0 || !in_array($action, ['pause', 'resume', 'cancel', 'send-now'], true)) {
            echo json_encode(['ok' => false, 'message' => $this->describeOutcome(['code' => 'unknown_action'])]);
            return;
        }

        $adminSession = new Zend_Session_Namespace('administrators');
        $result = (new Application_Service_Participants())->changeParticipantEmailState($id, $action, (string) $adminSession->primary_email);
        if ($result['ok']) {
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Email to participants #{$id}: {$action} ({$result['code']})", 'config');
        }
        echo json_encode(['ok' => $result['ok'], 'message' => $this->describeOutcome($result)]);
    }

    /**
     * Dry run for the Send button: returns the exact recipient list that
     * queueParticipantEmail() would queue for the chosen shipments/audiences,
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
