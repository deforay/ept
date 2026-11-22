<?php

class Admin_HomeConfigController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext
            ->addActionContext('get-html-template-by-section', 'html')
            ->addActionContext('get-sections-list', 'html')
            ->initContext();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
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
        try {
            $homeSection = new Application_Service_HomeSection();
            $common = new Application_Service_Common();
            $globalConfigDb = new Application_Model_DbTable_GlobalConfig();

            /** @var Zend_Controller_Request_Http $request */
            $request = $this->getRequest();
            if ($request->isPost()) {
                $params = $this->getAllParams();
                $before = $this->homeAuditFields();

                // Handle logo deletions
                $logosDir = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logos';
                if (!is_dir($logosDir)) {
                    mkdir($logosDir, 0777, true);
                }
                if (isset($params['delete_home_left_logo']) && !empty($params['delete_home_left_logo'])) {
                    $logoPath = $logosDir . DIRECTORY_SEPARATOR . $params['delete_home_left_logo'];
                    if (file_exists($logoPath)) {
                        unlink($logoPath);
                    }
                    $globalConfigDb->update(['value' => null], "name = 'home_left_logo'");
                }
                if (isset($params['delete_home_right_logo']) && !empty($params['delete_home_right_logo'])) {
                    $logoPath = $logosDir . DIRECTORY_SEPARATOR . $params['delete_home_right_logo'];
                    if (file_exists($logoPath)) {
                        unlink($logoPath);
                    }
                    $globalConfigDb->update(['value' => null], "name = 'home_right_logo'");
                }

                // Handle logo uploads
                foreach (['home_left_logo', 'home_right_logo'] as $field) {
                    if (isset($_FILES[$field]) && !empty($_FILES[$field]['name'])) {
                        $fileNameSanitized = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES[$field]['name']);
                        $fileNameSanitized = str_replace(' ', '-', $fileNameSanitized);
                        $extension = strtolower(pathinfo($logosDir . DIRECTORY_SEPARATOR . $fileNameSanitized, PATHINFO_EXTENSION));
                        $fileName = Pt_Commons_MiscUtility::generateRandomString(4) . '.' . $extension;
                        if (move_uploaded_file($_FILES[$field]['tmp_name'], $logosDir . DIRECTORY_SEPARATOR . $fileName)) {
                            $globalConfigDb->update(['value' => $fileName], ['name = ?' => $field]);
                        }
                    }
                }

                // Handle home config
                if (isset($params['home']) && !empty($params['home'])) {
                    $home = json_encode($params['home']);
                    $common->saveConfigByName($home, 'home');
                }

                // Handle FAQ
                $faqResponse = [];
                foreach ($params['faqQuestions'] as $key => $faq) {
                    if (!empty(trim($faq))) {
                        $faqResponse[$faq] = $params['faqAnswers'][$key] ?? '';
                    }
                }
                $globalConfigDb->update(['value' => json_encode($faqResponse, true)], "name = 'faqs'");

                // Handle home banner upload
                $common->updateHomeBanner($params);

                $changedSections = [];
                if (!empty($params['delete_home_left_logo']) || !empty($_FILES['home_left_logo']['name'])) {
                    $changedSections[] = 'left logo';
                }
                if (!empty($params['delete_home_right_logo']) || !empty($_FILES['home_right_logo']['name'])) {
                    $changedSections[] = 'right logo';
                }
                if (!empty($params['home'])) {
                    $changedSections[] = 'home content';
                }
                if (!empty($params['faqQuestions'])) {
                    $changedSections[] = 'FAQs';
                }
                $detail = empty($changedSections) ? '' : ' - ' . implode(', ', $changedSections);
                $this->recordHomeConfigUpdate($detail, $before);
            }

            $this->view->home = json_decode($common->getConfig('home'));
            $this->view->faq = json_decode($common->getConfig('faqs'));
            $this->view->home_left_logo = $common->getConfig('home_left_logo');
            $this->view->home_right_logo = $common->getConfig('home_right_logo');
            $this->view->banner = $common->getHomeBannerDetails();

            $this->view->sections = $homeSection->getAllHomeSection();

            $this->view->htmlHomePage = $homeSection->getActiveHtmlHomePage();
        } catch (\Throwable $exc) {
            Pt_Commons_LoggerUtility::logError('Failed to save home config: ' . $exc->getMessage(), [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
        }
    }

    public function getHtmlTemplateBySectionAction()
    {
        $homeSection = new Application_Service_HomeSection();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $section = $request->getParam('section');
            $this->view->result = $homeSection->getActiveHtmlHomePage($section);
        }
    }

    public function getSectionsListAction()
    {
        $homeSection = new Application_Service_HomeSection();
        $this->_helper->layout()->disableLayout();
        if ($this->hasParam('search')) {
            $section = $this->_getParam('search');
            $this->view->search = $section;
            $this->view->section = $homeSection->getActiveHtmlHomePage($section);
        }
    }

    private const HOME_LABELS = [
        'home.heading1' => 'Line 1 (Main Title)',
        'home.heading2' => 'Line 2 (Subtitle)',
        'home.heading3' => 'Line 3 (Description)',
        'home.videoUrl' => 'Video URL',
        'home.title' => 'Page Title',
        'home.additionalLink' => 'Additional Link URL',
        'home.additionalLinkText' => 'Additional Link Text',
        'home.icon1' => 'Resource Section 1 › Icon',
        'home.subHeading1' => 'Resource Section 1 › Section Heading',
        'home.icon2' => 'Resource Section 2 › Icon',
        'home.subHeading2' => 'Resource Section 2 › Section Heading',
        'home.icon3' => 'Resource Section 3 › Icon',
        'home.subHeading3' => 'Resource Section 3 › Section Heading',
        'home_left_logo' => 'Logo 1 (Left)',
        'home_right_logo' => 'Logo 2 (Right)',
    ];

    /**
     * The saved home page settings, flattened to the fields the form edits.
     *
     * @return array<string, string>|null
     */
    private function homeAuditFields(): ?array
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $config = $db->fetchPairs(
                $db->select()
                    ->from('global_config', ['name', 'value'])
                    ->where('name IN (?)', ['home', 'faqs', 'home_left_logo', 'home_right_logo'])
            );
        } catch (Throwable $e) {
            return null;
        }
        $fields = [];
        $home = json_decode((string) ($config['home'] ?? ''), true);
        foreach (array_keys(self::HOME_LABELS) as $field) {
            if (str_starts_with($field, 'home.')) {
                $value = is_array($home) ? ($home[substr($field, 5)] ?? '') : '';
                $fields[$field] = is_scalar($value) ? (string) $value : '';
            } else {
                $fields[$field] = (string) ($config[$field] ?? '');
            }
        }
        $faqs = json_decode((string) ($config['faqs'] ?? ''), true);
        foreach (is_array($faqs) ? $faqs : [] as $question => $answer) {
            $fields['faq.' . $question] = is_scalar($answer) ? (string) $answer : '';
        }
        return $fields;
    }

    /** @param array<string, string>|null $before */
    private function recordHomeConfigUpdate(string $detail, ?array $before): void
    {
        $after = $this->homeAuditFields();
        $extra = ['entity' => ['type' => 'config', 'id' => 'home', 'label' => 'Home Page Settings']];
        if ($before !== null && $after !== null) {
            $labels = self::HOME_LABELS;
            foreach (array_keys($before + $after) as $field) {
                if (str_starts_with($field, 'faq.')) {
                    $labels[$field] = 'FAQ › ' . substr($field, 4);
                }
            }
            $extra['changes'] = Application_Model_DbTable_AuditLog::changes($before, $after, $labels);
        }
        if (!empty($_FILES['home_banner']['name'])) {
            // A new banner keeps the same stored file name, so the comparison above cannot see it.
            $extra['changes'] ??= [];
            $extra['changes'][] = ['field' => 'home_banner', 'label' => 'Home Banner Image', 'from' => '', 'to' => (string) $_FILES['home_banner']['name']];
        }
        Application_Model_DbTable_AuditLog::record("Updated home page config{$detail}", 'config', $extra);
    }
}
