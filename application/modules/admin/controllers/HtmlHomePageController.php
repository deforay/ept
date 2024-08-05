<?php

class Admin_HtmlHomePageController extends Zend_Controller_Action
{

    public function init()
    {

        /** @var Zend_Controller_Request_Http $request */

        $request = $this->getRequest();

        $this->_helper->layout()->pageName = 'configMenu';
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext
            ->addActionContext('get-html-template-by-section', 'html')
            ->addActionContext('get-sections-list', 'html')
            ->initContext();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
    }

    public function indexAction()
    {
        $homeSection = new Application_Service_HomeSection();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $homeSection->saveHomePageHtmlContent($params);
            $this->redirect('/admin/html-home-page');
        }
        $this->view->sections = $homeSection->getAllActiveHtmlHomePage();
        $this->view->htmlHomePage = $homeSection->getActiveHtmlHomePage();
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
