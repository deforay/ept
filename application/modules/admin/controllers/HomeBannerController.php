<?php

class Admin_HomeBannerController extends Zend_Controller_Action
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
        $commonServices = new Application_Service_Common();
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $commonServices->updateHomeBanner($params);
            $this->redirect("/admin/home-banner");
        } else {
            $this->view->banner = $commonServices->getHomeBannerDetails();
        }
    }
}
