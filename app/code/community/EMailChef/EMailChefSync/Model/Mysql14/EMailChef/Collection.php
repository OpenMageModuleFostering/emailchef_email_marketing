<?php

class EMailChef_EMailChefSync_Model_Mysql4_EMailChef_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        //parent::__construct();
        $this->_init('emailchef/emailchef');
    }
}
