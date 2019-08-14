<?php

/**
 * Provide clone model that specifies custom customer attributes as prefixes to be
 * cloned from single original field.
 *
 * Class EMailChef_EMailChefSync_Model_Adminhtml_System_Clone_Mappings_Custom
 */
class EMailChef_EMailChefSync_Model_Adminhtml_System_Clone_Mappings_Custom
    extends Mage_Core_Model_Config_Data
{
    /**
     * Get fields prefixes.
     *
     * @return array
     */
    public function getPrefixes()
    {
        $customerAttributes = Mage::helper('emailchef/customer')->getCustomCustomerAttrCollection();

        $prefixes = array();
        foreach ($customerAttributes as $attribute) {
            /* @var $attribute Mage_Eav_Model_Entity_Attribute */
            $prefixes[] = array(
                'field' => $attribute->getAttributeCode().'_',
                'label' => $attribute->getFrontend()->getLabel(),
            );
        }

        return $prefixes;
    }
}
