<?php
/**
 * Config.php.
 *
 * Central config model
 */
class EMailChef_EMailChefSync_Model_Config
{
    const XML_LOG_ENABLE = 'emailchef_newsletter/emailchef/enable_log';
    const XML_CRON_EXPORT_ENABLE = 'emailchef_newsletter/emailchef/enable_cron_export';
    const XML_EMAILCHEF_USERNAME = 'emailchef_newsletter/emailchef/username_ws';
    const XML_EMAILCHEF_PASSWORD = 'emailchef_newsletter/emailchef/password_ws';
    const XML_EMAILCHEF_LIST_ID = 'emailchef_newsletter/emailchef/list';
    const XML_EMAILCHEF_DEFAULT_GROUP_ID = 'emailchef_newsletter/emailchef/default_group';
    const XML_SUBSCRIBE_IN_CHECKOUT = 'emailchef_newsletter/emailchef/enable_subscribe_in_checkout';
    const XML_REQ_SUBSCRIPTION_CONF = 'emailchef_newsletter/emailchef/require_subscription_confirmation';
    const XML_CRON_FREQ = 'emailchef_newsletter/emailchef/emailchef_cron_frequency';
    const XML_TEST_MODE_ENABLE = 'emailchef_newsletter/emailchef/enable_testmode';
    const XML_ORDER_STATUSES = 'emailchef_newsletter/emailchef/qualifying_order_statuses';

    const XML_MAPPING_SECTION = 'emailchef_newsletter/emailchef_mapping';
    const XML_CUSTOM_MAPPING_SECTION = 'emailchef_newsletter/emailchef_mapping_custom';

    /**
     * Is test mode enabled.
     *
     * @param int
     *
     * @return bool
     */
    public function isTestMode($storeId = null)
    {
        return (bool) Mage::getStoreConfig(self::XML_TEST_MODE_ENABLE, $storeId);
    }

    /**
     * Is the log enabled?
     *
     * @param   int
     *
     * @return bool
     */
    public function isLogEnabled($storeId = null)
    {
        return (int) Mage::getStoreConfig(self::XML_LOG_ENABLE, $storeId);
    }

    /**
     * Write a log entry if enabled.
     *
     * @param   string
     * @param   int
     *
     * @return bool
     */
    public function log($message, $storeId = null)
    {
        if (!$this->isLogEnabled($storeId)) {
            return;
        }

        Mage::log($message, null, 'emailchef.log');
    }

    /**
     * Get qualifying order statuses for inclusion in order totals.
     *
     * @param  int
     *
     * @return array Array of statuses
     */
    public function getQualifyingOrderStatuses($storeId = null)
    {
        // Get from config storage
        $statusesStr = Mage::getStoreConfig(self::XML_ORDER_STATUSES, $storeId);

        if ($statusesStr === null || $statusesStr === '') {
            return array();
        }

        // Split up comma separated values
        return explode(',', $statusesStr);
    }

    /**
     * Get default qualifying order stated for inclusion in order totals.
     *
     * @return array
     */
    public function getDefaultQualifyingStates()
    {
        return array('complete', 'closed', 'processing');
    }

    /**
     * Write a log entry if enabled.
     *
     * @param   string
     * @param   int
     * @param   int
     * @param   string
     * @param   string
     *
     * @return bool
     */
    public function dbLog($info, $jobId = 0, $storeId = null, $status = 'DEBUG', $type = 'DEBUG')
    {
        if (!$this->isLogEnabled($storeId)) {
            return;
        }

        if (!isset($storeId)) {
            $storeId = Mage::app()->getStore()->getId();
        }

        $log = Mage::getModel('emailchef/log');
        /* @var $log EMailChef_EMailChefSync_Model_Log */
        $log->setData(array(
            'store_id' => $storeId,
            'job_id' => $jobId,
            'type' => $type,
            'status' => $status,
            'data' => $info,
            'event_time' => date('Y-m-d H:i:s'),
        ));

        try {
            $log->save();
        } catch (Exception $e) {
            $this->log($e->getMessage(), $storeId);
        }
    }

    /**
     * Disable Magnetos Newsletter Subscription Notifiactions??
     *
     * @param   int
     *
     * @return bool
     */
    public function isNewsletterNotificationDisabled($storeId = null)
    {
        return false;
    }

    /**
     * Is the cron enabled?
     *
     * @param   int
     *
     * @return int
     */
    public function isCronExportEnabled($storeId = null)
    {
        return (int) Mage::getStoreConfig(self::XML_CRON_EXPORT_ENABLE, $storeId);
    }

