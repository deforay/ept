<?php

class Pt_Plugins_PreSetter extends Zend_Controller_Plugin_Abstract {

    public function preDispatch(Zend_Controller_Request_Abstract $request) {
        $layout = Zend_Layout::getMvcInstance();
        $authNameSpace = new Zend_Session_Namespace('Zend_Auth');
        if ($request->getModuleName() == 'default'  && $request->getControllerName() != 'auth' && $request->getControllerName() != 'index' && $request->getControllerName() != 'captcha' && $request->getControllerName() != 'contact-us') {
            
            if(!Zend_Auth::getInstance()->hasIdentity()){
                $request->setModuleName('default')->setControllerName('auth')->setActionName('login');
                $request->setDispatched(false);
                return;
            }            
            if($authNameSpace->ForcePasswordReset == 1 || $authNameSpace->ForcePasswordReset == '1'){
                if ($request->getControllerName() == 'participant' && $request->getActionName() == 'password'){
                    $sessionAlert = new Zend_Session_Namespace('alertSpace');
                    $sessionAlert->message = "Please change your password to proceed.";
                }else{                        
                    $request->setModuleName('default')->setControllerName('participant')->setActionName('password');
                    $request->setDispatched(false);
                }
            }            
            
        }else if ($request->getModuleName() == 'admin'  && $request->getControllerName() != 'login') {
            $layout->setLayout('admin');
            if(!Zend_Auth::getInstance()->hasIdentity()){
                $request->setModuleName('admin')->setControllerName('login')->setActionName('index');
                $request->setDispatched(false);
                return;
            }
            
        }
        
        

        
    }

}