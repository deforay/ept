<?php

class Admin_HomeBannerController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $commonServices = new Application_Service_Common();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $commonServices->updateHomeBanner($params);
            $this->redirect("/admin/home-banner");
        } else {
            $this->view->banner = $commonServices->getHomeBannerDetails();
        }
    }
}
