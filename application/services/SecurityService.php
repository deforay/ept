<?php

class Application_Service_SecurityService
{


    public static function generateCSRF()
    {
        $csrfNamespace = new Zend_Session_Namespace('csrf');
        if (empty($csrfNamespace->token)) {
            $csrfNamespace->token = bin2hex(random_bytes(32)); // Generate a 64-character random token
        }

        $csrfNamespace->tokenTime = time();
    }
    public static function invalidateCSRF()
    {
        $csrfNamespace = new Zend_Session_Namespace('csrf');
        if (isset($csrfNamespace->token)) {
            unset($csrfNamespace->token);
            unset($csrfNamespace->tokenTime);
        }
    }
    public static function rotateCSRF(): void
    {
        self::invalidateCSRF();
        self::generateCSRF();
    }

    public static function checkCSRF(Zend_Controller_Request_Http $request, $invalidate = false): bool
    {

        $method = strtoupper($request->getMethod());

        // Check if method is one of the modifying methods
        $modifyingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        $csrfNamespace = new Zend_Session_Namespace('csrf');

        if (
            php_sapi_name() === 'cli' ||
            $request->isXmlHttpRequest() ||
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
