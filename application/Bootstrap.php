<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
	protected function _initSesion(){
		Zend_Session::start();
		// setup localle
		$locale = new Zend_Locale('en_US');
		Zend_Registry::set('Zend_Locale', $locale);
		
		$router = Zend_Controller_Front::getInstance()->getRouter();
		$router->addRoute("captchaRoute", new Zend_Controller_Router_Route('captcha/:r', array('controller' => 'captcha', 'action' => 'index', 'r'=>'')));
		
	}
	
	
	
}

