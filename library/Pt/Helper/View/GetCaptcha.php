<?php

use Gregwar\Captcha\CaptchaBuilder;

class Pt_Helper_View_GetCaptcha extends Zend_View_Helper_Abstract
{

    public function getCaptcha()
    {
        //if it is development environment, then let us keep it simple
        if (APPLICATION_ENV == "development") {
            $phrase = "zaq";
        } else {
            $common = new Application_Service_Common();
            $phrase = $common->generateRandomString(4);
        }
        $builder = new CaptchaBuilder($phrase);
        $builder->setDistortion(false);
        $builder->build(200, 100);


        $captchaSession = new Zend_Session_Namespace("DACAPTCHA");
        $captchaSession->code = $phrase; //$builder->getPhrase();

        header('Content-type: image/jpeg');
        $builder->output();
    }
}
