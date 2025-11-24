<?php
require_once 'constants.php';


set_include_path(implode(PATH_SEPARATOR, [
    realpath(ROOT_PATH . '/vendor'),
    realpath(ROOT_PATH . '/library'),
    get_include_path(),
]));

require_once ROOT_PATH . '/vendor/autoload.php';

/** Zend_Application */
require_once 'Zend/Application.php';

// Create application, bootstrap, and run
$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);

$application->bootstrap();
