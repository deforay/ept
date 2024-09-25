<?php

class Admin_CustomFieldsController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
        if ($request->isPost()) {
            $customField1 = $this->_getParam('customField1', '');
            $customField2 = $this->_getParam('customField2', '');
            $haveCustom = $this->_getParam('haveCustom', 'no');
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $customFieldInConfig = $globalConfigDb->getValue('custom_field_1');

            if ($haveCustom == 'yes') {
                $globalConfigDb->updateConfigDetails(array('custom_field_1' => $customField1));
                $globalConfigDb->updateConfigDetails(array('custom_field_2' => $customField2));
            } else {
                $globalConfigDb->updateConfigDetails(array('custom_field_1' => ''));
                $globalConfigDb->updateConfigDetails(array('custom_field_2' => ''));
            }

            $globalConfigDb->updateConfigDetails(array('custom_field_needed' => $haveCustom));
        }

        $this->view->customField1 = $globalConfigDb->getValue('custom_field_1');
        $this->view->customField2 = $globalConfigDb->getValue('custom_field_2');
        $this->view->haveCustom = $globalConfigDb->getValue('custom_field_needed');
    }
}
