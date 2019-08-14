<?php

require_once __DIR__.'/../lib/emailchef/vendor/autoload.php';
class EMailChef_EMailChefSync_Model_EMailChef extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('emailchef/emailchef');
    }
}
