<?php

require_once __DIR__.'/../lib/emailchef/vendor/autoload.php';
require_once dirname(__FILE__).'/EMailChefWsImport.php';
require_once dirname(__FILE__).'/Wssend.php';

class EMailChef_EMailChefSync_Model_Observer
{
    const CRON_STRING_PATH = 'crontab/jobs/emailchef_emailchefsync/schedule/cron_expr';

    /**
     * @var EMailChef_EMailChefSync_Model_Config
     */
    protected $_config;

    protected $_beforeSaveCalled = array();
    protected $_afterSaveCalled = array();

    /**
     * Save system config event.
     *
     * @param Varien_Object $observer
     */
    public function saveSystemConfig($observer)
    {
        Mage::getSingleton('adminhtml/session')->setMessages(Mage::getModel('core/message_collection'));

        Mage::getModel('core/config_data')
            ->load(self::CRON_STRING_PATH, 'path')
            ->setValue($this->_getSchedule())
            ->setPath(self::CRON_STRING_PATH)
            ->save();

        Mage::app()->cleanCache();

        $this->configCheck();

        // If there are errors in config, do not progress further as it may be testing old data
        $currentMessages = Mage::getSingleton('adminhtml/session')->getMessages();
        foreach ($currentMessages->getItems() as $msg) {
            if ($msg->getType() != 'success') {
                return;
            }
        }

        $messages = array();

        // Close connection to avoid mysql gone away errors
        $res = Mage::getSingleton('core/resource');
        $res->getConnection('core_write')->closeConnection();

        // Test connection
        $storeId = Mage::app()->getStore();
        $usernameWs = Mage::getStoreConfig('emailchef_newsletter/emailchef/username_ws');
        $passwordWs = Mage::getStoreConfig('emailchef_newsletter/emailchef/password_ws');
        $retConn = Mage::helper('emailchef')->testConnection($usernameWs, $passwordWs, $storeId);
        $messages = array_merge($messages, $retConn);

        // Config tests
        $retConfig = Mage::helper('emailchef')->testConfig();
        $messages = array_merge($messages, $retConfig);

        // Re-open connection to avoid mysql gone away errors
        $res->getConnection('core_write')->getConnection();

        // Add messages from test
        if (count($messages) > 0) {
            foreach ($messages as $msg) {
                $msgObj = Mage::getSingleton('core/message')->$msg['type']($msg['message']);
                Mage::getSingleton('adminhtml/session')->addMessage($msgObj);
            }
        }
    }

    /**
     * Transform system settings option to cron schedule string.
     *
     * @return string
     */
    protected function _getSchedule()
    {
        // Get frequency and offset from posted data
        $data = Mage::app()->getRequest()->getPost('groups');
        $frequency = !empty($data['emailchef']['fields']['emailchef_cron_frequency']['value']) ?
            $data['emailchef']['fields']['emailchef_cron_frequency']['value'] :
            EMailChef_EMailChefSync_Model_Adminhtml_System_Source_Cron_Frequency::HOURLY;
        $offset = !empty($data['emailchef']['fields']['emailchef_cron_offset']['value']) ?
            $data['emailchef']['fields']['emailchef_cron_offset']['value'] :
            0;

        // Get period between calls and calculate explicit hours using this and offset
        $period = EMailChef_EMailChefSync_Model_Adminhtml_System_Source_Cron_Frequency::getPeriod($frequency);
        if ($period === null) {
            Mage::log('eMailChef: Could not find cron frequency in valid list. Defaulted to hourly', Zend_Log::ERR);
            $period = 1;
        }
        $hoursStr = $this->_calculateHourFreqString($period, $offset);

        return "0 {$hoursStr} * * *";
    }

    /**
     * Get comma-separated list of hours in a day spaced by $periodInHours and offset by
     *   $offset hours. Note that if $offset is greater than $periodInHours then it loops (modulo).
     *
     * @param int $periodInHours Hours between each call
     * @param int $offset        Offset (in hours) for each entry
     *
     * @return string Comma-separated list of hours
     */
    private function _calculateHourFreqString($periodInHours, $offset)
    {
        $hours = array();
        // Repeat as many times as the period fits into 24 hours
        for ($n = 0; $n < (24 / $periodInHours); ++$n) {
            $hours[] = $n * $periodInHours + ($offset % $periodInHours);
        }
        $hourStr = implode(',', $hours);

        return $hourStr;
    }


