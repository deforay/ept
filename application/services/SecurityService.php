<?php

class Application_Service_SecurityService
{

    public static function generateCSRF(): void
    {
        $csrfNamespace = new Zend_Session_Namespace('csrf');
        if (empty($csrfNamespace->token)) {
            $csrfNamespace->token = bin2hex(random_bytes(32));
            $csrfNamespace->tokenTime = time();
        }
    }

    public static function checkCSRF(Zend_Controller_Request_Http $request): bool
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
        }

        $csrfToken = $request->getHeader('X-CSRF-Token') ?: $request->getPost('csrf_token');

        // Validate token
        if (
            empty($csrfToken) ||
            !hash_equals($csrfNamespace->token, $csrfToken)
        ) {
            return false;
        }

        return true;
    }
}
