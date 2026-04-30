<?php

use Application_Service_SecurityService as SecurityService;

class Pt_Plugins_PreSetter extends Zend_Controller_Plugin_Abstract
{

    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {

        $layout = Zend_Layout::getMvcInstance();


        /** @var Zend_Controller_Request_Http $request */
        if ($request->getControllerName() == 'error') {
            return;
        }

        $csrfCheck = SecurityService::checkCSRF($request);

        if (!$csrfCheck) {
            $translate = Zend_Registry::get('translate');
            $expiredMessage = $translate->_('Your session has expired. Please sign in again.');
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = $expiredMessage;

            // Admin/reports area: bounce to admin login. Public area: send to participant login.
            if (in_array($request->getModuleName(), ['admin', 'reports'], true)) {
                $request->setModuleName('admin')
                    ->setControllerName('login')
                    ->setActionName('index');
            } else {
                $request->setModuleName('default')
                    ->setControllerName('auth')
                    ->setActionName('login');
            }
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
                    if ($_SESSION['profile_confirmed']) {
                        $sessionAlert->message = "Please change your password to proceed.";
                    }
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
}