    	/**
         * Observes: customer_customer_authenticated
         *
         * @param type $observer
         * @return \EMailChef_EMailChefSync_Model_Observer
         */
    	public function checkUser($observer)
    	{
    		$model = $observer->getEvent()->getModel();
    		if (empty($model)) $model = $model = $observer->getEvent()->getDataObject();
    		if (isset($GLOBALS["__sl_emailchef_check_user"])) return $this;
    		$GLOBALS["__sl_emailchef_check_user"] = true;

        $listId = Mage::getStoreConfig('emailchef_newsletter/emailchef/list');
        $defaultGroupId = Mage::getStoreConfig('emailchef_newsletter/emailchef/default_group');
        $confirm = Mage::getStoreConfig('emailchef_newsletter/emailchef/require_subscription_confirmation');

        try {
            if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) Mage::log("stato registrazione: " . $stato_registrazione);
            $model = $observer->getEvent()->getModel();
            $storeId = $model->getStoreId(); // Is this always correct??

            // Get from list
            $wsSend = new EMailChefWsSend($storeId);
            $accessKey = $wsSend->loginFromId();
            $getContactFromEmailCommand = new \EMailChef\Command\Api\GetContactFromEmailCommand();
            $contact = $getContactFromEmailCommand->execute($listId, $model->getEmail(), $accessKey);

            // Ensure that before_save does not fire
            $this->_authenticationCalled[$model->getEmail()] = true;
            // Set subscription based on returned $stato_registrazione

            $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($model->getEmail());

            $status = $subscriber->getStatus();

            if($contact->status=='UNSUBSCRIBED' && $status != Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED ){
              $model->setIsSubscribed(0);
              $model->save();
            }
    		} catch (Exception $e) {
    			Mage::logException($e);
    		}

