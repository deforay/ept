<?php

class Pt_Plugins_PreSetter extends Zend_Controller_Plugin_Abstract {

    public function preDispatch(Zend_Controller_Request_Abstract $request) {
        $layout = Zend_Layout::getMvcInstance();
        
        if($request->getControllerName() == 'error'){
            return;
        }
        
        if ($request->getModuleName() == 'default'&& $request->getControllerName() != 'shipment-form'  && $request->getControllerName() != 'auth' && $request->getControllerName() != 'download'  && $request->getControllerName() != 'error' && $request->getControllerName() != 'index' && $request->getControllerName() != 'captcha' && $request->getControllerName() != 'contact-us'&& $request->getControllerName() != 'pt-request-enrollment' && $request->getControllerName() != 'common') {
             $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if(!isset($authNameSpace->dm_id)){
                $request->setModuleName('default')->setControllerName('auth')->setActionName('login');
                $request->setDispatched(false);
                return;
            }            
            else if($authNameSpace->force_password_reset == 1 || $authNameSpace->force_password_reset == '1'){
                if ($request->getControllerName() == 'participant' && $request->getActionName() == 'password'){
                    $sessionAlert = new Zend_Session_Namespace('alertSpace');
                    $sessionAlert->message = "Please change your password to proceed.";
                }else{                        
                    $request->setModuleName('default')->setControllerName('participant')->setActionName('password');
                    $request->setDispatched(false);
                }
            }            
            
            else if($authNameSpace->force_profile_updation == 1 || $authNameSpace->force_profile_updation == '1'){
                if ($request->getControllerName() == 'participant' && $request->getActionName() == 'testeredit'){
                    $sessionAlert = new Zend_Session_Namespace('alertSpace');
                    $sessionAlert->message = "Please update participant information.";
                }else{
                    if($request->getActionName() != 'profile-update-redirect'){
                    $request->setModuleName('default')->setControllerName('participant')->setActionName('testeredit');
                    $request->setParam('psid',$authNameSpace->profile_updation_pid);
                    $request->setDispatched(false);
                    }
                }
            }
        }else if (($request->getModuleName() == 'admin'  && $request->getControllerName() != 'login') || $request->getModuleName() == 'reports') {
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $layout->setLayout('admin');
            if(!isset($authNameSpace->admin_id)){
                $request->setModuleName('admin')->setControllerName('login')->setActionName('index');
                $request->setDispatched(false);
                return;
            }
            
        }
        
        

        
    }

}