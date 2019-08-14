<?php

class EMailChef_EMailChefSync_Model_Mysql4_EMailChef extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {
        $this->_init('emailchef/emailchef', 'emailchef_id');
    }
}
