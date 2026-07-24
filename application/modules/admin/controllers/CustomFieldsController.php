<?php

// Folded into ePT Global Settings; kept only so old bookmarks still land somewhere.
class Admin_CustomFieldsController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $this->redirect('/admin/global-config#customFieldsConfig');
    }
}
