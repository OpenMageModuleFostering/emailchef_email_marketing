<?php
/**
 * IndexController.php.
 */
class EMailChef_EMailChefSync_IndexController extends Mage_Core_Controller_Front_Action
{
    /**
     * Predispatch: should set layout area.
     *
     * @return Mage_Core_Controller_Front_Action
     */
    public function preDispatch()
    {
        $config = Mage::getModel('emailchef/config');
        /* @var $config EMailChef_EMailChefSync_Model_Config */

        //if( ! $config->isTestMode()) {
        //    die('Access Denied.');
        //}

        parent::preDispatch();

        return $this;
    }

    /**
     * Default Action.
     */
    public function indexAction()
    {
    }

    /**
     * Clean the Resource Table.
     */
    public function cleanAction()
    {
        return;

        Mage::helper('emailchef')->cleanResourceTable();
    }

    public function showAction()
    {
        return;

        Mage::helper('emailchef')->showResourceTable();
    }
}
