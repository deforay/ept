<?php

class Admin_HomeConfigController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
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
        if ($this->getRequest()->isPost()) {
            $q = $this->getRequest()->getPost("question");
            $a = $this->getRequest()->getPost("answer");
            // Zend_Debug::dump(htmlspecialchars($faq, ENT_QUOTES, 'UTF-8'));die;
            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $sec = APPLICATION_ENV;
            $config->$sec->home->content->title = $this->getRequest()->getPost('title');
            $config->$sec->home->content->heading1 = $this->getRequest()->getPost('heading1');
            $config->$sec->home->content->heading2 = $this->getRequest()->getPost('heading2');
            $config->$sec->home->content->heading3 = $this->getRequest()->getPost('heading3');
            $config->$sec->home->content->homeSectionHeading1 = $this->getRequest()->getPost('homeSectionHeading1');
            $config->$sec->home->content->homeSectionHeading2 = $this->getRequest()->getPost('homeSectionHeading2');
            $config->$sec->home->content->homeSectionHeading3 = $this->getRequest()->getPost('homeSectionHeading3');
            $config->$sec->home->content->homeSectionIcon1 = $this->getRequest()->getPost('homeSectionIcon1');
            $config->$sec->home->content->homeSectionIcon2 = $this->getRequest()->getPost('homeSectionIcon2');
            $config->$sec->home->content->homeSectionIcon3 = $this->getRequest()->getPost('homeSectionIcon3');
            $config->$sec->home->content->video = $this->getRequest()->getPost('video');
            $config->$sec->home->content->additionalLink = $this->getRequest()->getPost('additionalLink');
            $config->$sec->home->content->additionalLinkText = $this->getRequest()->getPost('additionalLinkText');
            if (!empty($q[0]) > 0 && !empty($a[0]) > 0) {
                $faq = json_encode(array_combine($q, $a), true);
                $config->$sec->home->content->faq = htmlspecialchars($faq, ENT_QUOTES, 'UTF-8');
            } else {
                $config->$sec->home->content->faq = null;
            }
            $writer = new Zend_Config_Writer_Ini();
            $writer->setConfig($config)->setFilename($file)->write();
        }
        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
    }
}
