<?php

/**
 * Common functions
 *
 * @author Amit Dugar <amit@deforay.com>
 */
class Common {


    function humanDateFormat($dateIn, $showDateAndTime = false)
    {

        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";

        // $config = new Zend_Config_Ini($file, APPLICATION_ENV, array('allowModifications'=>true, 'nestSeparator'=>"#"));
        $config = new Zend_Config_Ini($file, APPLICATION_ENV, array('allowModifications' => false));

        $formatDate = $config->participant->dateformat;

        if (empty($dateIn) && $dateIn == null || $dateIn == "" || $dateIn == "0000-00-00") {
            return '';
        } else {

            $dateInArray = explode(' ', $dateIn);
            $dateOutArray = explode('-', $dateInArray[0]);
            $newDate = $dateOutArray[2] . "-";

            $monthsArray = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
            $mon = $monthsArray[$dateOutArray[1] - 1];
            $time = "";
            if($showDateAndTime){
                $time = " ".$dateInArray[1];
            }
            if ($formatDate == 'dd-M-yy')
                return  $newDate . $mon . "-" . $dateOutArray[0].$time;
            else
                return   $mon . "-" . $newDate  . $dateOutArray[0].$time;
        }
    }
}

