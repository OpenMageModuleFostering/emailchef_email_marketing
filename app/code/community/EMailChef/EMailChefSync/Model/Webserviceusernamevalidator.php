<?php

class EMailChef_EMailChefSync_Model_Webserviceusernamevalidator  extends Mage_Core_Model_Config_Data
{
    public function save()
    {
        $value = $this->getValue();
        if (strlen($value) == 0) {
            Mage::throwException(Mage::helper('emailchef')->__('Please fill the email'));
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            Mage::throwException(Mage::helper('emailchef')->__('Email is not in the right format'));
        }

        return parent::save();
    }
}
