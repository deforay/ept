<?php

class Pt_Plugins_PreSetter extends Zend_Controller_Plugin_Abstract {

    public function preDispatch(Zend_Controller_Request_Abstract $request) {
//        $layout = Zend_Layout::getMvcInstance();

        if (($request->getModuleName() == 'default'  && $request->getControllerName() != 'login')) {
            if(!Zend_Auth::hasIdentity()){
                $request->setModuleName('default')->setControllerName('login')->setActionName('index');
                $request->setDispatched(false);
                return;
            }
        }
    }

}