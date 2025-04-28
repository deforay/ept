<?php

class Pt_Helper_View_GetGlobalConfig extends Zend_View_Helper_Abstract
{

    public function getGlobalConfig()
    {
        $commonService = new Application_Service_Common();
        return $commonService->getGlobalConfigDetails();
    }
}
