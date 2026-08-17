<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initAppSetup()
    {
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $timezone = !empty($conf->timezone) ? $conf->timezone : 'UTC';
        date_default_timezone_set($timezone);

        // Skip session handling in CLI mode
        if (php_sapi_name() !== 'cli') {
            // Start a session if it's not already started
            if (session_status() == PHP_SESSION_NONE) {
                Zend_Session::start();
            }

            // Generate CSRF token if not already generated
            Application_Service_SecurityService::generateCSRF();
        }

        /** @var Zend_Controller_Router_Rewrite $router */
        $router = Zend_Controller_Front::getInstance()->getRouter();

        $router->addRoute('captchaRoute', new Zend_Controller_Router_Route('captcha/:r', ['controller' => 'captcha', 'action' => 'index', 'r' => '']));
        $router->addRoute('downloadRoute', new Zend_Controller_Router_Route('d/:filepath', ['controller' => 'download', 'action' => 'index', 'filepath' => '']));
        // /dl/ — new encrypted-token download route; see Pt_Commons_SignedDownload.
        $router->addRoute('signedDownloadRoute', new Zend_Controller_Router_Route('dl/:token', ['controller' => 'dl', 'action' => 'index', 'token' => '']));
        $router->addRoute('checkCaptchaRoute', new Zend_Controller_Router_Route_Static('captcha/check-captcha', ['controller' => 'captcha', 'action' => 'check-captcha']));
        // /admin/scheme-config/dts — the Scheme Config hub. Without this the
        // default route would need /admin/scheme-config/index/scheme/dts.
        $router->addRoute('schemeConfigRoute', new Zend_Controller_Router_Route(
            'admin/scheme-config/:scheme',
            ['module' => 'admin', 'controller' => 'scheme-config', 'action' => 'index', 'scheme' => ''],
            ['scheme' => '[a-z0-9]*']
        ));

        //Database Cache
        $appDirectory = realpath(APPLICATION_PATH);
        $directoryPath = $appDirectory . DIRECTORY_SEPARATOR . 'cache';
        if (!file_exists($directoryPath) || !is_dir($directoryPath)) {
            mkdir($directoryPath, 0777, true);
        }

        $frontendOptions = ['lifetime' => 7200000000, 'automatic_serialization' => true];
        $backendOptions  = ['cache_dir' => APPLICATION_PATH . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR];

        if (php_sapi_name() !== 'cli') {
            $session = new Zend_Session_Namespace('cacheSpace');
            if (isset($session->defaultCache)) {
                Zend_Db_Table_Abstract::setDefaultMetadataCache(unserialize($session->defaultCache));
            } else {
                $cache = Zend_Cache::factory('Core', 'File', $frontendOptions, $backendOptions);
                $session->defaultCache = serialize($cache);
                Zend_Db_Table_Abstract::setDefaultMetadataCache($cache);
            }
        } else {
            $cache = Zend_Cache::factory('Core', 'File', $frontendOptions, $backendOptions);
            Zend_Db_Table_Abstract::setDefaultMetadataCache($cache);
        }
    }

    protected function _initTranslate()
    {
        $locale = 'en_US'; // default fallback

        if (php_sapi_name() !== 'cli') {
            // Priority 1: Admin-side modules — check administrators session
            if ($this->_isAdminSideRequest()) {
                $adminNameSpace = new Zend_Session_Namespace('administrators');
                if (!empty(trim($adminNameSpace->language ?? ''))) {
                    $locale = trim($adminNameSpace->language);
                }
            }

            // Priority 2: Datamanagers session
            if ($locale === 'en_US') {
                $authNameSpace = new Zend_Session_Namespace('datamanagers');
                if (!empty(trim($authNameSpace->language ?? ''))) {
                    $locale = trim($authNameSpace->language);
                }
            }

            // Priority 3: Database config
            if ($locale === 'en_US') {
                $this->bootstrap('db');
                $dbLocale = Application_Service_Common::getConfig('locale');
                if (!empty($dbLocale)) {
                    $locale = trim($dbLocale);
                }
            }
        } else {
            // CLI: get locale from database directly
            $this->bootstrap('db');
            $dbLocale = Application_Service_Common::getConfig('locale');
            if (!empty($dbLocale)) {
                $locale = trim($dbLocale);
            }
        }

        $translate = new Zend_Translate([
            'adapter' => 'gettext',
            'content' => APPLICATION_PATH . DIRECTORY_SEPARATOR . "languages/$locale/$locale.mo",
            'locale'  => $locale,
        ]);

        Zend_Registry::set('Zend_Locale', $locale);
        Zend_Registry::set('translate', $translate);

        $this->bootstrap('view');
        $view = $this->getResource('view');
        $view->translate = $translate;
    }

    /**
     * True for the modules an administrator browses, so _initTranslate() reads their
     * personal language. The reports module lives at /reports, not under /admin, so
     * matching only /admin left every Reports page falling through to the instance
     * locale — an admin with Vietnamese selected still got English there.
     */
    protected function _isAdminSideRequest()
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        return (bool) preg_match('#^/(admin|reports)(/|$)#i', $path);
    }
}
