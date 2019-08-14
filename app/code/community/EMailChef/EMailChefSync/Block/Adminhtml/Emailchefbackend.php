<?php

/**
 * EMailChefbackend.php.
 */
class EMailChef_EMailChefSync_Block_Adminhtml_Emailchefbackend extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_emailchef';
        $this->_blockGroup = 'emailchef';

        $this->_headerText = Mage::helper('emailchef')->__('eMailChef Scheduled Tasks');
        //$this->_addButtonLabel = Mage::helper('emailchef')->__('Add Item');

        parent::__construct();

        $this->_removeButton('add');
    }
}
