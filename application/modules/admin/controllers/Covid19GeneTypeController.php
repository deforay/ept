<?php

class Admin_Covid19GeneTypeController extends Zend_Controller_Action
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
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-covid19-gene-type', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $schemeService = new Application_Service_Schemes();
            $schemeService->getAllCovid19GeneTypeInGrid($params);
        }
    }

    public function addAction()
    {
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getAllSchemes();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $schemeService->addGeneType($params);
            $this->redirect("/admin/covid19-gene-type");
        }
    }

    public function editAction()
    {
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getAllSchemes();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $schemeService->updateCovid19GeneType($params);
            $this->redirect("/admin/covid19-gene-type");
        } else if ($this->_hasParam('53s5k85_8d')) {
            $id = base64_decode($this->_getParam('53s5k85_8d'));
            $this->view->result = $schemeService->getCovid19GeneType($id);
        } else {
            $this->redirect('admin/covid19-gene-type/index');
        }
    }
}
