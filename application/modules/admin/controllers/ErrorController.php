<?php

class Admin_ErrorController extends Zend_Controller_Action
{

    public function errorAction()
    {
        $errors = $this->_getParam('error_handler');

        if (!$errors || !$errors instanceof ArrayObject) {
            $this->view->message = $this->_getParam('message', 'You have reached the error page');
            return;
        }

        switch ($errors->type) {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
                $this->getResponse()->setHttpResponseCode(404);
                $priority = Zend_Log::NOTICE;
                $this->view->message = 'Page not found';
                break;
            default:
                $this->getResponse()->setHttpResponseCode(500);
                $priority = Zend_Log::CRIT;
                $this->view->message = 'Application error';
                break;
        }

        $log = $this->getLog();
        if (false !== $log) {
            $log->log($this->view->message, $priority, $errors->exception);
            $log->log('Request Parameters', $priority, $errors->request->getParams());
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender(true);

            $response = [
                'status' => 'error',
                'message' => $this->view->message
            ];

            if ($this->getInvokeArg('displayExceptions') == true && isset($errors->exception)) {
                $response['exception'] = [
                    'message' => $errors->exception->getMessage(),
                    'file' => $errors->exception->getFile(),
                    'line' => $errors->exception->getLine(),
                    'trace' => $errors->exception->getTraceAsString()
                ];
            }

            $this->getResponse()
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode($response));
            return;
        }

        if ($this->getInvokeArg('displayExceptions') == true) {
            $this->view->exception = $errors->exception;
        }

        $this->view->request = $errors->request;
    }

    public function getLog()
    {
        $bootstrap = $this->getInvokeArg('bootstrap');
        if (!$bootstrap->hasResource('Log')) {
            return false;
        }
        return $bootstrap->getResource('Log');
    }
}
