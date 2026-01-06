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
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        try {
            $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
            $homeSection = new Application_Service_HomeSection();

            /** @var Zend_Controller_Request_Http $request */
            $request = $this->getRequest();
            if ($request->isPost()) {
                $params = $this->getAllParams();

                $link = $request->getPost("fileLink");
                if (isset($link) && !empty($link)) {
                    $uploadDirectory = realpath(UPLOAD_PATH);
                    $common = new Application_Service_Common();
                    foreach ($link as $key => $l) {
                        if (isset($_FILES['fileLink']['name'][$key]['file']) && !empty($_FILES['fileLink']['name'][$key]['file'])) {
                            $fileNameSanitized = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['fileLink']['name'][$key]['file']);
                            $fileNameSanitized = str_replace(" ", "-", $fileNameSanitized);
                            $extension = strtolower(pathinfo($uploadDirectory . DIRECTORY_SEPARATOR . $fileNameSanitized, PATHINFO_EXTENSION));
                            $random = Pt_Commons_MiscUtility::generateRandomString(6);
                            $fileName = $random . "." . $extension;

                            if (!file_exists($uploadDirectory . DIRECTORY_SEPARATOR . 'home-links')) {
                                mkdir($uploadDirectory . DIRECTORY_SEPARATOR . 'home-links');
                            }
                            if (move_uploaded_file($_FILES['fileLink']['tmp_name'][$key]['file'], $uploadDirectory . DIRECTORY_SEPARATOR . 'home-links' . DIRECTORY_SEPARATOR . $fileName)) {
                                $link[$key]['file'] = $fileName;
                            }
                        }
                    }
                    $link = json_encode($link, true);
                    $params['home']['fileLinks'] = $link;
                }

                if (isset($params['home']) && !empty($params['home'])) {
                    $home = json_encode($params['home']);
                    $common->saveConfigByName($home, 'home');
                }
                $customHomePage = $request->getPost('customHomePage') ?? null;
                if (isset($customHomePage) && $customHomePage == 'yes') {
                    $params = $this->getAllParams();
                    $homeSection->saveHomePageHtmlContent($params);
                }
            }
            $common = new Application_Service_Common();
            $this->view->home = json_decode($common->getConfig('home'));
            $this->view->faq = json_decode($common->getConfig('faqs'));

            $this->view->sections = $homeSection->getAllHtmlHomePage();
            $this->view->htmlHomePage = $homeSection->getActiveHtmlHomePage();
        } catch (Exception $exc) {
            error_log("HOME-CONFIG--" . $exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }

    function getHtmlTemplateBySectionAction()
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
}
