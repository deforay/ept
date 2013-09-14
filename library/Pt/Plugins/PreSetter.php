<?php

class Pt_Plugins_PreSetter extends Zend_Controller_Plugin_Abstract {

    public function preDispatch(Zend_Controller_Request_Abstract $request) {
//        $layout = Zend_Layout::getMvcInstance();

        if ($request->getModuleName() == 'default'  && $request->getControllerName() != 'auth' && $request->getControllerName() != 'index' && $request->getControllerName() != 'captcha') {
            if(!Zend_Auth::getInstance()->hasIdentity()){
                $request->setModuleName('default')->setControllerName('auth')->setActionName('login');
                $request->setDispatched(false);
                return;
            }
        }
    }

}