<?php

class Admin_HomeBannerController extends Zend_Controller_Action
{

    public function init()
    {
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction(){
       $commonServices = new Application_Service_Common();  
       if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $commonServices->updateHomeBanner($params);
            $this->_redirect("/admin/home-banner");
        }else{
            $this->view->banner = $commonServices->getHomeBannerDetails();
        }
    }

}





