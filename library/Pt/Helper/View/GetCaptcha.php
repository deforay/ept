<?php

use Gregwar\Captcha\CaptchaBuilder;

class Pt_Helper_View_GetCaptcha extends Zend_View_Helper_Abstract
{

    public function getCaptcha()
    {
        //if it is development environment, then let us keep it simple
        if (APPLICATION_ENV === "development") {
            $phrase = "zaq";
        } else {
            // Simple 5-digit numeric. Kept readable on purpose — the heavy
            // lifting against bots is done by the honeypot + single-use HMAC
            // form token on the contact form, and server-side single-use
            // enforcement on the login flows.
            $phrase = (string) random_int(10000, 99999);
        }

        $builder = new CaptchaBuilder($phrase);
        $builder->setDistortion(false);
        $builder->setMaxBehindLines(0);
        $builder->setMaxFrontLines(0);
        $builder->build(180, 70);

        $captchaSession = new Zend_Session_Namespace("DACAPTCHA");
        $captchaSession->code = $phrase ?? $builder->getPhrase();
        // New image => previous pass is void. Caller must re-solve.
        $captchaSession->captchaStatus = 'fail';

        header('Content-type: image/jpeg');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        $builder->output();
    }
}
