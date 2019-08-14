<?php

/**
 * Log.php.
 */
class EMailChef_EMailChefSync_Block_Adminhtml_Log extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_log';
        $this->_blockGroup = 'emailchef';

        $this->_headerText = Mage::helper('emailchef')->__('eMailChef Logs');
        //$this->_addButtonLabel = Mage::helper('emailchef')->__('Add Item');

        parent::__construct();

        $this->_removeButton('add');
    }
}
