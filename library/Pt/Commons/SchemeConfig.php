<?php



final class Pt_Commons_SchemeConfig
{
    public static function get($name, bool $useCache = true)
    {
        static $cache = [];
        if ($useCache && array_key_exists($name, $cache)) {
            return $cache[$name];
        }
        $sc = new Application_Model_DbTable_SchemeConfig();
        $result = $sc->getSchemeConfig($name);

        // If no result from database, check config.ini
        if ($result === null) {
            try {
                $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
                if (file_exists($file)) {
                    $config = new Zend_Config_Ini($file, APPLICATION_ENV);
                    // Handle nested config values (e.g., "evaluation.dts.passPercentage")
                    if (str_contains($name, '.')) {
                        $keys = explode('.', $name);
                        $value = $config;
                        foreach ($keys as $key) {
                            if (isset($value->$key)) {
                                $value = $value->$key;
                            } else {
                                $value = null;
                                break;
                            }
                        }
                        $result = $value;
                    } else {
                        // Direct config key
                        $result = isset($config->$name) ? $config->$name : null;
                        if (!$result)
                            $result = isset($config->evaluation->$name) ? $config->evaluation->$name : null;
                    }
                }
            } catch (Throwable $e) {
                // Log error if needed
                error_log("Error reading config.ini: " . $e->getMessage());
            }
        }

        $result = Pt_Commons_JsonUtility::isJSON($result) ? Pt_Commons_JsonUtility::decodeJson($result, true) : $result;

        if ($result instanceof Zend_Config) {
            $resultArray = $result->toArray();
            if (count($resultArray) === 1) {
                $firstValue = reset($resultArray);
                $result = (is_scalar($firstValue) || $firstValue === null) ? $firstValue : $resultArray;
            } else {
                $result = $resultArray;
            }
        }

        if ($useCache) {
            $cache[$name] = $result;
        }
        return $result;
    }
}
