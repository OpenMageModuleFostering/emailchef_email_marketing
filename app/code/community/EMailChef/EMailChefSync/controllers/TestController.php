<?php
/**
 * TestController.php.
 */
class EMailChef_EMailChefSync_TestController extends Mage_Core_Controller_Front_Action
{
    /**
     * Predispatch: should set layout area.
     * 
     * This is causing an issue and making 404s, something to do with the install
     * being messed up and the code inside parent method doing something strange!
     *
     * @return Mage_Core_Controller_Front_Action
     */
    public function preDispatch()
    {
        $config = Mage::getModel('emailchef/config');
        /* @var $config EMailChef_EMailChefSync_Model_Config */

        if (!$config->isTestMode()) {
            die('Access Denied.');
        }

        return parent::preDispatch();
    }

    /**
     * Default Action.
     */
    public function indexAction()
    {
        //$this->loadLayout();
        //$this->renderLayout();
        //var_dump(Mage::helper('emailchef')->getAllCustomerAttributes());

        die('done');
    }

    public function SubscriberAction()
    {
        $helper = Mage::helper('emailchef');

        var_dump($helper->isSubscriber(27, 1));
        var_dump($helper->isSubscriber(29, 99));
    }

    /**
     * Test the models..
     */
    public function modelsAction()
    {
        $jobTask = Mage::getModel('emailchef/sync');
        /* @var $jobTask EMailChef_EMailChefSync_Model_Sync */

        $job = Mage::getModel('emailchef/job');
        /* @var $job EMailChef_EMailChefSync_Model_Job */

        $tasks = $jobTask->getSyncItemsCollection();
        foreach ($tasks as $task) {
            var_dump($task->getData());
        }

        foreach ($jobTask->fetchByJobId(0) as $task) {
            var_dump($task->getData());
        }

        var_dump($jobTask->getJob());
    }

}
