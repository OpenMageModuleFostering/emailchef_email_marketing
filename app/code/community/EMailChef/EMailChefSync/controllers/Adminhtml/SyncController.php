<?php

class EMailChef_EMailChefSync_Adminhtml_SyncController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Default Action.
     */
    public function indexAction()
    {
        $this->loadLayout();
        $this->_title($this->__('Sync Queue'));
        $this->renderLayout();
    }

    /**
     * Sync the Entity.
     */
    public function syncAction()
    {
        /** @var $session Mage_Admin_Model_Session */
        $session = Mage::getSingleton('adminhtml/session');
        $id = $this->getRequest()->getParam('id');

        if (!$id) {
            $session->addError(
                Mage::helper('emailchef')->__('Invalid Entity')
            );
        }

        $entity = Mage::getModel('emailchef/sync')->load($id);

        $session->addSuccess(
            Mage::helper('emailchef')->__("Synced Entity [{$entity->getEntity()}]")
        );

        $this->_redirect('*/*/index');
    }
}
