<?php

class DownloadController extends Zend_Controller_Action
{

    public function init()
    {
    }

    public function preDispatch()
    {
    }

    public function indexAction()
    {
        $this->view->filePath = base64_decode($this->_getParam('filepath'));
        //$this->view->filePath = "../downloads/reports/EID2018-I/EID2018-I-summary.pdf";
        $this->_helper->layout()->disableLayout();
        //$this->_helper->viewRenderer->setNoRender(true);

    }
}
