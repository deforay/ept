<?php

final class Pt_Commons_SchemeConfig
{
    public static function get($name, bool $useCache = true)
    {
        static $cache = [];
        if ($useCache && array_key_exists($name, $cache)) {
            return $cache[$name];
        }
        // scheme_config is the only source. config.ini is retired: init.sql
        // seeds the built-in schemes and run-once/config-migration.php moved
        // older installs' values into the table.
        $sc = new Application_Model_DbTable_SchemeConfig();
        $result = $sc->getSchemeConfig($name);

        $result = Pt_Commons_JsonUtility::isJSON($result) ? Pt_Commons_JsonUtility::decodeJson($result, true) : $result;

        if ($useCache) {
            $cache[$name] = $result;
        }
        return $result;
    }
}
