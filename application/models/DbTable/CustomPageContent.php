<?php

class Application_Model_DbTable_CustomPageContent extends Zend_Db_Table_Abstract
{
    protected $_name = 'custom_page_content';
    protected $_primary = 'id';

    public function saveHomePageContent($params)
    {
        try {
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $templates = $params['templates'] ?? 'home';
            $data = [
                'title' => $templates,
                'content' => htmlspecialchars($params['message']),
                'modified_by' => $authNameSpace->admin_id,
                'status' => 'active',
                'modified_date_time' => new Zend_Db_Expr('now()'),
            ];

            /* Check IF Exist or not */
            $sql = $this->select()->where('title like "' . $templates . '"');
            $exist = $this->fetchRow($sql);
            $this->update(['status' => 'inactive'], "title != '" . $templates . "'");
            if (isset($exist) && !empty($exist)) {
                return $this->update($data, 'id = ' . $exist['id']);
            } else {
                return $this->insert($data);
            }
        } catch (Throwable $e) {
            // If any of the queries failed and threw an exception,
            // we want to roll back the whole transaction, reversing
            // changes made in the transaction, even those that succeeded.
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function fetchActiveHtmlHomePage($title = null)
    {
        $sql = $this->getAdapter()->select()->from(['hs' => $this->_name], ['title', 'content']);
        if (isset($title) && !empty($title)) {
            $sql = $sql->where("title like '%" . $title . "%'");
            return $this->getAdapter()->fetchAll($sql);
        } else {
            $sql = $sql->where('status= ? ', 'active');
        }
        return $this->getAdapter()->fetchRow($sql);
    }

    public function fetchAllHtmlHomePage()
    {
        $sql = $this->getAdapter()->select()->from(['hs' => $this->_name], ['title'])->group('title')->order('title ASC');
        return $this->getAdapter()->fetchRow($sql);
    }
}
