<?php
/**
 * Collection.php.
 */
class EMailChef_EMailChefSync_Model_Mysql4_Log_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        $this->_init('emailchef/log');
    }
}
