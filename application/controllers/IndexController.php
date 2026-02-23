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

            // Anti-spam: honeypot check
            if (!empty($params['website_url'])) {
                $this->view->message = 1;
                return;
            }

            // Anti-spam: HMAC timestamp check
            $formToken = $params['form_token'] ?? '';
            if (!str_contains($formToken, '.')) {
                $this->view->message = 1;
                return;
            }
            [$timestamp, $hash] = explode('.', $formToken, 2);
            $secret = Application_Service_Common::getFormSecret();
            $expected = hash_hmac('sha256', $timestamp, $secret);
            if (!hash_equals($expected, $hash)) {
                $this->view->message = 1;
                return;
            }
            $elapsed = time() - (int)$timestamp;
            if ($elapsed < 5 || $elapsed > 3600) {
                $this->view->message = 1;
                return;
            }

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

            // Generate HMAC token for contact form anti-spam
            $secret = Application_Service_Common::getFormSecret();
            $timestamp = time();
            $hash = hash_hmac('sha256', (string)$timestamp, $secret);
            $this->view->formToken = $timestamp . '.' . $hash;
        }
    }
}
