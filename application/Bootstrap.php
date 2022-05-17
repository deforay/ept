<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initAppSetup()
    {

        define('APP_VERSION', '7.2.1');

        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $locale = (!empty($conf->locale) ? $conf->locale : "en_US");
        $timezone = (!empty($conf->timezone) ? $conf->timezone : "UTC");
        Zend_Session::start();
        date_default_timezone_set($timezone);
        $appLocale = new Zend_Locale($locale);
        Zend_Registry::set('Zend_Locale', $appLocale);

        $router = Zend_Controller_Front::getInstance()->getRouter();
        $router->addRoute("captchaRoute", new Zend_Controller_Router_Route('captcha/:r', array('controller' => 'captcha', 'action' => 'index', 'r' => '')));
        $router->addRoute("downloadRoute", new Zend_Controller_Router_Route('d/:filepath', array('controller' => 'download', 'action' => 'index', 'filepath' => '')));
        $router->addRoute("checkCaptchaRoute", new Zend_Controller_Router_Route_Static('captcha/check-captcha', array('controller' => 'captcha', 'action' => 'check-captcha')));


        //Database Cache

        $session = new Zend_Session_Namespace('cacheSpace');
        if (isset($session->defaultCache)) {
            Zend_Db_Table_Abstract::setDefaultMetadataCache(unserialize($session->defaultCache));
        } else {
            $frontendOptions = array(
                'lifetime' => 7200000000,
                'automatic_serialization' => true
            );
            $backendOptions = array(
                'cache_dir' => APPLICATION_PATH . DIRECTORY_SEPARATOR . "cache" . DIRECTORY_SEPARATOR
            );
            $frontend = "Core";
            $backend = "File";

            $cache = Zend_Cache::factory($frontend, $backend, $frontendOptions, $backendOptions);
            $session->defaultCache = serialize($cache);
            Zend_Db_Table_Abstract::setDefaultMetadataCache($cache);
        }
    }

    protected function _initTranslate()
    {
        // We use the Swedish locale as an example
        $locale = new Zend_Locale('fr_FR');
        Zend_Registry::set('Zend_Locale', $locale);

        // Create Session block and save the locale
        $session = new Zend_Session_Namespace('session');
        $langLocale = isset($session->lang) ? $session->lang : $locale;

        // Set up and load the translations (all of them!)
        $translate = new Zend_Translate(
            'gettext',
            APPLICATION_PATH . DIRECTORY_SEPARATOR . 'languages',
            $langLocale,
            array('disableNotices' => true)
        );

        //$translate->setLocale($langLocale); // Use this if you only want to load the translation matching current locale, experiment.

        // Save it for later
        $registry = Zend_Registry::getInstance();
        $registry->set('Zend_Translate', $translate);
    }
}
