<?php

require_once dirname(__FILE__).'/../../Model/EMailChefWsImport.php';
require_once dirname(__FILE__).'/../../Model/Wssend.php';
class EMailChef_EMailChefSync_Adminhtml_ViewdatatransferlogController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->loadLayout()->renderLayout();
    }

    public function searchAction()
    {
        $this->loadLayout()->renderLayout();
    }
}
