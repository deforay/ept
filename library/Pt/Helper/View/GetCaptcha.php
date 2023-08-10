<?php

//
//	A simple PHP CAPTCHA script
//
//	Copyright 2011 by Cory LaViska for A Beautiful Site, LLC.
//
//	Based on http://abeautifulsite.net/blog/2011/01/a-simple-php-captcha-script/
//



class Pt_Helper_View_GetCaptcha extends Zend_View_Helper_Abstract
{

    public function getCaptcha($config = array())
    {
        if (!function_exists('gd_info')) {
            throw new Exception('Required GD library is missing');
        }

        // Default values
        $captcha_config = array(
            'code' => '',
            'min_length' => 4,
            'max_length' => 5,
            'png_backgrounds' => array(UPLOAD_PATH . '/../images/captchabg/default.png', UPLOAD_PATH . '/../images/captchabg/ravenna.png'),
            'fonts' => array(UPLOAD_PATH . '/../fonts/Idolwild/idolwild.ttf'),
            //'characters' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
            //'characters' => 'czobnfuvpokh5rzt9avu51sv2s7acug9iymucuhvsy4ljo3iatzjmv6dyjnzfchkl4hmrcszqqnsnump3jerxy78wymkmbspvxh93c82kokbiuvvgrzk9qujizls8rwhvbkfbypb10sx2g4tkqrkkboizkudmmubxn2lnaxcdpecmbbdl3l9lyvu8qgbhh3sr5soj3xhhfsv8ynrfa5qmlr9oxloirbrlqz444eo8hebgd5vnzj7l5fa22',
            'characters' => '232132143556788796564353432443546567687878654543324344545462212123235346578798708653532421223134567890343243243212345678902349846989283094829381293820938490282323232323234345455676878896543434434345566878989786753392018309123890128392392103892138902138',
            'min_font_size' => 22,
            'max_font_size' => 26,
            'color' => '#111',
            'angle_min' => 0,
            'angle_max' => 10,
            'shadow' => false,
            'shadow_color' => '#bbb',
            'shadow_offset_x' => -2,
            'shadow_offset_y' => 1
        );

        // Overwrite defaults with custom config values
        if (is_array($config)) {
            foreach ($config as $key => $value)
                $captcha_config[$key] = $value;
        }

        // Restrict certain values
        if ($captcha_config['min_length'] < 1)
            $captcha_config['min_length'] = 1;
        if ($captcha_config['angle_min'] < 0)
            $captcha_config['angle_min'] = 0;
        if ($captcha_config['angle_max'] > 10)
            $captcha_config['angle_max'] = 10;
        if ($captcha_config['angle_max'] < $captcha_config['angle_min'])
            $captcha_config['angle_max'] = $captcha_config['angle_min'];
        if ($captcha_config['min_font_size'] < 10)
            $captcha_config['min_font_size'] = 10;
        if ($captcha_config['max_font_size'] < $captcha_config['min_font_size'])
            $captcha_config['max_font_size'] = $captcha_config['min_font_size'];

        // Use milliseconds instead of seconds
        srand((float) microtime() * 1000);

        //if it is development environment, then let us keep it simple
        if(APPLICATION_ENV == "development"){
            $captcha_config['code'] = "zaq";
        }

        // Generate CAPTCHA code if not set by user
        if (empty($captcha_config['code'])) {
            $captcha_config['code'] = '';
            $length = rand($captcha_config['min_length'], $captcha_config['max_length']);
            while (strlen($captcha_config['code']) < $length) {
                $captcha_config['code'] .= substr($captcha_config['characters'], rand() % (strlen($captcha_config['characters'])), 1);
            }
            $captcha_config['code'] = str_shuffle(str_shuffle($captcha_config['code']));
        }

        // Generate image src
        $image_src = substr(__FILE__, strlen($_SERVER['DOCUMENT_ROOT'])) . '?DACAPTCHA&amp;t=' . urlencode(microtime());
        $image_src = '/' . ltrim(preg_replace('/\\\\/', '/', $image_src), '/');

        $captchaSession = new Zend_Session_Namespace("DACAPTCHA");
        $captchaSession->code = $captcha_config['code'];

        if (!function_exists('hex2rgb')) {

            function hex2rgb($hex_str, $return_string = false, $separator = ',')
            {
                $hex_str = preg_replace("/[^0-9A-Fa-f]/", '', $hex_str); // Gets a proper hex string
                $rgb_array = [];
                if (strlen($hex_str) == 6) {
                    $color_val = hexdec($hex_str);
                    $rgb_array['r'] = 0xFF & ($color_val >> 0x10);
                    $rgb_array['g'] = 0xFF & ($color_val >> 0x8);
                    $rgb_array['b'] = 0xFF & $color_val;
                } elseif (strlen($hex_str) == 3) {
                    $rgb_array['r'] = hexdec(str_repeat(substr($hex_str, 0, 1), 2));
                    $rgb_array['g'] = hexdec(str_repeat(substr($hex_str, 1, 1), 2));
                    $rgb_array['b'] = hexdec(str_repeat(substr($hex_str, 2, 1), 2));
                } else {
                    return false;
                }
                return $return_string ? implode($separator, $rgb_array) : $rgb_array;
            }
        }

        srand((float) microtime() * 1000);

        // Pick random background, get info, and start captcha
        $background = $captcha_config['png_backgrounds'][rand(0, count($captcha_config['png_backgrounds']) - 1)];
        list($bg_width, $bg_height, $bg_type, $bg_attr) = getimagesize($background);
        // Create captcha object
        $captcha = imagecreatefrompng($background);
        imagealphablending($captcha, true);
        imagesavealpha($captcha, true);

        $color = hex2rgb($captcha_config['color']);
        $color = imagecolorallocate($captcha, $color['r'], $color['g'], $color['b']);

        // Determine text angle
        $angle = rand($captcha_config['angle_min'], $captcha_config['angle_max']) * (rand(0, 1) == 1 ? -1 : 1);

        // Select font randomly
        $font = $captcha_config['fonts'][rand(0, count($captcha_config['fonts']) - 1)];

        // Verify font file exists
        if (!file_exists($font))
            throw new Exception('Font file not found: ' . $font);

        //Set the font size.
        $font_size = rand($captcha_config['min_font_size'], $captcha_config['max_font_size']);
        $text_box_size = imagettfbbox($font_size, $angle, $font, $captcha_config['code']);

        // Determine text position
        $box_width = abs($text_box_size[6] - $text_box_size[2]);
        $box_height = abs($text_box_size[5] - $text_box_size[1]);
        $text_pos_x_min = 0;
        $text_pos_x_max = ($bg_width) - ($box_width);
        $text_pos_x = rand($text_pos_x_min, $text_pos_x_max);
        $text_pos_y_min = $box_height;
        $text_pos_y_max = ($bg_height) - ($box_height / 2);
        $text_pos_y = rand($text_pos_y_min, $text_pos_y_max);

        // Draw shadow
        if ($captcha_config['shadow']) {
            $shadow_color = hex2rgb($captcha_config['shadow_color']);
            $shadow_color = imagecolorallocate($captcha, $shadow_color['r'], $shadow_color['g'], $shadow_color['b']);
            imagettftext($captcha, $font_size, $angle, $text_pos_x + $captcha_config['shadow_offset_x'], $text_pos_y + $captcha_config['shadow_offset_y'], $shadow_color, $font, $captcha_config['code']);
        }

        // Draw text
        imagettftext($captcha, $font_size, $angle, $text_pos_x, $text_pos_y, $color, $font, $captcha_config['code']);

        // Output image
        header("Content-type: image/png");
        imagepng($captcha);
    }
}
