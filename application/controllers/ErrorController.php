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

        // Map exception type / action-exception code → HTTP code + user-friendly headline.
        // Zend_Controller_Action_Exception carries an HTTP code in its ->getCode() that the
        // legacy default branch ignored, so 401/410/etc. all showed up as a generic 500.
        $exception = $errors->exception ?? null;
        $actionCode = ($exception instanceof Zend_Controller_Action_Exception) ? (int) $exception->getCode() : 0;

        switch ($errors->type) {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
                $httpCode = 404;
                $priority = Zend_Log::NOTICE;
                $this->view->message = 'Page not found';
                break;
            default:
                $httpCode = ($actionCode >= 400 && $actionCode < 600) ? $actionCode : 500;
                $priority = $httpCode >= 500 ? Zend_Log::CRIT : Zend_Log::NOTICE;
                $this->view->message = self::messageForCode($httpCode);
                break;
        }
        $this->getResponse()->setHttpResponseCode($httpCode);
        $this->view->httpCode = $httpCode;
        $this->view->codeLabel = self::labelForCode($httpCode);
        $this->view->detail = self::detailForCode($httpCode);

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

    private static function messageForCode(int $code): string
    {
        return match ($code) {
            400 => 'Bad request',
            401 => 'Authentication required',
            403 => 'Access denied',
            404 => 'Page not found',
            410 => 'Link is invalid or has expired',
            default => 'Application error',
        };
    }

    private static function labelForCode(int $code): string
    {
        return match ($code) {
            400 => '400 · Bad Request',
            401 => '401 · Sign-in Required',
            403 => '403 · Access Denied',
            404 => '404 · Page Not Found',
            410 => '410 · Link Expired',
            default => $code . ' · Application Error',
        };
    }

    private static function detailForCode(int $code): string
    {
        return match ($code) {
            401 => 'You need to sign in to access this page.',
            403 => 'Your account does not have permission to view this resource.',
            404 => 'The page you are looking for does not exist.',
            410 => 'This download link has expired or is no longer valid. Ask the original sender for a fresh link.',
            default => 'An unexpected error occurred while processing your request. You can try again, or return to the homepage.',
        };
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
