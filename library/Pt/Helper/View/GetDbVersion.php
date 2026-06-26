<?php

class Pt_Helper_View_GetDbVersion extends Zend_View_Helper_Abstract
{
    /**
     * The DB-recorded schema version (system_config.app_version), or null if it
     * can't be read. Compared against the APP_VERSION constant to surface a
     * partially-applied migration as a footer warning.
     */
    public function getDbVersion(): ?string
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            if (!$db) {
                return null;
            }
            $version = $db->fetchOne(
                $db->select()->from('system_config', ['value'])->where('config = ?', 'app_version')
            );
            return ($version !== false && $version !== null && $version !== '') ? (string) $version : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
