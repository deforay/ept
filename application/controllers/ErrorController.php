<?php

class ErrorController extends Zend_Controller_Action
{
    public function errorAction()
    {
        $errors = $this->_getParam('error_handler');

        if (!$errors || !$errors instanceof ArrayObject) {
            $this->view->message = 'You have reached the error page';
            return;
        }

        switch ($errors->type) {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
                // 404 error -- controller or action not found
                $this->getResponse()->setHttpResponseCode(404);
                $priority = Zend_Log::NOTICE;
                $this->view->message = 'Page not found';
                break;
            default:
                // application error
                $this->getResponse()->setHttpResponseCode(500);
                $priority = Zend_Log::CRIT;
                $this->view->message = 'Application error';
                break;
        }

        // Log exception, if logger available
        $log = $this->getLog();
        if (false !== $log) {
            $log->log($this->view->message, $priority, $errors->exception);
            $log->log('Request Parameters', $priority, $errors->request->getParams());
        }

        $this->logToMonolog($errors, $priority);

        // Return JSON for AJAX requests
        if ($this->getRequest()->isXmlHttpRequest()) {
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender(true);

            $response = [
                'status' => 'error',
                'message' => $this->view->message,
            ];

            // Include exception details in development
            if ($this->getInvokeArg('displayExceptions') == true && isset($errors->exception)) {
                $response['exception'] = [
                    'message' => $errors->exception->getMessage(),
                    'file' => $errors->exception->getFile(),
                    'line' => $errors->exception->getLine(),
                    'trace' => $errors->exception->getTraceAsString(),
                ];
            }

            $this->getResponse()
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode($response));
            return;
        }

        // conditionally display exceptions
        if ($this->getInvokeArg('displayExceptions') == true) {
            $this->view->exception = $errors->exception;
        }

        $this->view->request   = $errors->request;
    }

    public function getLog()
    {
        $bootstrap = $this->getInvokeArg('bootstrap');
        if (!$bootstrap->hasResource('Log')) {
            return false;
        }
        $log = $bootstrap->getResource('Log');
        return $log;
    }

    private function logToMonolog(ArrayObject $errors, int $priority): void
    {
        if (!class_exists('Pt_Commons_LoggerUtility')) {
            return;
        }
        $exception = $errors->exception ?? null;
        $request   = $errors->request   ?? null;
        $context = [
            'type'       => (string) ($errors->type ?? ''),
            'url'        => $request ? (string) $request->getRequestUri() : '',
            'method'     => $request ? strtoupper((string) $request->getMethod()) : '',
            'module'     => $request ? (string) $request->getModuleName() : '',
            'controller' => $request ? (string) $request->getControllerName() : '',
            'action'     => $request ? (string) $request->getActionName() : '',
            'params'     => $request ? $request->getParams() : [],
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
        ];
        if ($exception instanceof Throwable) {
            $message = get_class($exception) . ': ' . $exception->getMessage()
                . ' at ' . $exception->getFile() . ':' . $exception->getLine();
            $context['trace'] = substr($exception->getTraceAsString(), 0, 8000);
        } else {
            $message = (string) ($this->view->message ?? 'Application error');
        }

        if ($priority <= Zend_Log::ERR) {
            Pt_Commons_LoggerUtility::logError($message, $context);
        } else {
            Pt_Commons_LoggerUtility::logWarning($message, $context);
        }
    }
}
