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

        // Dead-route 404s (no route/controller/action) are almost entirely automated
        // vulnerability scanners probing for /.git/config, /wp-login.php, etc. They are
        // handled correctly and carry no diagnostic value, so we suppress logging for them.
        $isDeadRoute = false;

        switch ($errors->type) {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
                $httpCode = 404;
                $priority = Zend_Log::NOTICE;
                $this->view->message = 'Page not found';
                $isDeadRoute = true;
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

        // Mint a short, human-quotable error ID for the genuine errors we log, so
        // the user can read it off the page and an admin can paste it straight
        // into the Log Viewer search to find this exact event. Dead-route 404s
        // are not logged, so they get no ID.
        $errorId = $isDeadRoute ? null : self::generateErrorId();
        $this->view->errorId   = $errorId;
        $this->view->errorTime = date('Y-m-d H:i:s');

        // Show the "View Logs" shortcut only to a signed-in admin who actually
        // holds the Log Viewer privilege — same gate as Admin_LogViewerController.
        $this->view->canViewLogs = self::canViewLogs();

        // Any signed-in admin can use Spotlight to navigate off the error page.
        // The shared spotlight partial renders itself only for an admin session,
        // so this flag just gates the button + hint that drive it.
        $this->view->canSearch = self::hasAdminSession();

        // Log exception, if logger available (skip scanner-driven dead-route 404s).
        $log = $this->getLog();
        if (!$isDeadRoute && false !== $log) {
            $log->log($this->view->message, $priority, $errors->exception);
            $log->log('Request Parameters', $priority, $errors->request->getParams());
        }

        if (!$isDeadRoute) {
            $this->logToMonolog($errors, $priority, $errorId);
        }

        // Return JSON for AJAX requests
        if ($this->getRequest()->isXmlHttpRequest()) {
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender(true);

            $response = [
                'status' => 'error',
                'message' => $this->view->message,
            ];
            if (!empty($errorId)) {
                $response['error_id'] = $errorId;
            }

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

    /**
     * Short, human-quotable identifier for a logged error. Embedded in the log
     * line (so it is searchable in the Log Viewer) and shown on the error page.
     */
    private static function generateErrorId(): string
    {
        return 'ERR-' . date('Ymd-His') . '-' . substr(uniqid(), -6);
    }

    /**
     * True only for a signed-in admin who holds the Log Viewer privilege.
     * Mirrors the gate in Admin_LogViewerController::init(). Never throws — a
     * permission/session hiccup must not blow up the error page itself.
     */
    private static function canViewLogs(): bool
    {
        try {
            $adminSession = new Zend_Session_Namespace('administrators');
            if (empty($adminSession->admin_id)) {
                return false;
            }
            $privileges = explode(',', (string) ($adminSession->privileges ?? ''));
            return in_array('analyze-generate-reports', $privileges, true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** True when an admin is signed in (drives the Spotlight search affordance). */
    private static function hasAdminSession(): bool
    {
        try {
            return !empty((new Zend_Session_Namespace('administrators'))->admin_id);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function logToMonolog(ArrayObject $errors, int $priority, ?string $errorId = null): void
    {
        if (!class_exists('Pt_Commons_LoggerUtility')) {
            return;
        }
        $exception = $errors->exception ?? null;
        $request   = $errors->request   ?? null;
        $context = [
            'error_id'   => $errorId,
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

        // Lead with the ID so it stands out in the log and the Log Viewer
        // "?q=<id>" deep-link from the error page lands on this exact line.
        if (!empty($errorId)) {
            $message = '[' . $errorId . '] ' . $message;
        }

        if ($priority <= Zend_Log::ERR) {
            Pt_Commons_LoggerUtility::logError($message, $context);
        } else {
            Pt_Commons_LoggerUtility::logWarning($message, $context);
        }
    }
}
