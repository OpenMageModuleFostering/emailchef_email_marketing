<?php

/**
 * Self-test button for custom fields creation in system configuration.
 */
class EMailChef_EMailChefSync_Block_Adminhtml_System_Config_Form_Createfieldsbutton
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Return element html.
     *
     * @param Varien_Data_Form_Element_Abstract $element
     *
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * Generate button html.
     *
     * @return string
     */
    protected function _toHtml()
    {
        $storeId = Mage::app()->getStore();
        $eMailChef_EMailChefSync_Model_Source_Lists = new EMailChef_EMailChefSync_Model_Source_Lists();
        $lists = $eMailChef_EMailChefSync_Model_Source_Lists->toOptionArray($storeId);
        $listId = Mage::getStoreConfig('emailchef_newsletter/emailchef/list');
        $listName = null;
        if($listId){
          foreach($lists as $list){
            if($list['value'] == $listId){
              $listName = $list['label'];
              break;
            }
          }
        }

        $data = array(
            'id' => 'emailchef_createfields_button',
            'label' => $this->helper('adminhtml')->__('Create '.($listName?' in list '.$listName:'').' and map'.(!$listName?' (set a list and save to enable)':'')),
        );

        if(!$listName){
          $data['disabled'] = true;
        }

        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData($data);

        return $button->toHtml();
    }
}
