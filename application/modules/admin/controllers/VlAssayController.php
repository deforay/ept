<?php

class Admin_VlAssayController extends Zend_Controller_Action
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
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $parameters = $this->getAllParams();
            $vlAssayService = new Application_Service_VlAssay();
            $vlAssayService->getAllVlAssay($parameters);
        }
    }

    public function addAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $vlAssayService = new Application_Service_VlAssay();
            $vlAssayService->addVlAssay($params);
            $this->redirect("/admin/vl-assay");
        }
    }

    public function editAction()
    {
        $vlAssayService = new Application_Service_VlAssay();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $vlAssayService->updateVlAssay($params);
            $this->redirect("/admin/vl-assay");
        }
        if ($this->hasParam('id')) {
            $id = (int)$this->_getParam('id');
            $this->view->vlAssay = $vlAssayService->getVlAssay($id);
        } else {
            $this->redirect("/admin/vl-assay");
        }
    }
}
