<?php

/**
 * Class EMailChef_EMailChefSync_Model_Adminhtml_System_Source_Fields.
 *
 * Cached EMailChef recipient fields fetched via API
 *
 * There are two levels of caching:
 *   - Within one instantiation of magento, store options in the object
 *   - Magento's cache is used for up to 10 minutes to save hitting the API too often
 */
class EMailChef_EMailChefSync_Model_Adminhtml_System_Source_Fields
{
    const CACHE_LIFETIME = 600; // 10 min

    /**
     * Storage for options array for this run (assuming class used as singleton).
     *
     * @var null|array
     */
    protected $_options = null;

    /**
     * Options getter for EMailChef field mapping drop-down list.
     *
     * @return array
     */
    public function toOptionArray()
    {
        // If in this instantiation of Mage options have been fetched, return them, bypassing even cache
        if ($this->_options !== null) {
            return $this->_options;
        }
        $websiteCode = Mage::app()->getRequest()->getParam('website');
        $storeCode = Mage::app()->getRequest()->getParam('store');

        if ($storeCode) {
            $storeId = Mage::app()->getStore($storeCode)->getId();
            $cacheId = 'emailchef_fields_array_store_'.$storeId;
        } elseif ($websiteCode) {
            $storeId = Mage::app()
                ->getWebsite($websiteCode)
                ->getDefaultGroup()
                ->getDefaultStoreId()
            ;
            $cacheId = 'emailchef_fields_array_store_'.$storeId;
        } else {
            $storeId = null;
            $cacheId = 'emailchef_fields_array';
            //$storeId = Mage::app()->getDefaultStoreView()->getStoreId();
        }

        // Blank option
        $options = array(array('value' => '', 'label' => ''));

        // Attempt to fetch options from cache (handles invalidation after CACHE_LIFETIME)
        if (Mage::app()->useCache('config') && (false !== ($data = Mage::app()->getCache()->load($cacheId)))) {
            $options = unserialize($data);
        } else {
            // If cache is invalid, make request to EMailChef via API
            $wsSend = new EMailChefWsSend($storeId);
            $accessKey = $wsSend->loginFromId();
            if ($accessKey !== false) {
                $fields = $wsSend->getFields($accessKey);
                if ($fields !== null) {
                    foreach ($fields as $field) {
                        $options[] = array(
                            //'value' => $field->id,
                            'value' => $field->place_holder,
                            'label' => $field->name, //Mage::helper('adminhtml')->__($label)
                        );
                    }
                }
                // Only store a persistent cache of entries if there was a successful response from EMailChef
                Mage::app()->getCache()->save(serialize($options), $cacheId, array(), self::CACHE_LIFETIME);
            } else {
                // Force options to be empty so that nothing is saved
                // (thus defaults or saved values will still be available)
                $options = array();
            }
        }

        // Whether the cache was used, or a call was made (successfully or otherwise), store the result for this run
        $this->_options = $options;

        return $options;
    }
}
