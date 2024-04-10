<?php

class Admin_HomeConfigController extends Zend_Controller_Action
{

    public function init()
    {
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
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
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

            if (!empty($q[0]) && !empty(trim($a[0]))) {
                $faq = json_encode(array_combine($q, $a), true);
                $config->$section->home->content->faq = htmlspecialchars($faq, ENT_QUOTES, 'UTF-8');
            } else {
                $config->$section->home->content->faq = null;
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

            $writer = new Zend_Config_Writer_Ini();
            $writer->write($file, $config);
        }
        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
    }
}
