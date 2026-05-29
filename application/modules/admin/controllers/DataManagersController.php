<?php

class Admin_DataManagersController extends Zend_Controller_Action
{
    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if (!in_array('config-ept', $privileges) && !in_array('manage-participants', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-participants-names', 'html')
            ->addActionContext('reset-password', 'html')
            ->addActionContext('bulk-reset-password', 'html')
            ->addActionContext('bulk-reset-by-list', 'html')
            ->addActionContext('change-primary-email', 'html')
            ->addActionContext('save-password', 'html')
            ->addActionContext('check-dm-duplicate', 'html')
            ->addActionContext('export-ptcc', 'html')
            ->addActionContext('mapped-participants', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $clientsServices = new Application_Service_DataManagers();
            $clientsServices->getAllUsers($params);
        }
        if ($this->hasParam('ptcc')) {
            $this->view->ptcc = $this->_getParam('ptcc');
        }
        // Mapping filter is only useful when there's at least one active DM/PTCC
        // with no mapped participant — hide it otherwise to keep the toolbar clean.
        $dmType = (!empty($this->view->ptcc) && $this->view->ptcc == 1) ? 'ptcc' : 'manager';
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->view->unmappedDmCount = (int)$db->fetchOne(
            $db->select()
                ->from(['u' => 'data_manager'], new Zend_Db_Expr('COUNT(*)'))
                ->where('u.status = ?', 'active')
                ->where('u.data_manager_type = ?', $dmType)
                ->where('NOT EXISTS (SELECT 1 FROM participant_manager_map pmm WHERE pmm.dm_id = u.dm_id)')
        );
    }

    /* Returns the list of participants mapped to a data manager, rendered as a fragment
       to be inserted into a DataTables child row on /admin/data-managers. */
    public function mappedParticipantsAction()
    {
        $this->_helper->layout()->disableLayout();
        $dmId = (int)$this->_getParam('id');
        $this->view->dmId = $dmId;
        $this->view->mappedParticipants = [];
        if ($dmId > 0) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $select = $db->select()
                ->from(['pmm' => 'participant_manager_map'], [])
                ->join(['p' => 'participant'], 'p.participant_id = pmm.participant_id', ['participant_id', 'unique_identifier', 'first_name', 'last_name', 'institute_name', 'email', 'status'])
                ->joinLeft(['c' => 'countries'], 'c.id = p.country', ['iso_name'])
                ->where('pmm.dm_id = ?', $dmId)
                ->order(['p.institute_name', 'p.unique_identifier']);
            $this->view->mappedParticipants = $db->fetchAll($select);
        }
    }

