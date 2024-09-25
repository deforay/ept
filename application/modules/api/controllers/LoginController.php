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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            // Zend_Debug::dump('isPost');die;
            $params = json_decode(file_get_contents('php://input'));
            $clientsServices = new Application_Service_DataManagers();
            $result = $clientsServices->loginDatamanagerAPI((array)$params);
            $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
        }
    }

    public function changePasswordAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = json_decode(file_get_contents('php://input'));
            $clientsServices = new Application_Service_DataManagers();
            $result = $clientsServices->changePasswordDatamanagerAPI((array)$params);
            $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
        }
    }

    public function loginDetailsAction()
    {
        $params = $this->getAllParams();
        $clientsServices = new Application_Service_DataManagers();
        $result = $clientsServices->getLoggedInDetails((array)$params);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function forgetPasswordAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = json_decode(file_get_contents('php://input'));
            $clientsServices = new Application_Service_DataManagers();
            $result = $clientsServices->forgetPasswordDatamanagerAPI((array)$params);
            $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
        }
    }
}
