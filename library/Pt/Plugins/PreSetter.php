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

        if ($request->isPost() === true && $request->isXmlHttpRequest() === false) {
            self::checkCSRF($request);
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
            !($request->getControllerName() == 'participant' && $request->getActionName() == 'download-file')
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
        if (!isset($csrfNamespace->token) || time() - ($csrfNamespace->tokenTime ?? 0) > 3600) {
            $csrfNamespace->token = bin2hex(random_bytes(32)); // Generate a 64-character random token
            $csrfNamespace->tokenTime = time();
        }
    }
    private static function invalidateCSRF()
    {
        $csrfNamespace = new Zend_Session_Namespace('csrf');
        if (isset($csrfNamespace->token)) {
            unset($csrfNamespace->token);
            unset($csrfNamespace->tokenTime);
        }
    }
    private static function invalidateAndGenerateCSRF(): void
    {
        self::invalidateCSRF();
        self::generateCSRF();
    }

    private static function checkCSRF($request, $invalidate = false): void
    {
        $csrfNamespace = new Zend_Session_Namespace('csrf');
        $token = $request->getPost('csrf_token');
        $translate = Zend_Registry::get('translate');

        // Check if the CSRF token is expired (1 hour)
        if (time() - $csrfNamespace->tokenTime > 3600) {
            self::invalidateAndGenerateCSRF();

            // Forward to the default error/error action
            $request->setControllerName('error')
                ->setActionName('error')
                ->setParam('message', $translate->_('Request token expired. Please refresh the page and try again.'));
            $request->setDispatched(false);
        }

        // Validate token
        if (empty($token) || !isset($csrfNamespace->token) || !hash_equals($csrfNamespace->token, $token)) {
            // Forward to the default error/error action
            $request->setControllerName('error')
                ->setActionName('error')
                ->setParam('message', $translate->_('Invalid request token'));
            $request->setDispatched(false);
        }

        // Optionally invalidate and generate a new token
        if ($invalidate) {
            self::invalidateAndGenerateCSRF();
        }
    }
}
