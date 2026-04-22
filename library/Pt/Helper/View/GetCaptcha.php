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
            // Mixed alphanumeric (excluding ambiguous 0/O/1/l/I) to resist OCR.
            $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
            $phrase = '';
            for ($i = 0; $i < 6; $i++) {
                $phrase .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        }

        $builder = new CaptchaBuilder($phrase);
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
