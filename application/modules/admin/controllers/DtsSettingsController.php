<?php

/**
 * Superseded by the Scheme Config hub — see Admin_SchemeConfigController.
 *
 * Kept as a redirect so existing bookmarks, SOP links and anything else
 * pointing at the old per-scheme settings URL still lands in the right place.
 */
class Admin_DtsSettingsController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $this->redirect('/admin/scheme-config/dts');
    }
}
