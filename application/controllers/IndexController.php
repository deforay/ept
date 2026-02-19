<?php

class IndexController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $this->_helper->layout()->disableLayout();
            $params = $request->getPost();
            $common = new Application_Service_Common();
            $this->view->message = $common->contactForm($params);
        } else {
            $this->_helper->layout()->setLayout('home');
            $this->_helper->layout()->activeMenu = 'home';
            $commonServices = new Application_Service_Common();
            $partnerService = new Application_Service_Partner();
            $scheme = new Application_Service_Schemes();
            $homeSec = new Application_Service_HomeSection();

            $this->view->homeContent = $home = json_decode($commonServices->getConfig('home') ?? '');
            $this->view->faqs = $commonServices->getConfig('faqs');
            $this->view->countriesList = $commonServices->getcountriesList();
            $this->view->banner = $commonServices->getHomeBanner();
            $this->view->partners = $partnerService->getAllActivePartners();
            $this->view->schemes = $scheme->getAllSchemes();
            $this->view->homeSection = $homeSec->getAllHomeSection();
            $htmlHomePage = $homeSec->getActiveHtmlHomePage();

            if (isset($htmlHomePage) && !empty($htmlHomePage) && isset($home->customHomePage) && $home->customHomePage == 'yes') {
                $this->_helper->layout()->disableLayout();
                $this->view->htmlHomePage = $htmlHomePage;
            } else {
                $this->view->htmlHomePage = "";
            }
        }
    }
}
