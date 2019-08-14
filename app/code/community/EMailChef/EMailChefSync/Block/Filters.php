<?php
/**
 * Filters.php.
 * 
 * Adminhtml block for the filters section
 */
class EMailChef_EMailChefSync_Block_Filters extends Mage_Core_Block_Template
{
    public function _toHtml()
    {
        return parent::_toHtml();
    }

    /**
     * Get an array of all stores.
     * 
     * @return array
     */
    protected function _getStoresArray()
    {
        $config = Mage::getModel('emailchef/config');
        /* @var $config EMailChef_EMailChefSync_Model_Config */
        return $config->getStoreArray();
    }
}
