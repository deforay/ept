<?php

class Pt_Helper_View_GetAppVersion extends Zend_View_Helper_Abstract{
    
    public function getAppVersion(){
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        return $conf->app->version;
    }
    

}