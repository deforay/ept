<?php

class Pt_Plugins_PreSetter extends Zend_Controller_Plugin_Abstract
{

    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {

        $layout = Zend_Layout::getMvcInstance();

        /** @var \Laminas\Http\Request $request */

        if ($request->getControllerName() == 'error') {
            return;
        }

        $csrfCheck = self::checkCSRF($request);

        if (!$csrfCheck) {
            $translate = Zend_Registry::get('translate');
            // Forward to the default error/error action
            $request->setControllerName('error')
                ->setActionName('error')
                ->setParam('message', $translate->_('Invalid or expired request. Please try again'));
            $request->setDispatched(false);
            return;
        }

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $loggedInAsParticipant = false;
        if (!empty($authNameSpace->dm_id)) {
            $loggedInAsParticipant = true;
        }

        $loggedInAsAdmin  = false;
        $adminAllowedOnFrontend = false;
        $adminAuthNameSpace = new Zend_Session_Namespace('administrators');
        if (isset($adminAuthNameSpace->admin_id)) {
            $loggedInAsAdmin = true;

            $currentURI = $request->getRequestUri();
            $adminAllowedURI = [
                '/dts/response',
                '/eid/response',
                '/vl/response',
                '/tb/response',
                '/recency/response',
                '/generic-test/response',
                '/tb/assay-formats',
            ];
            foreach ($adminAllowedURI as $uri) {
                if (strpos($currentURI, $uri) === 0) {
                    $adminAllowedOnFrontend = true;
                    break;
                }
            }
        }

        if (
            $request->getModuleName() == 'default' &&
            $request->getControllerName() != 'shipment-form'  &&
            $request->getControllerName() != 'auth' &&
            $request->getControllerName() != 'download'  &&
            $request->getControllerName() != 'error' &&
            $request->getControllerName() != 'index' &&
            $request->getControllerName() != 'captcha' &&
            $request->getControllerName() != 'common' &&
            !($request->getControllerName() == 'participant' &&
                $request->getActionName() == 'download-file')
        ) {
            if (!$loggedInAsParticipant && !$adminAllowedOnFrontend) {
                $request->setModuleName('default')->setControllerName('auth')->setActionName('login');
                $request->setDispatched(false);
            } elseif ($authNameSpace->forcePasswordReset == 1 || $authNameSpace->forcePasswordReset == '1') {
                if ($request->getControllerName() == 'participant' && $request->getActionName() == 'password') {
                    $sessionAlert = new Zend_Session_Namespace('alertSpace');
                    $sessionAlert->message = "Please change your password to proceed.";
                } else {
                    $request->setModuleName('default')->setControllerName('participant')->setActionName('password');
                    $request->setDispatched(false);
                }
            }
        } elseif (($request->getModuleName() == 'admin'  && $request->getControllerName() != 'login') || $request->getModuleName() == 'reports') {

            if (!$loggedInAsAdmin) {
                $request->setModuleName('admin')->setControllerName('login')->setActionName('index');
                $request->setDispatched(false);
            }
            $layout->setLayout('admin');
        }
    }


    private static function generateCSRF()
    {
        $csrfNamespace = new Zend_Session_Namespace('csrf');
        if (empty($csrfNamespace->token)) {
            $csrfNamespace->token = bin2hex(random_bytes(32)); // Generate a 64-character random token
        }

        $csrfNamespace->tokenTime = time();
    }
    private static function invalidateCSRF()
    {
        $csrfNamespace = new Zend_Session_Namespace('csrf');
        if (isset($csrfNamespace->token)) {
            unset($csrfNamespace->token);
            unset($csrfNamespace->tokenTime);
        }
    }
    private static function rotateCSRF(): void
    {
        self::invalidateCSRF();
        self::generateCSRF();
    }

    private static function checkCSRF($request, $invalidate = false): bool
    {

        $method = strtoupper($request->getMethod());

        // Check if method is one of the modifying methods
        $modifyingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        $csrfNamespace = new Zend_Session_Namespace('csrf');

        if (
            php_sapi_name() === 'cli' ||
            $request->getModuleName() === 'api' ||
            $request->getControllerName() === 'error' ||
            !in_array($method, $modifyingMethods) ||
            !isset($csrfNamespace->token)
        ) {
            return true;
        } else {

            $csrfToken = $request->getHeader('X-CSRF-Token') ?: $request->getPost('csrf_token');

            // Validate token
            if (
                empty($csrfToken) ||
                !hash_equals($csrfNamespace->token, $csrfToken)
            ) {
                return false;
            }

            // Optionally invalidate and generate a new token
            if ($request->isXmlHttpRequest() !== true && $invalidate) {
                self::rotateCSRF();
            }

            return true;
        }
    }
}
