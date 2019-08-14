<?php

class EMailChef_EMailChefSync_Adminhtml_EMailChefbackendController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Default Action.
     */
    public function indexAction()
    {
        $this->loadLayout();
        $this->_title($this->__('eMailChef Jobs'));
        $this->renderLayout();
    }

    /**
     * Run The Job.
     */
    public function runjobAction()
    {
        /** @var $session Mage_Admin_Model_Session */
        $session = Mage::getSingleton('adminhtml/session');
        $id = $this->getRequest()->getParam('id');

        if (!$id) {
            $session->addError(
                Mage::helper('emailchef')->__('Invalid Entity')
            );
        }

        $entity = Mage::getModel('emailchef/job')->load($id);
        if ($entity) {
            Mage::helper('emailchef')->runJob($entity->getId());
        }

        $session->addSuccess(
            Mage::helper('emailchef')->__("Run Job [{$entity->getId()}]")
        );

        $this->_redirect('*/*/index');
    }

    /**
     * Delete a job.
     */
    public function deleteAction()
    {
        /** @var $session Mage_Admin_Model_Session */
        $session = Mage::getSingleton('adminhtml/session');
        $id = $this->getRequest()->getParam('id');

        if (!$id) {
            $session->addError(
                Mage::helper('emailchef')->__('Invalid Entity')
            );
        }

        $entity = Mage::getModel('emailchef/job')->load($id);
        $entity->delete();

        $session->addSuccess(
            Mage::helper('emailchef')->__("Job [{$entity->getId()}] [Deleted]")
        );

        $this->_redirect('*/*/index');
    }
}
