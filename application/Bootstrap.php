<?php
class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initAppSetup()
    {
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $timezone = !empty($conf->timezone) ? $conf->timezone : "UTC";

        // Start a session if it's not already started
        if (session_status() == PHP_SESSION_NONE) {
            Zend_Session::start();
        }

        // Generate CSRF token if not already generated
        self::generateCSRF();

        date_default_timezone_set($timezone);

        /** @var Zend_Controller_Router_Rewrite $router */

        $router = Zend_Controller_Front::getInstance()->getRouter();

        $router->addRoute("captchaRoute", new Zend_Controller_Router_Route('captcha/:r', array('controller' => 'captcha', 'action' => 'index', 'r' => '')));
        $router->addRoute("downloadRoute", new Zend_Controller_Router_Route('d/:filepath', array('controller' => 'download', 'action' => 'index', 'filepath' => '')));
        $router->addRoute("checkCaptchaRoute", new Zend_Controller_Router_Route_Static('captcha/check-captcha', array('controller' => 'captcha', 'action' => 'check-captcha')));

        //Database Cache
        $appDirectory = realpath(APPLICATION_PATH);
        $directoryPath = $appDirectory . DIRECTORY_SEPARATOR . "cache";
        if (!file_exists($directoryPath) || !is_dir($directoryPath)) {
            mkdir($directoryPath, 0777, true);
        }

        $session = new Zend_Session_Namespace('cacheSpace');
        if (isset($session->defaultCache)) {
            Zend_Db_Table_Abstract::setDefaultMetadataCache(unserialize($session->defaultCache));
        } else {
            $frontendOptions = [
                'lifetime' => 7200000000,
                'automatic_serialization' => true
            ];
            $backendOptions = [
                'cache_dir' => APPLICATION_PATH . DIRECTORY_SEPARATOR . "cache" . DIRECTORY_SEPARATOR
            ];
            $frontend = "Core";
            $backend = "File";

            $cache = Zend_Cache::factory($frontend, $backend, $frontendOptions, $backendOptions);
            $session->defaultCache = serialize($cache);
            Zend_Db_Table_Abstract::setDefaultMetadataCache($cache);
        }
    }

    protected function _initTranslate()
    {
        $locale = "en_US"; // default locale

        $authNameSpace = new Zend_Session_Namespace('datamanagers');

        if (isset($authNameSpace->language) && !empty(trim($authNameSpace->language))) {
            $locale = trim($authNameSpace->language);
        } else {
            // Get locale from database (global_config table with built-in fallback to config.ini)
            $this->bootstrap('db');
            $dbLocale = Application_Service_Common::getConfig('locale');
            if (!empty($dbLocale)) {
                $locale = trim($dbLocale);
            }
        }

        $translate = new Zend_Translate([
            'adapter' => 'gettext',
            'content' => APPLICATION_PATH . DIRECTORY_SEPARATOR . "languages/$locale/$locale.mo",
            'locale' => $locale
        ]);

        Zend_Registry::set('Zend_Locale', $locale);
        Zend_Registry::set('translate', $translate);

        $this->bootstrap('view');
        $view = $this->getResource('view');
        $view->translate = $translate;
    }

    private static function generateCSRF()
    {
        $csrfNamespace = new Zend_Session_Namespace('csrf');
        if (empty($csrfNamespace->token)) {
            $csrfNamespace->token = bin2hex(random_bytes(32)); // Generate a 64-character random token
        }

        $csrfNamespace->tokenTime = time();
    }
}
