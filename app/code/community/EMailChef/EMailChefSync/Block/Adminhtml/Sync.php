<?php

/**
 * Sync.php.
 */
class EMailChef_EMailChefSync_Block_Adminhtml_Sync extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_sync';
        $this->_blockGroup = 'emailchef';

        $this->_headerText = Mage::helper('emailchef')->__('eMailChef Task Data');
        //$this->_addButtonLabel = Mage::helper('emailchef')->__('Add Item');

        parent::__construct();

        $this->_removeButton('add');
    }
}
