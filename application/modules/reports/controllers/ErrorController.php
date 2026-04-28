<?php

require_once APPLICATION_PATH . '/modules/admin/controllers/ErrorController.php';

class Reports_ErrorController extends Admin_ErrorController
{
    public function init()
    {
        // Reuse the admin module's error view to avoid duplicating templates.
        $this->view->addScriptPath(APPLICATION_PATH . '/modules/admin/views/scripts/');
    }
}
