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
            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $sec = APPLICATION_ENV;
            $config->$sec->home->content->title = $this->getRequest()->getPost('title');
            $config->$sec->home->content->heading1 = $this->getRequest()->getPost('heading1');
            $config->$sec->home->content->heading2 = $this->getRequest()->getPost('heading2');
            $config->$sec->home->content->heading3 = $this->getRequest()->getPost('heading3');
            $config->$sec->home->content->video = $this->getRequest()->getPost('video');
            $writer = new Zend_Config_Writer_Ini();
            $writer->setConfig($config)->setFilename($file)->write();
        }
        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
    }
}
