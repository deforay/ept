<?php

class Admin_GlobalConfigController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }

    public function indexAction()
    {
        $commonServices = new Application_Service_Common();
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if($this->getRequest()->isPost()){

            $config = new Zend_Config_Ini($file, null,array('allowModifications'=>true));
            $sec = APPLICATION_ENV;
            $config->$sec->map->center=$this->getRequest()->getPost('mapCenter');
            $config->$sec->map->center=$this->getRequest()->getPost('mapCenter');
            $config->$sec->instituteName=$this->getRequest()->getPost('instituteName');
            $config->$sec->evaluation->dts->passPercentage=$this->getRequest()->getPost('dtsPassPercentage');
            $config->$sec->evaluation->dts->documentationScore=$this->getRequest()->getPost('dtsDocumentationScore');
            
            $writer = new Zend_Config_Writer_Ini();
            $writer->setConfig($config)
                   ->setFilename($file)
                   ->write();
                   
            $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);    
            
            $params = $this->_getAllParams();
            $commonServices->updateConfig($params);
        }
        
        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
        
        $assign=$commonServices->getGlobalConfigDetails();
        $this->view->assign($assign);

    }


}

