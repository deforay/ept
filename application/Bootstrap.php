<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
	protected function _initAppSetup(){

        define('APP_VERSION', '7.2.1');

		Zend_Session::start();
		date_default_timezone_set("America/New_York");
		$locale = new Zend_Locale('en_US');
		Zend_Registry::set('Zend_Locale', $locale);
		
		$router = Zend_Controller_Front::getInstance()->getRouter();
		$router->addRoute("captchaRoute", new Zend_Controller_Router_Route('captcha/:r', array('controller' => 'captcha', 'action' => 'index', 'r'=>'')));
		$router->addRoute("downloadRoute", new Zend_Controller_Router_Route('d/:filepath', array('controller' => 'download', 'action' => 'index', 'filepath'=>'')));
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
	
}

