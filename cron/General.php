<?php

/**
 * General functions
 *
 * @author Amit Dugar <amit@deforay.com>
 */
class General {

    /**
     * Used to format date from dd-mmm-yyyy to yyyy-mm-dd for storing in database
     *
     */
    function humanDateFormat($dateIn)
    {

        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";

        // $config = new Zend_Config_Ini($file, APPLICATION_ENV, array('allowModifications'=>true, 'nestSeparator'=>"#"));
        $config = new Zend_Config_Ini($file, APPLICATION_ENV, array('allowModifications' => false));

        $formatDate = $config->participant->dateformat;

        if (empty($dateIn) && $dateIn == null || $dateIn == "" || $dateIn == "0000-00-00") {
            return '';
        } else {

            $dateArray = explode('-', $dateIn);
            $newDate = $dateArray[2] . "-";

            $monthsArray = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
            $mon = $monthsArray[$dateArray[1] - 1];
            if ($formatDate == 'dd-M-yy')
                return  $newDate . $mon . "-" . $dateArray[0];
            else
                return   $mon . "-" . $newDate  . $dateArray[0];
        }
    }
}

