<?php

use Gregwar\Captcha\CaptchaBuilder;
use Application_Service_Common as CommonService;

class Pt_Helper_View_GetCaptcha extends Zend_View_Helper_Abstract
{

    public function getCaptcha()
    {
        $phrase = null;
        //if it is development environment, then let us keep it simple
        if (APPLICATION_ENV === "development") {
            $phrase = "zaq";
        } else {
            $phrase = CommonService::generateRandomNumber(4);
        }

        $builder = new CaptchaBuilder($phrase);
        $builder->setDistortion(false);
        $builder->build(150, 70);

        $captchaSession = new Zend_Session_Namespace("DACAPTCHA");
        $captchaSession->code = $phrase ?? $builder->getPhrase();

        header('Content-type: image/jpeg');
        $builder->output();
    }
}
