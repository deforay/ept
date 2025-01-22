#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');

use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;
use Netflie\WhatsAppCloudApi\Message\Template\Component;

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);


// Instantiate the WhatsAppCloudApi super class.
$whatsapp_cloud_api = new WhatsAppCloudApi([
    'from_phone_number_id' => $conf->whatsapp->phoneId,
    'access_token' => $conf->whatsapp->token,
]);
/* 

$component_header = [];

$component_body = [
    [
        'type' => 'text',
        'text' => '*Mr Jones*',
    ],
];

$component_buttons = [
    [
        'type' => 'button',
        'sub_type' => 'quick_reply',
        'index' => 0,
        'parameters' => [
            [
                'type' => 'text',
                'text' => 'Yes',
            ]
        ]
    ],
    [
        'type' => 'button',
        'sub_type' => 'quick_reply',
        'index' => 1,
        'parameters' => [
            [
                'type' => 'text',
                'text' => 'No',
            ]
        ]
    ]
];

$components = new Component($component_header, $component_body, $component_buttons);

$whatsapp_cloud_api->sendTemplate('+919943734726', 'sample_issue_resolution', 'en_US', $components); // Language is optional
 */
$whatsapp_cloud_api->sendTextMessage('+919943734726', 'Hey there! I\'m using WhatsApp Cloud API. Visit https://www.netflie.es');