    public function quickCreateAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        header('Content-Type: application/json');

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if (!$request->isPost()) {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'POST required.']);
            return;
        }

        $userService = new Application_Service_DataManagers();
        $result = $userService->quickCreateDataManager($request->getPost());
        if (!empty($result['error'])) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $result['error']]);
            return;
        }
        echo json_encode([
            'status' => 'success',
            'dm' => [
                'dm_id' => (int)$result['dm_id'],
                'label' => $result['label'],
            ],
        ]);
    }

    public function addAction()
    {
        $userService = new Application_Service_DataManagers();
        $commonService = new Application_Service_Common();
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        $participantService = new Application_Service_Participants();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $userService->addUser($params);
            if (isset($params['ptcc']) && $params['ptcc'] == 'yes') {
                $this->redirect('/admin/data-managers/index/ptcc/1');
            }
            $this->redirect('/admin/data-managers');
        } else {
            $this->view->participants = $participantService->getAllActiveParticipants();
        }
        if ($this->hasParam('ptcc')) {
            $this->view->ptcc = $this->_getParam('ptcc');
        }
        if ($this->hasParam('contact')) {
            $contact = new Application_Model_DbTable_ContactUs();
            $this->view->contact = $contact->getContact($this->_getParam('contact'));
        }
        $this->view->countriesList = $commonService->getcountriesList();
        $this->view->countries = $participantService->getParticipantCountriesList();
        $this->view->province = $commonService->getParticipantsProvinceList();
        $this->view->district = $commonService->getParticipantsDistrictList();
        $this->view->networksTier = $commonService->getAllnetwork();
        $this->view->affiliation = $commonService->getAllParticipantAffiliates();
        $this->view->institutes = $commonService->getAllInstitutes();
        $this->view->dmId = 0;
        $this->view->preSelectedParticipants = [];
        $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
        $this->view->passLength = $globalConfigDb->getValue('participant_login_password_length');
    }

    public function editAction()
    {
        $participantService = new Application_Service_Participants();
        $userService = new Application_Service_DataManagers();
        $commonService = new Application_Service_Common();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $userService->updateUser($params);
            if (isset($params['ptcc']) && $params['ptcc'] == 'yes') {
                $this->redirect('/admin/data-managers/index/ptcc/1');
            } else {
                $this->redirect('/admin/data-managers');
            }
        } else {
            if ($this->hasParam('id')) {
                $userId = (int) $this->_getParam('id');
                if ($this->hasParam('ptcc')) {
                    $this->view->ptcc = $this->_getParam('ptcc');
                    $this->view->countryList = $userService->getPtccCountryMap($userId, 'implode', true);
                }
                $this->view->rsUser = $userService->getUserInfoBySystemId($userId);
                $this->view->participants = $participantService->getAllActiveParticipants();
                $this->view->participantList = $participantService->getActiveParticipantDetails($userId);
                $this->view->countriesList = $commonService->getcountriesList();
                $this->view->provinceList = $commonService->getParticipantsProvinceList();
                $this->view->districtList = $commonService->getParticipantsDistrictList();
                $this->view->countries = $participantService->getParticipantCountriesList();
                $this->view->province = $commonService->getParticipantsProvinceList();
                $this->view->district = $commonService->getParticipantsDistrictList();
                $this->view->networksTier = $commonService->getAllnetwork();
                $this->view->affiliation = $commonService->getAllParticipantAffiliates();
                $this->view->institutes = $commonService->getAllInstitutes();
                $this->view->dmId = $userId;
                $this->view->preSelectedParticipants = array_map(function ($p) {
                    return (int)$p['participant_id'];
                }, $this->view->participantList ?: []);
                $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
                $this->view->passLength = $globalConfigDb->getValue('participant_login_password_length');
            }
        }
    }

    public function getParticipantsNamesAction()
    {
        $this->_helper->layout()->disableLayout();
        $participantService = new Application_Service_Participants();
        if ($this->hasParam('search')) {
            $search = $this->_getParam('search');
            $this->view->participants = $participantService->getParticipantSearch($search);
        }
    }

    public function resetPasswordAction()
    {
        $this->_helper->layout()->setLayout('modal');
        $userService = new Application_Service_DataManagers();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $loginUrl = rtrim((string) $conf->domain, '/') . '/auth/login';
        if ($request->isPost()) {
            $params = $request->getPost();
            if (empty($params['loginUrl'])) {
                $params['loginUrl'] = $loginUrl;
            }
            $this->view->result = $userService->resetPasswordFromAdmin($params);
        } elseif ($this->hasParam('id')) {
            $userId = (int) $this->_getParam('id');
            $this->view->user = $userService->getUserInfoBySystemId($userId);
            $targetEmail = $this->view->user['primary_email'] ?? '';
            if ($targetEmail !== '') {
                $this->view->recentActivity = $userService->getRecentAccountActivity($targetEmail, 6, 5);
            }
        }
        $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
        $this->view->passLength = $globalConfigDb->getValue('participant_login_password_length');
        $this->view->loginUrl = $loginUrl;
    }

    public function bulkResetPasswordAction()
    {
        $this->_helper->layout()->setLayout('modal');
        $userService = new Application_Service_DataManagers();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $loginUrl = rtrim((string) $conf->domain, '/') . '/auth/login';

        if ($request->isPost()) {
            $params = $request->getPost();
            if (empty($params['loginUrl'])) {
                $params['loginUrl'] = $loginUrl;
            }

            // Normalise and persist a payload file for the background worker.
            $rawIds = $params['dmIds'] ?? '';
            $dmIds = array_values(array_unique(array_filter(array_map('intval', is_array($rawIds) ? $rawIds : explode(',', (string) $rawIds)))));

            if (empty($dmIds)) {
                $this->view->result = ['queued' => 0, 'error' => 'No data managers selected.'];
                $this->view->loginUrl = $loginUrl;
                return;
            }

            $authNameSpace = new Zend_Session_Namespace('administrators');
            $payload = [
                'dmIds'              => $dmIds,
                'sendEmail'          => !empty($params['sendEmail']),
                'forcePasswordReset' => !empty($params['forcePasswordReset']),
                'emailCc'            => (string) ($params['emailCc'] ?? ''),
                'emailBcc'           => (string) ($params['emailBcc'] ?? ''),
                'loginUrl'           => $params['loginUrl'],
                'actorEmail'         => (string) ($authNameSpace->primary_email ?? ''),
                'actorRole'          => 'admin',
            ];

            $fileName = 'bulk-reset-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
            $payloadPath = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName;
            if (!is_dir(TEMP_UPLOAD_PATH)) {
                @mkdir(TEMP_UPLOAD_PATH, 0775, true);
            }
            file_put_contents($payloadPath, json_encode($payload));

            $scheduledDb = new Application_Model_DbTable_ScheduledJobs();
            $scheduledDb->insert([
                'job'          => "bulk-reset-passwords.php -f '$fileName'",
                'requested_on' => Pt_Commons_DateUtility::getCurrentDateTime(),
                'requested_by' => $authNameSpace->admin_id ?? null,
                'status'       => 'pending',
            ]);

            $this->view->result = [
                'queued'  => count($dmIds),
                'emailed' => !empty($params['sendEmail']),
            ];
        } else {
            $idsParam = (string) $this->_getParam('ids', '');
            $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $idsParam)))));
            $this->view->dms = $ids ? $userService->getUsersByIds($ids) : [];
        }
        $this->view->loginUrl = $loginUrl;
    }

    public function bulkResetByListAction()
    {
        $this->_helper->layout()->setLayout('modal');
        $userService = new Application_Service_DataManagers();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $loginUrl = rtrim((string) $conf->domain, '/') . '/auth/login';

        $this->view->loginUrl = $loginUrl;
        $this->view->step = 'paste';
        $this->view->pastedText = '';
        $this->view->matched = [];
        $this->view->unresolved = [];

        if (!$request->isPost()) {
            return;
        }

        $params = $request->getPost();
        $step = (string) ($params['step'] ?? 'resolve');
        $pasted = (string) ($params['identifiers'] ?? '');
        $this->view->pastedText = $pasted;

        $resolved = $userService->resolveDmIdentifiers($this->tokenizePastedIdentifiers($pasted));
        $this->view->matched = $resolved['matched'];
        $this->view->unresolved = $resolved['unresolved'];

        if ($step === 'queue' && !empty($resolved['matched'])) {
            // Reuse the existing queue path: write payload + insert scheduled_jobs.
            $dmIds = array_map(fn ($r) => (int) $r['dm_id'], $resolved['matched']);

            $authNameSpace = new Zend_Session_Namespace('administrators');
            $payload = [
                'dmIds'              => $dmIds,
                'sendEmail'          => !empty($params['sendEmail']),
                'forcePasswordReset' => !empty($params['forcePasswordReset']),
                'emailCc'            => (string) ($params['emailCc'] ?? ''),
                'emailBcc'           => (string) ($params['emailBcc'] ?? ''),
                'loginUrl'           => $loginUrl,
                'actorEmail'         => (string) ($authNameSpace->primary_email ?? ''),
                'actorRole'          => 'admin',
            ];

            $fileName = 'bulk-reset-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
            $payloadPath = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName;
            if (!is_dir(TEMP_UPLOAD_PATH)) {
                @mkdir(TEMP_UPLOAD_PATH, 0775, true);
            }
            file_put_contents($payloadPath, json_encode($payload));

            $scheduledDb = new Application_Model_DbTable_ScheduledJobs();
            $scheduledDb->insert([
                'job'          => "bulk-reset-passwords.php -f '$fileName'",
                'requested_on' => Pt_Commons_DateUtility::getCurrentDateTime(),
                'requested_by' => $authNameSpace->admin_id ?? null,
                'status'       => 'pending',
            ]);

            $this->view->result = [
                'queued'  => count($dmIds),
                'emailed' => !empty($params['sendEmail']),
            ];
            $this->view->step = 'queued';
            return;
        }

        $this->view->step = 'resolved';
    }

    // Excel pastes are TSV with CSV-style quoting: a cell containing a newline
    // arrives as "value\n", so naive splitting on \r\n leaves a stray quote
    // alone on its own line. Parse with fgetcsv (tab delimiter, " enclosure,
    // no escape char), then split each cell on comma/semicolon for users who
    // paste comma-separated lists inline.
    private function tokenizePastedIdentifiers(string $pasted): array
    {
        $lines = [];
        $tmp = fopen('php://temp', 'r+');
        if ($tmp === false) {
            return preg_split('/[\r\n,;\t]+/', $pasted) ?: [];
        }
        fwrite($tmp, $pasted);
        rewind($tmp);
        while (($row = fgetcsv($tmp, 0, "\t", '"', '')) !== false) {
            foreach ($row as $cell) {
                foreach (preg_split('/[,;]+/', (string) $cell) ?: [] as $piece) {
                    $lines[] = $piece;
                }
            }
        }
        fclose($tmp);
        return $lines;
    }

    public function changePrimaryEmailAction()
    {
        $this->_helper->layout()->setLayout('modal');
        $userService = new Application_Service_DataManagers();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $this->view->result = $userService->changePrimaryEmailFromAdmin($request->getPost());
        } elseif ($this->hasParam('id')) {
            $userId = (int) $this->_getParam('id');
            $this->view->user = $userService->getUserInfoBySystemId($userId);
        }
    }

    /* public function savePasswordAction()
    {
        $this->_helper->layout()->setLayout('modal');
        $userService = new Application_Service_DataManagers();
        /** @var Zend_Controller_Request_Http $request
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->view->result = $userService->resetPasswordFromAdmin($params);
        }
    } */

    public function checkDmDuplicateAction() // This action created for checking ptcc and actual dm replacement using primary email
    {
        $this->_helper->layout()->disableLayout();
        $userService = new Application_Service_DataManagers();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->view->result = $userService->checkSystemDuplicate($params);
        }
    }
    public function exportPtccAction()
    {
        $this->_helper->layout()->disableLayout();
        $userService = new Application_Service_DataManagers();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->view->result = $userService->exportPTCCDetails($params);
        }
    }

    public function bulkImportPtccAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $userService = new Application_Service_DataManagers();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->view->response = $userService->uploadBulkDatamanager($params);
            $this->redirect('/admin/data-managers/index/ptcc/1');
        }
    }
}
