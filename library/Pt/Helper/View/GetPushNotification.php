<?php

class Pt_Helper_View_GetPushNotification extends Zend_View_Helper_Abstract {

    public function getPushNotification() {
        $commonService = new Application_Service_Common();
        return $commonService->fetchUnReadPushNotify();
    }
}

?>