    		return $this;
    	}

    /**
     * Observes Before save, sets the status based on single or double opt-in.
     *
     * @see     newsletter_subscriber_save_before
     *
     * @param   $observer
     */
    public function beforeSave($observer)
    {
        $model = $observer->getEvent()->getDataObject();

        $confirm = Mage::getStoreConfig('emailchef_newsletter/emailchef/require_subscription_confirmation');

        // If change is to subscribe, and confirmation required, set to confirmation pending
        if ($model->getStatus() == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED &&
            $confirm
        ) {
            // Always change the status
            $model->setStatus(Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED);
        }

        // Check whether there is a status to change
        $origModel = Mage::getModel('newsletter/subscriber')->load($model->getId());
        if ($origModel->getStatus() == $model->getStatus()) {
            $model->setDoNotChangeSubscription(true);

            return;
        }
    }

    /**
     * Observes subscription.
     *
     * @see     newsletter_subscriber_save_after
     *
     * @param   $observer
     *
     * @return \EMailChef_EMailChefSync_Model_Observer
     */
    public function sendUser($observer)
    {
        $model = $observer->getEvent()->getDataObject();

        // Ensure that (if called as singleton), this will only get called once per customer
        if (isset($this->_afterSaveCalled[$model->getEmail()])) {
            return $this;
        }
        $this->_afterSaveCalled[$model->getEmail()] = true;

        // If there is no change to status, do not subscribe/unsubscribe
        if ($model->getDoNotChangeSubscription()) {
            return $this;
        }

        // If the status has changed, and it is now unconfirmed, set notification
        if ($model->getStatus() == Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED) {
            Mage::getSingleton('core/session')->addNotice(Mage::helper('emailchef')->__('Your subscription is waiting for confirmation'));
        }

        if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
            Mage::log($model->getData());
        }
        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($model->getEmail());
        $status = $subscriber->getStatus();

        $module = Mage::app()->getRequest()->getModuleName();
        $controller = Mage::app()->getRequest()->getControllerName();
        $action = Mage::app()->getRequest()->getActionName();

        if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
            Mage::log('emailchef: invia utente');
        }

        if (($module == 'customer' and $controller == 'account' and $action == 'createpost') or ($module == 'checkout' and $controller == 'onepage' and $action == 'saveOrder')) {
            if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
                Mage::log('SONO in registrazione');
            }
            /*
             * are recording, monitoring the status of magento subscribe,
             * if you do not result in writing I read status from EMailChef and if
             * they are registered with the subject of magento before continuing
             */
            if (!$status) {
                //rileggo lo status perché potrebbe essere stato modificato dalla precedente chiamata
                $status = Mage::getModel('newsletter/subscriber')->loadByEmail($model->getEmail())->getStatus();
                // se non sono iscritto nemmeno lato emailchef allora posso evitare di andare oltre
                if (!$status) {
                    return $this;
                }
            }
        }

        $listId = Mage::getStoreConfig('emailchef_newsletter/emailchef/list');
        $defaultGroupId = Mage::getStoreConfig('emailchef_newsletter/emailchef/default_group');
        $confirm = Mage::getStoreConfig('emailchef_newsletter/emailchef/require_subscription_confirmation');

        try {
            if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
                Mage::log("STATO ISCRIZIONE: $status");
            }

            $storeId = $observer->getEvent()->getSubscriber()->getStoreId(); // Is this always correct??

            // Get from list
            $wsSend = new EMailChefWsSend($storeId);
            $accessKey = $wsSend->loginFromId();
            $getContactFromEmailCommand = new \EMailChef\Command\Api\GetContactFromEmailCommand();
            $contact = $getContactFromEmailCommand->execute($listId, $model->getEmail(), $accessKey);

            if ($status == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED && !$contact) {
                // send it
            $deleteContactCommand = new \EMailChef\Command\Api\CreateContactCommand();
                $deleteContactCommand->execute($listId, $model->getEmail(), $accessKey);
            // add default list if available
            if ($defaultGroupId) {
                $addEmailsToGroupCommand = new \EMailChef\Command\Api\AddEmailsToGroupCommand();
                $addEmailsToGroupCommand->execute($accessKey, array($model->getEmail()), $listId, $defaultGroupId);
                if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
                    Mage::log('emailchef aggiunta utente a gruppo');
                }
            }
                if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
                    Mage::log('emailchef invio utente');
                }

            // Customer data sync if customerId
            $modelData = $model->getData();
                if (isset($modelData['customer_id'])) {
                    self::setCustomerForDataSync($modelData['customer_id'], $storeId);
                }
            } elseif ($status != Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED && $contact && $contact->status!='UNSUBSCRIBED') {
                // remove it
            $deleteContactCommand = new \EMailChef\Command\Api\DeleteContactCommand();
                $deleteContactCommand->execute($listId, $contact->id, $accessKey);
                if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
                    Mage::log('emailchef cancello utente');
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $this;
    }

    /**
     * Config Check.
     *
     * @return type
     */
    public function configCheck()
    {
        $user = Mage::getStoreConfig('emailchef_newsletter/emailchef/username_ws');
        $password = Mage::getStoreConfig('emailchef_newsletter/emailchef/password_ws');
        $list = Mage::getStoreConfig('emailchef_newsletter/emailchef/list');

        if (!strlen($user) or !strlen($password) or !strlen($list)) {
            $url = Mage::getModel('adminhtml/url');
            $url = $url->getUrl('emailchef/adminhtml_configuration');
            $message = Mage::helper('emailchef')->__('eMailChef configuration is not complete');
            $message = str_replace("href=''", "href='$url'", $message);
            Mage::getSingleton('adminhtml/session')->addWarning($message);

            return;
        }

        $wsimport = new EMailChefWsImport();
        $mapping = $wsimport->getFieldsMapping();
        if (empty($mapping)) {
            $url = Mage::getModel('adminhtml/url');
            $url = $url->getUrl('emailchef/adminhtml_configuration');
            $message = Mage::helper('emailchef')->__('eMailChef fields mapping is not complete');
            $message = str_replace("href=''", "href='$url'", $message);
            Mage::getSingleton('adminhtml/session')->addWarning($message);

            return;
        }
    }

    /**
     * Called on completion of an order (saved during one-page checkout)
     * NOTE: If another checkout is used, this will not be called!
     */
    public function onCheckoutSaveOrder()
    {
        $order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);

        $this->clearAbandonment($order);
        $this->subscribeDuringCheckout($order);
    }

    /**
     * If customer already subscribed, or pending, then set abandoned cart details to null.
     *
     * @param Mage_Sales_Model_Order $order
     */
    public function clearAbandonment($order)
    {
        // Get subscriber status
        $storeId = $order->getStoreId();
        $customerId = $order->getCustomerId();
        $email = $order->getCustomerEmail();
        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($email);
        if ($subscriber === null) {
            return;
        }
        $status = $subscriber->getStatus();

        // Check status and make API request to clear abandonment fields
        if ($status == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED ||
            $status == Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED) {
            //Mage::helper('emailchef')->clearAbandonmentFields($id,$email);
            self::setCustomerForDataSync($customerId, $storeId);
        }
    }

    /**
     * Subscribe the user, during checkout.
     *
     * @param Mage_Sales_Model_Order $order
     */
    public function subscribeDuringCheckout($order)
    {
        // If subscription option chosen, then subscribe
        if (isset($_REQUEST['emailchef_subscribe2']) && $_REQUEST['emailchef_subscribe2']) {
            try {
                Mage::getModel('newsletter/subscriber')->subscribe($order->getCustomerEmail());
            } catch (Exception $e) {
            }
        }
    }

    /**
     * @var bool
     */
    protected $_hasCustomerDataSynced = false;

    /**
     * Attach to sales_order_save_after event.
     *
     * @see     sales_order_save_after
     *
     * @param type $observer
     */
    public function prepareOrderForDataSync($observer)
    {
        if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
            Mage::log('TRIGGERED prepareOrderForDataSync');
        }

        $order = $observer->getEvent()->getOrder();
        /* @var $order Mage_Sales_Model_Order */
        $customerId = $order->getCustomerId();
        //$customer = Mage::getmodel('customer/customer')->load($customerId);
        /* @var $customer Mage_Customer_Model_Customer */
        if ($this->_hasCustomerDataSynced) {
            return; // Don't bother if nothing has updated.
        }

        //$storeId = $customer->getStoreId(); // Is this always correct??
        $storeId = $order->getStoreId();

        if ($customerId) {
            self::setCustomerForDataSync($customerId, $storeId);
            $this->_hasCustomerDataSynced = true;
        }
    }

    /**
     * Attach to customer_save_after even.
     *
     * Track if we've synced this run, only do it once.
     * This event can be triggers 3+ times per run as the customer
     * model is saved! we only want one Sync though.
     *
     * @todo    refactor
     * @observes     customer_save_after
     */
    public function prepareCustomerForDataSync($observer)
    {
        if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
            Mage::log('TRIGGERED prepareCustomerForDataSync');
        }

        $customer = $observer->getEvent()->getCustomer();
        /* @var $customer Mage_Customer_Model_Customer */
        if (!$customer->hasDataChanges() || $this->_hasCustomerDataSynced) {
            if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
                Mage::log('Nothing changed '.$this->_hasCustomerDataSynced);
            }

            return; // Don't bother if nothing has updated.
        }
        $customerId = $customer->getId();
        $storeId = $customer->getStoreId(); // Is this always correct??
        if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
            Mage::log('Prepare for sending '.$customerId);
        }
        /*
         * Possibly getting issues here with store id not being right...
         *
         * @todo possible issue
         *
         * If the customer is saved, how do we know which store to sync with?
         * he could possibly have made sales on multiple websites...
         */
        if ($customerId) {
            self::setCustomerForDataSync($customerId, $storeId);
            $this->_hasCustomerDataSynced = true;
        }
    }

    /**
     * Add customer data to sync table and creates job if required.
     *
     * @param $customerId
     * @param null $storeId
     *
     * @return bool|null
     *
     * @throws Exception
     */
    private static function setCustomerForDataSync($customerId, $storeId = null)
    {
        if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
            Mage::log("TRIGGERED setCustomerForDataSync [StoreID:{$storeId}, CustomerId:{$customerId}]");
        }

        // If no storeId specified, use current store
        if (!isset($storeId)) {
            $storeId = Mage::app()->getStore()->getId();
        }

        if (!$customerId) {
            return false;
        }

        if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
            Mage::log('setCustomerForDataSync preparing to send');
        }

        $helper = Mage::helper('emailchef');
        /* @var $helper EMailChef_EMailChefSync_Helper_Data */
        $config = Mage::getModel('emailchef/config');
        /* @var $config EMailChef_EMailChefSync_Model_Config */
        $lists = Mage::getSingleton('emailchef/source_lists');
        /* @var $lists EMailChef_EMailChefSync_Model_Source_Lists */
        $listID = $config->getEMailChefListId($storeId);

        //$listGuid = $lists->getListGuid($listID, $storeId);
        $defaultGroupId = Mage::getStoreConfig('emailchef_newsletter/emailchef/default_group', $storeId);
        if (!$defaultGroupId) {
            $defaultGroupId = 0;
        }

        // If list is not available, then cancel sync
        if (!$listID) {
            if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
                Mage::log('Could not fetch valid list, so cancelling customer sync');
            }

            return false;
        }

        // If cron export is not enabled, skip data sync for this customer
        if (!$config->isCronExportEnabled($storeId)) {
            return;
        }

        /* @var $job EMailChef_EMailChefSync_Model_Job */

        if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
            Mage::log('setCustomerForDataSync preparing to send 2');
        }

        /*
         *  Only Sync if they are a subscriber!
         */
        if (!$helper->isSubscriber($customerId, $storeId)) {
            return;
        }

        if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
            Mage::log('setCustomerForDataSync preparing to send 3');
        }

        // Set options for those already subscribed (not pending and no opt-in)
        $data = array(
            'emailchefgroupid' => $defaultGroupId,
            'status' => 'queued',
            'store_id' => $storeId,
            'list_id' => $listID,
            'list_guid' => $defaultGroupId,
        );

        // Find a matching job if exists
        $job = Mage::getModel('emailchef/job');
        self::loadMatchingJob($job, $data);
        // If no matching job, set data on new one
        if (!$job->getId()) {
            $job->setData($data);
            $job->setQueueDatetime(gmdate('Y-m-d H:i:s'));
            $job->setAsAutoSync();
        }
        // Save new or existing job
        try {
            $job->save();
            $config->dbLog('Job [Insert] [Group:NO GROUP] ', $job->getId(), $storeId);
        } catch (Exception $e) {
            $config->dbLog('Job [Insert] [FAILED] [NO GROUP] ', $job->getId(), $storeId);
            $config->log($e);
            throw $e;
        }

        // Add task - do this whether or not job is new
        try {
            // Check if task already exists for this customer
            $jobTask = Mage::getModel('emailchef/sync');
            if ($jobTask->getIdByUniqueKey($customerId, $job->getId(), $storeId) == null) {
                // If task does not exist, create and save
                /* @var $jobTask EMailChef_EMailChefSync_Model_Sync */
                $jobTask->setData(array(
                    'store_id' => $storeId,
                    'customer_id' => $customerId,
                    'entity' => 'customer',
                    'job_id' => $job->getId(),
                    'needs_sync' => true,
                    'last_sync' => null,
                ));
                $jobTask->save();
                $config->dbLog("Sync [Insert] [customer] [{$customerId}]", $job->getId(), $storeId);
            }
        } catch (Exception $e) {
            $config->dbLog("Sync [Insert] [customer] [FAILED] [{$customerId}]", $job->getId(), $storeId);
            $config->log($e);
            throw $e;
        }

        return true;
    }

    /**
     * Load job that matches data, or leave job as is.
     *
     * @param EMailChef_EMailChefSync_Model_Job $job
     * @param array                             $data
     */
    public static function loadMatchingJob(&$job, $data)
    {
        $collection = Mage::getModel('emailchef/job')->getCollection();
        foreach ($data as $key => $value) {
            $collection->addFieldToFilter($key, $value);
        }

        if ($collection->getSize() == 0) {
            return;
        }

        $job = $collection->getFirstItem();
    }

    /**
     * Get the config.
     *
     * @reutrn EMailChef_EMailChefSync_Model_Config
     */
    protected function _config()
    {
        if (null === $this->_config) {
            $this->_config = Mage::getModel('emailchef/config');
        }

        return $this->_config;
    }
}