    /**
     * Get the list ID.
     *
     * @param   int
     *
     * @return int
     */
    public function getEMailChefListId($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_EMAILCHEF_LIST_ID, $storeId);
    }

    /**
     * Get the default Group ID.
     *
     * @param   int
     *
     * @return int
     */
    public function getEMailChefDefaultGroupId($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_EMAILCHEF_DEFAULT_GROUP_ID, $storeId);
    }

    /**
     * Get the username from Config.
     *
     * @param   int
     *
     * @return string
     */
    public function getUsername($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_EMAILCHEF_USERNAME, $storeId);
    }

    /**
     * Get the password from Config.
     *
     * @param   int
     *
     * @return string
     */
    public function getPassword($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_EMAILCHEF_PASSWORD, $storeId);
    }

    /**
     * Is Subscribe in checkout enabled?
     *
     * @param   int
     *
     * @return int
     */
    public function isSubscribeInCheckout($storeId = null)
    {
        return (int) Mage::getStoreConfig(self::XML_SUBSCRIBE_IN_CHECKOUT, $storeId);
    }

    /**
     * Is Require Subscription Confirmation set in config?
     *
     * @param  int
     *
     * @return int
     */
    public function isRequireSubscriptionConfirmation($storeId = null)
    {
        return (int) Mage::getStoreConfig(self::XML_REQ_SUBSCRIPTION_CONF, $storeId);
    }

    /**
     * Get the cron freq settings.
     *
     * @param   int
     *
     * @return string
     */
    public function getCronFrequency($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_CRON_FREQ, $storeId);
    }

    /**
     * Get Field Mapping.
     *
     * @param   int
     *
     * @return array
     */
    public function getFieldsMapping($storeId = null)
    {
        // Get standard mappings
        $mappingMain = Mage::getStoreConfig(self::XML_MAPPING_SECTION, $storeId);
        // Get mappings for custom customer attributes
        $mappingCustom = Mage::getStoreConfig(self::XML_CUSTOM_MAPPING_SECTION, $storeId);

        if ($mappingCustom === null) {
            $mappingCustom = array();
        }

        return array_merge($mappingMain, $mappingCustom);

        /*$return = array();

        foreach(Mage::getStoreConfig(self::XML_MAPPING_SECTION, $storeId) as $key => $field) {
            var_dump($key);
            var_dump($field);
        }

        return $return;*/
    }

    /**
     * Get the name of the Sync Table.
     *
     * @return string
     */
    public function getSyncTableName()
    {
        return Mage::getSingleton('core/resource')->getTableName('emailchef/sync');
    }

    /**
     * Get the name of the Jobs Table.
     *
     * @return string
     */
    public function getJobsTableName()
    {
        return Mage::getSingleton('core/resource')->getTableName('emailchef/job');
    }

    /**
     * Get an array of Stores, for use in a dropdown.
     *
     * array(
     *     id => code
     * )
     *
     * @return array
     */
    public function getStoreArray()
    {
        //$storeModel = Mage::getSingleton('adminhtml/system_store');
        /* @var $storeModel Mage_Adminhtml_Model_System_Store */
        //$websiteCollection = $storeModel->getWebsiteCollection();
        //$groupCollection = $storeModel->getGroupCollection();
        //$storeCollection = $storeModel->getStoreCollection();
        $storesArr = array();

        /*$defaultStoreId = Mage::app()->getDefaultStoreView()->getStoreId();
        $storesArr[$defaultStoreId] = array(
            'id'    => $defaultStoreId,
            'code'  => Mage::app()->getDefaultStoreView()->getCode(),
            'name'  => Mage::app()->getDefaultStoreView()->getName(),
        );*/

        $storesArr[0] = array(
            'id' => 0,
            'code' => 'default',
            'name' => 'Default',
        );

        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = $group->getStores();
                foreach ($stores as $store) {
                    /* @var $store Mage_Core_Model_Store */
                    $storesArr[$store->getId()] = array(
                        'id' => $store->getId(),
                        'code' => $store->getCode(),
                        'name' => $store->getName(),
                    );
                }
            }
        }

        return $storesArr;
    }

    /**
     * Get an array of all store ids.
     *
     * @reutrn  array
     */
    public function getAllStoreIds()
    {
        $ids = array();

        $allStores = Mage::app()->getStores();
        foreach ($allStores as $storeId => $val) {
            $ids[] = Mage::app()->getStore($storeId)->getId();
        }

        return $ids;
    }
}
