<?php

class IndexController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->initContext();
    }

    public function preDispatch()
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (isset($authNameSpace->forcePasswordReset) && ($authNameSpace->forcePasswordReset == 1 || $authNameSpace->forcePasswordReset == '1')) {
            $this->redirect("/participant/password");
        }
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $this->_helper->layout()->disableLayout();
            $params = $this->getRequest()->getPost();
            $common = new Application_Service_Common();
            $this->view->message = $common->contactForm($params);
        }else{
            $this->_helper->layout()->setLayout('home');
            $this->_helper->layout()->activeMenu = 'home';
            $commonServices = new Application_Service_Common();
            $partnerService = new Application_Service_Partner();
            $scheme = new Application_Service_Schemes();
            $homeSec = new Application_Service_HomeSection();
    
    
            $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
            $config = new Zend_Config_Ini($file, APPLICATION_ENV);
            $this->view->homeContent = $config->home->content;
            $this->view->faqs = htmlspecialchars_decode($config->home->content->faq);
            $this->view->countriesList = $commonServices->getcountriesList();
            $this->view->banner = $commonServices->getHomeBanner();
            $this->view->partners = $partnerService->getAllActivePartners();
            $this->view->schemes = $scheme->getAllSchemes();
            $this->view->homeSection = $homeSec->getAllHomeSection();
        }
    }
}
