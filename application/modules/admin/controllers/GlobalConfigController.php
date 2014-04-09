<?php

class Admin_GlobalConfigController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }

    public function indexAction()
    {
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if($this->getRequest()->isPost()){

            $config = new Zend_Config_Ini($file, null,array('allowModifications'=>true));
            $sec = APPLICATION_ENV;
            $config->$sec->map->center=$this->getRequest()->getPost('mapCenter');
            $config->$sec->map->zoom=$this->getRequest()->getPost('mapZoom');
            
            $writer = new Zend_Config_Writer_Ini();
            $writer->setConfig($config)
                   ->setFilename($file)
                   ->write();
                   
            $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);       
        }
        
        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);

    }


}

