<?php
class Api_LoginController extends Zend_Controller_Action
{
    public function init()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            // $params = $this->getRequest()->getPost();
            $params = json_decode(file_get_contents('php://input'));
            $clientsServices = new Application_Service_DataManagers();
            $result = $clientsServices->loginDatamanagerAPI((array)$params);
            $this->getResponse()->setBody(json_encode($result,JSON_PRETTY_PRINT));
        }
    }
}
