<?php

class Admin_HomeConfigController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
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
        try{
            $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
            $homeSection = new Application_Service_HomeSection();

            /** @var Zend_Controller_Request_Http $request */
            $request = $this->getRequest();
            if ($request->isPost()) {


                // Zend_Debug::dump(htmlspecialchars($faq, ENT_QUOTES, 'UTF-8'));die;
                $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
                $section = APPLICATION_ENV ?? 'production';

                if (!isset($config->$section->home)) {
                    $config->$section->home = [];
                }

                if (!isset($config->$section->home->content)) {
                    $config->$section->home->content = [];
                }

                $q = $request->getPost("question");
                $a = $request->getPost("answer");
                $link = $request->getPost("fileLink");
                if (!empty($q[0]) && !empty(trim($a[0]))) {
                    $faq = json_encode(array_combine($q, $a), true);
                    $config->$section->home->content->faq = htmlspecialchars($faq, ENT_QUOTES, 'UTF-8');
                } else {
                    $config->$section->home->content->faq = null;
                }
                if (isset($link) && !empty($link)) {
                    $common = new Application_Service_Common();
                    foreach($link as $key=>$l){
                        if(isset($_FILES['fileLink']['name'][$key]['file']) && !empty($_FILES['fileLink']['name'][$key]['file'])){
                            $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['fileLink']['name'][$key]['file'], PATHINFO_EXTENSION));
                            $random = $common->generateRandomString(6);
                            $fileName = $random . "." . $extension;
                            
                            if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'home-links')) {
                                mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'home-links');
                            }
                            if (move_uploaded_file($_FILES['fileLink']['tmp_name'][$key]['file'], UPLOAD_PATH . DIRECTORY_SEPARATOR . 'home-links' . DIRECTORY_SEPARATOR . $fileName)) {
                                $link[$key]['file'] = $fileName;
                            }
                        }
                    }
                    $link = json_encode($link, true);
                    $config->$section->home->content->fileLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
                } else {
                    $config->$section->home->content->fileLink = null;
                }

                $config->$section->home->content->title = $request->getPost('title') ?? null;
                $config->$section->home->content->heading1 = $request->getPost('heading1') ?? null;
                $config->$section->home->content->heading2 = $request->getPost('heading2') ?? null;
                $config->$section->home->content->heading3 = $request->getPost('heading3') ?? null;
                $config->$section->home->content->homeSectionHeading1 = $request->getPost('homeSectionHeading1') ?? null;
                $config->$section->home->content->homeSectionHeading2 = $request->getPost('homeSectionHeading2') ?? null;
                $config->$section->home->content->homeSectionHeading3 = $request->getPost('homeSectionHeading3') ?? null;
                $config->$section->home->content->homeSectionIcon1 = $request->getPost('homeSectionIcon1') ?? null;
                $config->$section->home->content->homeSectionIcon2 = $request->getPost('homeSectionIcon2') ?? null;
                $config->$section->home->content->homeSectionIcon3 = $request->getPost('homeSectionIcon3') ?? null;
                $config->$section->home->content->video = $request->getPost('video') ?? null;
                $config->$section->home->content->additionalLink = $request->getPost('additionalLink') ?? null;
                $config->$section->home->content->additionalLinkText = $request->getPost('additionalLinkText') ?? null;

                $customHomePage = $request->getPost('customHomePage') ?? null;
                $config->$section->home->content->customHomePage = $customHomePage;
                if(isset($customHomePage) && $customHomePage == 'yes'){
                    $params = $this->getAllParams();
                    $homeSection->saveHomePageHtmlContent($params);
                }

                $writer = new Zend_Config_Writer_Ini();
                $writer->write($file, $config);
            }
            $this->view->sections = $homeSection->getAllActiveHtmlHomePage();
            $this->view->htmlHomePage = $homeSection->getActiveHtmlHomePage();
            $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
        } catch (Exception $exc) {
            error_log("HOME-CONFIG--" . $exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }

    function getHtmlTemplateBySectionAction(){
        $homeSection = new Application_Service_HomeSection();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $section = $request->getParam('section');
            $this->view->result = $homeSection->getActiveHtmlHomePage($section);
        }
    }

    public function getSectionsListAction(){
        $homeSection = new Application_Service_HomeSection();
        $this->_helper->layout()->disableLayout();
        if ($this->hasParam('search')) {
            $section = $this->_getParam('search');
            $this->view->search = $section;
            $this->view->section = $homeSection->getActiveHtmlHomePage($section);
        }
    }
}