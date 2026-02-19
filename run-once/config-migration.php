#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . '/../cli-bootstrap.php';

try {
    $console = Pt_Commons_MiscUtility::console();
    $console->writeln('<info>[config-migration]</info> Starting configuration migration...');

    // Load configuration using Zend_Config_Ini
    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/config.ini', APPLICATION_ENV);

    // Convert Zend_Config object to array
    $envConfig = $conf->toArray();

    $schemeConfigDb = new Application_Model_DbTable_SchemeConfig();
    $existingKeys = $schemeConfigDb->getAdapter()->fetchCol(
        $schemeConfigDb->select()->from($schemeConfigDb->info('name'), array('scheme_config_name'))
    );
    $existingLookup = array_flip($existingKeys);
    $schemeInserted = 0;
    $schemeSkipped = 0;
    $schemeTotal = 0;

    foreach (($envConfig['evaluation'] ?? []) as $key => $value) {
        $schemeTotal++;
        if (isset($existingLookup[$key])) {
            $schemeSkipped++;
            continue;
        }

        $schemeConfigDb->insert([
            'scheme_config_name' => $key,
            'scheme_config_value' => is_array($value) ? json_encode($value, true) : $value
        ]);
        $schemeInserted++;
    }
    $console->writeln(sprintf(
        '<comment>[config-migration]</comment> Scheme config: total=%d, inserted=%d, skipped(existing)=%d',
        $schemeTotal,
        $schemeInserted,
        $schemeSkipped
    ));

    unset($envConfig['evaluation']);

    $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
    $existingGlobalKeys = $globalConfigDb->getAdapter()->fetchCol(
        $globalConfigDb->select()->from($globalConfigDb->info('name'), array('name'))
    );
    $existingGlobalLookup = array_flip($existingGlobalKeys);
    $globalInserted = 0;
    $globalSkipped = 0;
    $globalTotal = 0;

    $toSnakeCase = static function ($value) {
        $value = str_replace('.', '_', $value);
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $value);
        $value = preg_replace('/_+/', '_', $value);
        return strtolower($value);
    };

    // Special handling for home.* settings
    // These should be stored as a single JSON object under 'home' key
    if (isset($envConfig['home'])) {
        // Support both home.content.* and home.* formats
        $homeContent = $envConfig['home']['content'] ?? $envConfig['home'];

        // Extract FAQ separately - it's stored in 'faqs' key
        if (isset($homeContent['faq'])) {
            $faqValue = $homeContent['faq'];
            $globalTotal++;
            // FAQ is already a JSON string in config.ini, decode and re-encode to validate
            if (!isset($existingGlobalLookup['faqs'])) {
                $globalConfigDb->insert([
                    'name' => 'faqs',
                    'value' => $faqValue
                ]);
                $existingGlobalLookup['faqs'] = true;
                $globalInserted++;
            } else {
                $globalSkipped++;
            }
            unset($homeContent['faq']);
        }

        // Map config.ini keys to expected home JSON keys
        $homeData = [];
        $keyMapping = [
            'title' => 'title',
            'heading1' => 'heading1',
            'heading2' => 'heading2',
            'heading3' => 'heading3',
            'video' => 'videoUrl',
            'additionalLink' => 'additionalLink',
            'additionalLinkText' => 'additionalLinkText',
            'homeSectionHeading1' => 'subHeading1',
            'homeSectionHeading2' => 'subHeading2',
            'homeSectionHeading3' => 'subHeading3',
            'homeSectionIcon1' => 'icon1',
            'homeSectionIcon2' => 'icon2',
            'homeSectionIcon3' => 'icon3',
        ];

        foreach ($homeContent as $key => $value) {
            $mappedKey = $keyMapping[$key] ?? $key;
            $homeData[$mappedKey] = $value;
        }

        if (!empty($homeData) && !isset($existingGlobalLookup['home'])) {
            $globalTotal++;
            $globalConfigDb->insert([
                'name' => 'home',
                'value' => json_encode($homeData)
            ]);
            $existingGlobalLookup['home'] = true;
            $globalInserted++;
        } elseif (!empty($homeData)) {
            $globalTotal++;
            $globalSkipped++;
        }

        unset($envConfig['home']);
    }

    foreach ($envConfig as $key => $value) {
        $queue = array(array($key, $value));

        while (!empty($queue)) {
            [$currentKey, $currentValue] = array_pop($queue);

            if (is_array($currentValue)) {
                foreach ($currentValue as $childKey => $childValue) {
                    $queue[] = ["{$currentKey}_$childKey", $childValue];
                }
                continue;
            }

            $snakeKey = $toSnakeCase($currentKey);
            $globalTotal++;
            if (isset($existingGlobalLookup[$snakeKey])) {
                $globalSkipped++;
                continue;
            }

            $globalConfigDb->insert([
                'name' => $snakeKey,
                'value' => is_array($currentValue) ? json_encode($currentValue, true) : $currentValue
            ]);
            $existingGlobalLookup[$snakeKey] = true;
            $globalInserted++;
        }
    }

    $console->writeln(sprintf(
        '<comment>[config-migration]</comment> Global config: total=%d, inserted=%d, skipped(existing)=%d',
        $globalTotal,
        $globalInserted,
        $globalSkipped
    ));
    $console->writeln('<info>[config-migration]</info> Migration finished successfully.');
} catch (\Throwable $e) {
    Pt_Commons_MiscUtility::console()->writeln(
        sprintf('<error>[config-migration]</error> Migration failed: %s', $e->getMessage())
    );
    Pt_Commons_LoggerUtility::logError($e->getMessage(), [
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}
