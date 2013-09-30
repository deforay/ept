<?php

class CommonController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('check-duplicate', 'html')
                   ->initContext();
    }

    public function indexAction()
    {
        // action body
    }

    public function sendMailAction()
    {
        
    }

    public function checkDuplicateAction()
    {
        if (!$this->_hasParam('tableName')) {
            $this->view->data = "";
        } else {
            $params = $this->_getAllParams();
            $commonServices = new Application_Service_Common();
            $this->view->data = $commonServices->checkDuplicate($params);
        }        
    }


}





