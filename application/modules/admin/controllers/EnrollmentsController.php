<?php

class Admin_EnrollmentsController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges) && !in_array('manage-participants', $privileges)) {
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
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            // The "By participant" tab's DataTable
            $params = $this->getAllParams();
            $participantService = new Application_Service_Participants();
            $participantService->getAllEnrollments($params);
            return;
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        $this->view->schemeCount = $scheme->countEnrollmentSchemes();
        $enrollments = new Application_Model_DbTable_Enrollments();
        $this->view->lists = $enrollments->getEnrollmentLists();
    }

    /** The list editor: one scheme, one list. Saving makes the list match the right-hand side. */
    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $participants = new Application_Service_Participants();
            $result = $participants->enrollParticipants($params);
            if ($request->isXmlHttpRequest()) {
                $alertMsg = new Zend_Session_Namespace('alertSpace');
                $alertMsg->message = null;
                $this->_helper->json([
                    'success' => !empty($result['ok']),
                    'added' => (int) ($result['added'] ?? 0),
                    'removed' => (int) ($result['removed'] ?? 0),
                    'error' => $result['error'] ?? null,
                    'trace_id' => $result['trace_id'] ?? null,
                ]);
                return;
            }
            $this->redirect('/admin/enrollments');
        }

        $schemeId = (string) $this->_getParam('scheme', '');
        $schemeService = new Application_Service_Schemes();
        $scheme = null;
        foreach ($schemeService->getAllSchemes() as $row) {
            if ((string) $row['scheme_id'] === $schemeId) {
                $scheme = $row;
            }
        }
        if ($scheme === null) {
            $this->redirect('/admin/enrollments');
            return;
        }
        $this->view->scheme = $scheme;
        $this->view->listName = Application_Model_DbTable_Enrollments::normalizeListName($this->_getParam('list', ''));

        $participantService = new Application_Service_Participants();
        $commonService = new Application_Service_Common();
        $this->view->countries = $participantService->getParticipantCountriesList();
        $this->view->province = $commonService->getParticipantsProvinceList();
        $this->view->district = $commonService->getParticipantsDistrictList();
        $this->view->networksTier = $commonService->getAllnetwork();
        $this->view->affiliation = $commonService->getAllParticipantAffiliates();
        $this->view->institutes = $commonService->getAllInstitutes();
    }

    /** JSON rows for the list editor. */
    public function listItemsAction()
    {
        $request = $this->getRequest();
        $enrollments = new Application_Model_DbTable_Enrollments();
        $this->_helper->json($enrollments->getListEditorItems(
            (string) $request->getParam('scheme', ''),
            (string) $request->getParam('list', ''),
            [
                'country' => (string) $request->getParam('country', ''),
                'province' => (string) $request->getParam('province', ''),
                'district' => (string) $request->getParam('district', ''),
                'institute' => (string) $request->getParam('institute', ''),
                'network' => (string) $request->getParam('network', ''),
                'affiliation' => (string) $request->getParam('affiliation', ''),
            ]
        ));
    }

    /** Builds the list as an Excel file and returns its name under /temporary. */
    public function exportListAction()
    {
        $request = $this->getRequest();
        $enrollments = new Application_Model_DbTable_Enrollments();
        $this->_helper->json(['file' => $enrollments->exportList(
            (string) $request->getParam('scheme', ''),
            (string) $request->getParam('list', '')
        )]);
    }

    /** Archive, reactivate, rename, merge or delete one list, then reload the lists. */
    public function listActionAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if (!$request->isPost()) {
            $this->redirect('/admin/enrollments');
            return;
        }
        $schemeId = (string) $request->getPost('scheme', '');
        $listName = (string) $request->getPost('list', '');
        $enrollments = new Application_Model_DbTable_Enrollments();
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        try {
            switch ($request->getPost('op')) {
                case 'archive':
                    $enrollments->setListArchived($schemeId, $listName, true);
                    $alertMsg->message = 'List archived. Its participants are still enrolled.';
                    break;
                case 'reactivate':
                    $enrollments->setListArchived($schemeId, $listName, false);
                    $alertMsg->message = 'List reactivated.';
                    break;
                case 'rename':
                    $newName = (string) $request->getPost('newName', '');
                    if ($enrollments->renameList($schemeId, $listName, $newName)) {
                        $alertMsg->message = 'List renamed.';
                        // Renamed from the list editor: go back to it under the new name.
                        if ($request->getPost('returnTo') === 'edit') {
                            $this->redirect('/admin/enrollments/add/scheme/' . rawurlencode($schemeId) . '?list=' . rawurlencode(trim($newName)));
                            return;
                        }
                    } else {
                        $alertMsg->message = 'This scheme already has a list with that name. Use Merge to combine the two lists.';
                    }
                    break;
                case 'merge':
                    $enrollments->mergeList($schemeId, $listName, (string) $request->getPost('into', ''));
                    $alertMsg->message = 'Lists merged.';
                    break;
                case 'delete':
                    // The admin types the list name to confirm.
                    if (trim((string) $request->getPost('confirmName', '')) !== trim($listName)) {
                        $alertMsg->message = 'The name did not match, so the list was not deleted.';
                        break;
                    }
                    $enrollments->deleteList($schemeId, $listName);
                    $alertMsg->message = 'List deleted.';
                    break;
            }
        } catch (InvalidArgumentException $e) {
            $alertMsg->message = 'The Default list cannot be renamed, merged, archived or deleted.';
        }
        $this->redirect('/admin/enrollments');
    }

    /** Gives a no-scheme list a scheme, then reloads the lists. */
    public function assignSchemeAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeId = (string) $request->getPost('scheme', '');
        if ($request->isPost() && $schemeId !== '' && $this->schemeName($schemeId) !== $schemeId) {
            $enrollments = new Application_Model_DbTable_Enrollments();
            $enrollments->assignSchemeToList((string) $request->getPost('list', ''), $schemeId);
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = 'The list now belongs to the scheme and can be edited.';
        }
        $this->redirect('/admin/enrollments');
    }

    /** The enrollment import template, generated in the admin's language. */
    public function bulkEnrollmentTemplateAction()
    {
        Pt_Commons_ImportTemplate::send(Pt_Commons_ImportTemplate::ENROLLMENT);
    }

    // Upload, then Review (nothing saved), then Done, like the participant and PTCC imports.
    public function bulkEnrollmentAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $participantService = new Application_Service_Participants();

        $feedback = new Zend_Session_Namespace('bulkImportFeedback');
        if (!empty($feedback->mismatches)) {
            $this->view->validationMismatches = $feedback->mismatches;
            unset($feedback->mismatches);
        }

        if ($request->isPost()) {
            $reviewToken = (string) $request->getPost('reviewToken', '');
            if ($reviewToken !== '') {
                $result = $participantService->importReviewedBulkEnrollment($reviewToken);
                if (!$result) {
                    $this->redirect('/admin/enrollments/bulk-enrollment');
                    return;
                }
                $this->view->response = $result;
                $this->view->schemeName = $this->schemeName((string) $result['options']['scheme']);
                $this->_helper->viewRenderer->setScriptAction('bulk-enrollment-done');
                return;
            }

            $result = $participantService->previewBulkEnrollment($request->getPost());
            if (is_array($result) && !empty($result['validation_error'])) {
                $feedback->mismatches = $result['mismatches'];
                $this->redirect('/admin/enrollments/bulk-enrollment');
            } elseif (!$result) {
                $this->redirect('/admin/enrollments/bulk-enrollment');
            } else {
                $this->view->response = $result;
                $this->view->schemeName = $this->schemeName((string) $result['options']['scheme']);
                $this->_helper->viewRenderer->setScriptAction('bulk-enrollment-review');
            }
            return;
        }

        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        $enrollments = new Application_Model_DbTable_Enrollments();
        $this->view->lists = $enrollments->getEnrollmentLists();
        $this->view->preselectedScheme = (string) $this->_getParam('scheme', '');
        $this->view->preselectedList = (string) $this->_getParam('list', '');
    }

    private function schemeName(string $schemeId): string
    {
        $scheme = new Application_Service_Schemes();
        foreach ($scheme->getAllSchemes() as $row) {
            if ((string) $row['scheme_id'] === $schemeId) {
                return (string) $row['scheme_name'];
            }
        }
        return $schemeId;
    }
}
