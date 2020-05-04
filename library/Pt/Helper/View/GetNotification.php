<?php

class Pt_Helper_View_GetNotification extends Zend_View_Helper_Abstract {

    public function getNotification() {
        $commonService = new Application_Service_Common();
        return $commonService->fetchNotify();
    }

}

?>
