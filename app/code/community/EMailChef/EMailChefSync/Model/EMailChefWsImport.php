<?php
/**
 * EMailChefWsImport.php.
 */
class EMailChefWsImport
{
    /**
     * @var EMailChef_EMailChefSync_Model_Config
     */
    protected $_config;
    /**
     * @var int
     */
    protected $storeId;

    /**
     * Constructor.
     */
    public function __construct($storeId = null)
    {
        $this->setStoreId($storeId);

        $this->_config = $config = Mage::getModel('emailchef/config');
    }

    /**
     * Set the store ID.
     *
     * @param int
     */
    public function setStoreId($id)
    {
        $this->storeId = $id;

        return $this;
    }

    /**
     * Create a New Group.
     *
     * @todo    CHECK THE API - might have been updated??
     * The API states the signature of this method is:
     *
     *      CreateGroup(int idList, int listGUID, string newGroupName)
     *
     * @param type $newGroup
     * @param type $authKey
     *
     * @return bool
     */
    public function creaGruppo($newGroup, $authKey)
    {
        try {
            $createSegmentCommand = new \EMailChef\Command\Api\CreateSegmentCommand();
            $result = $createSegmentCommand->execute($authKey, $newGroup['idList'], $newGroup['newGroupName'], $newGroup['newGroupName']);
            if ($this->_config()->isLogEnabled($this->storeId)) {
                $this->_config()->dbLog(sprintf(
                    'eMailChef: Create a new Group [%s] [List:%s] [%s]',
                    $newGroup['newGroupName'],
                    $newGroup['listGUID'],
                    $result
                ));
            }

            return $result;
        } catch (Exception $e) {
            Mage::log($e->getMessage(), 0);
            $errorDescription = $e->getMessage();
        }
    }

    /**
     * GetNlList.
     *
     * KNOWN RESTRICTION
     * Characters & and " are not escaped in returned response, so please avoid these
     * characters in names of lists and groups otherwise you will experience some problems due to an invalid returned XML
     *
     * @todo    parse the XML response correctly and return something nice.
     *
     * @return string
     */
    public function GetNlList($accessKey, $withGroups = false)
    {
        try {
            $getListsCommand = new \EMailChef\Command\Api\GetListsCommand();
            $result = $getListsCommand->execute($accessKey, $withGroups);
            if ($this->_config()->isLogEnabled($this->storeId)) {
                $this->_config()->log($result, 0);
            }

            return $result;
        } catch (Exception $e) {
            Mage::log($e->getMessage(), 0);
            $errorDescription = $e->getMessage();
        }
    }

    /**
     * startImportProcesses.
     *
     * @param type $processData
     *
     * @return int|bool
     */
    public function startImportProcesses($processData, $accessKey)
    {
        try {
            $importContactsInGroupCommand = new \EMailChef\Command\Api\ImportContactsInGroupCommand();
            $result = $importContactsInGroupCommand->execute($accessKey, $processData['customers'], $processData['idList'], $processData['groupsIDs']);
            if ($this->_config()->isLogEnabled($this->storeId)) {
                $this->_config()->log($result, 0);
            }

            return $result;
        } catch (Exception $e) {
            Mage::log($e->getMessage(), 0);
            $errorDescription = $e->getMessage();
        }
    }

    /**
     * Get filtered customers.
     *
     * @todo    refactor
     *
     * @param
     * @param   int
     *
     * @return array
     */
    public function getCustomersFiltered($request, $storeId = null)
    {
        $TIMEZONE_STORE = new DateTimeZone(Mage::getStoreConfig('general/locale/timezone'));
        $TIMEZONE_UTC = new DateTimeZone('UTC');

        //inizializzo l'array dei clienti
        $customersFiltered = array();

        if (!$request->getRequest()->getParam('emailchefCustomerFilteredMod')) {
            //ottengo la collection con tutti i clienti
            $customerCollection = Mage::getModel('customer/customer')
                ->getCollection()
                ->addAttributeToSelect('entity_id')
                ->addAttributeToSelect('group_id')
                ->addAttributeToSelect('created_at')
                ->addAttributeToSelect('store_id')
                //->getSelect()->query()
            ;
            /*
             * If StoreID = 0 we will not bother to filter...
             */
            if (isset($storeId) && !empty($storeId)) {
                $customerCollection->addAttributeToFilter('store_id', array(
                    'eq' => $storeId,
                ));
            }
            $customerCollection = $customerCollection->getSelect()->query();

            while ($row = $customerCollection->fetch()) {
                $customersFiltered[] = $row;
            }

            // if required, select only those that are (or are not) subscribed in Magento
            if ($request->getRequest()->getParam('emailchefSubscribed') > 0) {
                // Base status on option (1 -> must be subscribed. 2 -> must NOT be subscribed
                if ($request->getRequest()->getParam('emailchefSubscribed') == 1) {
                    $expectedStatus = true;
                } else {
                    $expectedStatus = false;
                }
                // Filter list of customers by expected subscription status
                $tempSubscribed = array();
                foreach ($customersFiltered as $customer) {
                    $customerItem = Mage::getModel('customer/customer')->load($customer['entity_id']);
                    $subscriptionStatus = Mage::getModel('newsletter/subscriber')->loadByCustomer($customerItem)->isSubscribed();
                    if ($subscriptionStatus === $expectedStatus) {
                        $tempSubscribed[] = $customer;
                    }
                }

                $customersFiltered = self::intersectByEntityId($tempSubscribed, $customersFiltered);
            }
            /*
             * FILTER 1 PURCHASED: Depending on whether or not customer has made ​​purchases
             *   0 = all, 1 = those who purchased, 2 = someone who has never purchased
             */
            $count = 0;
            $result = array();
            $tempPurchased = array();
            $tempNoPurchased = array();

            if ($request->getRequest()->getParam('emailchefCustomers') > 0) {
                foreach ($customersFiltered as $customer) {
                    $result[] = $customer;
                    // Filter orders based on customer id
                    $orders = Mage::getResourceModel('sales/order_collection')
                        ->addAttributeToFilter('customer_id', $result[$count]['entity_id']);
                    Mage::helper('emailchef/order')->addStatusFilterToOrders($orders);

                    // Add customer to either purchased or non-purchased array based on whether any orders
                    if ($orders->getData()) {
                        $tempPurchased[] = $result[$count];
                    } else {
                        $tempNoPurchased[] = $result[$count];
                    }
                    //unsetto la variabile
                    unset($orders); //->unsetData();
                    ++$count;
                }

                if ($request->getRequest()->getParam('emailchefCustomers') == 1) {
                    $customersFiltered = self::intersectByEntityId($tempPurchased, $customersFiltered);
                } elseif ($request->getRequest()->getParam('emailchefCustomers') == 2) {
                    $customersFiltered = self::intersectByEntityId($tempNoPurchased, $customersFiltered);
                }
            }
            /*
             * FILTER 1 BY PRODUCT: Based on whether customer purchased a specific product
             */
            $count = 0;
            $result = array();
            $tempProduct = array();

            if ($request->getRequest()->getParam('emailchefProductSku')) {
                foreach ($customersFiltered as $customer) {
                    $result[] = $customer;

                    // Filter orders based on customer id
                    $orders = Mage::getResourceModel('sales/order_collection')
                        ->addAttributeToFilter('customer_id', $result[$count]['entity_id']);
                    Mage::helper('emailchef/order')->addStatusFilterToOrders($orders);

                    $purchasedProduct = 0;
                    $emailchefProductId = Mage::getModel('catalog/product')
                        ->getIdBySku($request->getRequest()->getParam('emailchefProductSku'));

                    foreach ($orders->getData() as $order) {
                        $orderIncrementId = $order['increment_id'];

                        //carico i dati di ogni ordine
                        $orderData = Mage::getModel('sales/order')->loadByIncrementID($orderIncrementId);
                        $items = $orderData->getAllItems();
                        $ids = array();
                        foreach ($items as $itemId => $item) {
                            $ids[] = $item->getProductId();
                        }

                        if (in_array($emailchefProductId, $ids)) {
                            $purchasedProduct = 1;
                        }
                    }

                    //aggiungo il cliente ad un determinato array in base a se ha ordinato o meno
                    if ($purchasedProduct == 1) {
                        $tempProduct[] = $result[$count];
                    }

                    //unsetto la variabile
                    unset($orders); //->unsetData();

                    ++$count;
                }

                $customersFiltered = self::intersectByEntityId($tempProduct, $customersFiltered);
            }
            /*
             * FILTER 3 BY CATEGORY: Depending on whether bought at least one product in a given category
             */
            $count = 0;
            $result = array();
            $tempCategory = array();
            if ($request->getRequest()->getParam('emailchefCategoryId') > 0) {
                foreach ($customersFiltered as $customer) {
                    $result[] = $customer;
                    // Filter orders based on customer id
                    $orders = Mage::getResourceModel('sales/order_collection')
                        ->addAttributeToFilter('customer_id', $result[$count]['entity_id']);
                    Mage::helper('emailchef/order')->addStatusFilterToOrders($orders);

                    foreach ($orders->getData() as $order) {
                        $orderIncrementId = $order['increment_id'];

                        // Load data for each order (very slow)
                        $orderData = Mage::getModel('sales/order')->loadByIncrementID($orderIncrementId);
                        $items = $orderData->getAllItems();
                        /*
                         * Category ID, and it's descendants
                         */
                        $searchCategories = Mage::helper('emailchef')->getSubCategories($request->getRequest()->getParam('emailchefCategoryId'));
                        foreach ($items as $product) {
                            $_prod = Mage::getModel('catalog/product')->load($product->getProductId()); // need to load full product for cats.
                            $productCategories = Mage::getResourceSingleton('catalog/product')->getCategoryIds($_prod);
                            $matchingCategories = array_intersect($productCategories, $searchCategories);
                            if (is_array($matchingCategories) && !empty($matchingCategories)) {
                                $tempCategory[] = $result[$count];
                                break 2;
                            }
                        }
                    }
                    unset($orders);
                    ++$count;
                }
                $customersFiltered = self::intersectByEntityId($tempCategory, $customersFiltered);
            }

            /*
             * FILTER 4 CUSTOMER GROUP
             */
            $count = 0;
            $result = array();
            $tempGroup = array();

            if ($request->getRequest()->getParam('emailchefCustomerGroupId') > 0) {
                foreach ($customersFiltered as $customer) {
                    if ($customer['group_id'] == $request->getRequest()->getParam('emailchefCustomerGroupId')) {
                        $tempGroup[] = $customer;
                    }
                }

                $customersFiltered = self::intersectByEntityId($tempGroup, $customersFiltered);
            }
            //FINE FILTRO 4 GRUPPO DI CLIENTI: testato ok

            //FILTRO 5 PAESE DI PROVENIENZA
            $count = 0;
            $result = array();
            $tempCountry = array();

            if ($request->getRequest()->getParam('emailchefCountry') != '0') {
                foreach ($customersFiltered as $customer) {
                    //ottengo la nazione del primary billing address
                    $customerItem = Mage::getModel('customer/customer')->load($customer['entity_id']);
                    $customerAddress = $customerItem->getPrimaryBillingAddress();
                    $countryId = $customerAddress['country_id'];

                    if ($countryId == $request->getRequest()->getParam('emailchefCountry')) {
                        $tempCountry[] = $customer;
                    }

                    //unsetto la variabile
                    unset($customerItem); //->unsetData();
                }

                $customersFiltered = self::intersectByEntityId($tempCountry, $customersFiltered);
            }
            //FINE FILTRO 5 PAESE DI PROVENIENZA: testato ok

            //FILTRO 6 CAP DI PROVENIENZA
            $count = 0;
            $result = array();
            $tempPostCode = array();

            if ($request->getRequest()->getParam('emailchefPostCode')) {
                foreach ($customersFiltered as $customer) {
                    //ottengo la nazione del primary billing address
                    $customerItem = Mage::getModel('customer/customer')->load($customer['entity_id']);
                    $customerAddress = $customerItem->getPrimaryBillingAddress();
                    $postCode = $customerAddress['postcode'];

                    if ($postCode == $request->getRequest()->getParam('emailchefPostCode')) {
                        $tempPostCode[] = $customer;
                    }

                    //unsetto la variabile
                    unset($customerItem); //->unsetData();
                }

                $customersFiltered = self::intersectByEntityId($tempPostCode, $customersFiltered);
            }
            //FINE FILTRO 6 CAP DI PROVENIENZA: testato ok

            //FILTRO 7 DATA CREAZIONE CLIENTE
            $count = 0;
            $result = array();
            $tempDate = array();

            if ($request->getRequest()->getParam('emailchefCustomerStartDate') || $request->getRequest()->getParam('emailchefCustomerEndDate')) {
                foreach ($customersFiltered as $customer) {
                    $createdAt = $customer['created_at'];
                    $createdAt = new DateTime($createdAt, $TIMEZONE_UTC);
                    $createdAt->setTimezone($TIMEZONE_STORE);
                    $createdAt = (string) $createdAt->format('Y-m-d H:i:s');
                    $filterStart = '';
                    $filterEnd = '';

                    if ($request->getRequest()->getParam('emailchefCustomerStartDate')) {
                        $date = Zend_Locale_Format::getDate(
                            $request->getRequest()->getParam('emailchefCustomerStartDate'),
                            array(
                                'locale' => Mage::app()->getLocale()->getLocale(),
                                'date_format' => Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
                                'fix_date' => true,
                            )
                        );
                        $date['month'] = str_pad($date['month'], 2, 0, STR_PAD_LEFT);
                        $date['day'] = str_pad($date['day'], 2, 0, STR_PAD_LEFT);
                        $filterStart = "{$date['year']}-{$date['month']}-{$date['day']} 00:00:00";
                    }
                    if ($request->getRequest()->getParam('emailchefCustomerEndDate')) {
                        $date = Zend_Locale_Format::getDate(
                            $request->getRequest()->getParam('emailchefCustomerEndDate'),
                            array(
                                'locale' => Mage::app()->getLocale()->getLocale(),
                                'date_format' => Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
                                'fix_date' => true,
                            )
                        );
                        $date['month'] = str_pad($date['month'], 2, 0, STR_PAD_LEFT);
                        $date['day'] = str_pad($date['day'], 2, 0, STR_PAD_LEFT);
                        $filterEnd = "{$date['year']}-{$date['month']}-{$date['day']} 23:59:59";
                    }
                    if ($filterStart && $filterEnd) {
                        //compreso tra start e end date
                        if ($createdAt >= $filterStart and $createdAt <= $filterEnd) {
                            $tempDate[] = $customer;
                        }
                    } elseif ($filterStart) {
                        // >= di start date
                        if ($createdAt >= $filterStart) {
                            $tempDate[] = $customer;
                        }
                    } else {
                        // <= di end date
                        if ($createdAt <= $filterEnd) {
                            $tempDate[] = $customer;
                        }
                    }
                }

                $customersFiltered = self::intersectByEntityId($tempDate, $customersFiltered);
            }
            //FINE FILTRO 7 DATA CREAZIONE CLIENTE: testato ok

            //FILTRO 8 TOTALE ACQUISTATO
            $count = 0;
            $result = array();
            $tempTotal = array();

            if ($request->getRequest()->getParam('emailchefTotalAmountValue') > 0) {
                foreach ($customersFiltered as $customer) {
                    $result[] = $customer;

                    //filtro gli ordini in base al customer id
                    $orders = Mage::getModel('sales/order')
                        ->getCollection()
                        ->addAttributeToFilter('customer_id', $result[$count]['entity_id'])
                    ;

                    $totalOrdered = 0;

                    foreach ($orders->getData() as $order) {
                        if (isset($order['status']) && !in_array($order['status'], array('closed', 'complete', 'processing'))) {
                            continue;
                        }
                        $totalOrdered += $order['subtotal'];
                    }

                    if ($totalOrdered == $request->getRequest()->getParam('emailchefTotalAmountValue')
                        && $request->getRequest()->getParam('emailchefTotalAmountCond') == 'eq') {
                        $tempTotal[] = $result[$count];
                    }

                    if ($totalOrdered > $request->getRequest()->getParam('emailchefTotalAmountValue')
                        && $request->getRequest()->getParam('emailchefTotalAmountCond') == 'gt') {
                        $tempTotal[] = $result[$count];
                    }

                    if ($totalOrdered < $request->getRequest()->getParam('emailchefTotalAmountValue')
                        && $request->getRequest()->getParam('emailchefTotalAmountCond') == 'lt') {
                        $tempTotal[] = $result[$count];
                    }

                    ++$count;

                    //unsetto la variabile
                    unset($orders); //->unsetData();
                }

                $customersFiltered = self::intersectByEntityId($tempTotal, $customersFiltered);
            }
            //FINE FILTRO 8 TOTALE ACQUISTATO: testato ok

            //FILTRO 9 DATA ACQUISTATO
            $count = 0;
            $result = array();
            $tempOrderedDateYes = array();
            $tempOrderedDateNo = array();

            if ($request->getRequest()->getParam('emailchefOrderStartDate')
                || $request->getRequest()->getParam('emailchefOrderEndDate')) {
                foreach ($customersFiltered as $customer) {
                    $result[] = $customer;

                    //filtro gli ordini in base al customer id
                    $orders = Mage::getModel('sales/order')
                        ->getCollection()
                        ->addAttributeToFilter('customer_id', $result[$count]['entity_id'])
                    ;

                    $orderedDate = 0;

                    foreach ($orders->getData() as $order) {
                        if (isset($order['status']) && !in_array($order['status'], array('closed', 'complete', 'processing'))) {
                            continue;
                        }
                        $createdAt = $order['created_at'];
                        $createdAt = new DateTime($createdAt, $TIMEZONE_UTC);
                        $createdAt->setTimezone($TIMEZONE_STORE);
                        $createdAt = (string) $createdAt->format('Y-m-d H:i:s');
                        $filterStart = '';
                        $filterEnd = '';

                        if ($request->getRequest()->getParam('emailchefOrderStartDate')) {
                            $date = Zend_Locale_Format::getDate(
                                $request->getRequest()->getParam('emailchefOrderStartDate'),
                                array(
                                    'locale' => Mage::app()->getLocale()->getLocale(),
                                    'date_format' => Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
                                    'fix_date' => true,
                                )
                            );
                            $date['month'] = str_pad($date['month'], 2, 0, STR_PAD_LEFT);
                            $date['day'] = str_pad($date['day'], 2, 0, STR_PAD_LEFT);
                            $filterStart = "{$date['year']}-{$date['month']}-{$date['day']} 00:00:00";
                        }
                        if ($request->getRequest()->getParam('emailchefOrderEndDate')) {
                            $date = Zend_Locale_Format::getDate(
                                $request->getRequest()->getParam('emailchefOrderEndDate'),
                                array(
                                    'locale' => Mage::app()->getLocale()->getLocale(),
                                    'date_format' => Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
                                    'fix_date' => true,
                                )
                            );
                            $date['month'] = str_pad($date['month'], 2, 0, STR_PAD_LEFT);
                            $date['day'] = str_pad($date['day'], 2, 0, STR_PAD_LEFT);
                            $filterEnd = "{$date['year']}-{$date['month']}-{$date['day']} 23:59:59";
                        }

                        if ($filterStart and $filterEnd) {
                            //compreso tra start e end date
                            if ($createdAt >= $filterStart and $createdAt <= $filterEnd) {
                                $orderedDate = 1;
                            }
                        } elseif ($filterStart) {
                            // >= di start date
                            if ($createdAt >= $filterStart) {
                                $orderedDate = 1;
                            }
                        } else {
                            // <= di end date
                            if ($createdAt <= $filterEnd) {
                                $orderedDate = 1;
                            }
                        }

                        //unsetto la variabile
                        unset($orders); //->unsetData();
                    }

                    if ($orderedDate == 1) {
                        $tempOrderedDateYes[] = $result[$count];
                    } else {
                        $tempOrderedDateNo[] = $result[$count];
                    }

                    ++$count;
                }

                if ($request->getRequest()->getParam('emailchefOrderYesNo') == 'yes') {
                    $customersFiltered = self::intersectByEntityId($tempOrderedDateYes, $customersFiltered);
                } else {
                    $customersFiltered = self::intersectByEntityId($tempOrderedDateNo, $customersFiltered);
                }
            }
            //FINE FILTRO 9 DATA ACQUISTATO: testato ok
        } else {
            //GESTISCO LE MODIFICHE MANUALI
            $count = 0;
            $result = array();
            $tempMod = array();

            $emails = explode("\n", $request->getRequest()->getParam('emailchefCustomerFilteredMod'));

            foreach ($emails as $email) {
                $email = trim($email);

                if (strstr($email, '@') !== false) {
                    $customerModCollection = Mage::getModel('customer/customer')
                        ->getCollection()
                        ->addAttributeToSelect('email')
                        ->addAttributeToFilter('email', $email);

                    $added = 0;

                    foreach ($customerModCollection as $customerMod) {
                        $tempMod[] = $customerMod->toArray();
                        $added = 1;
                    }

                    if ($added == 0) {
                        $tempMod[] = array('entity_id' => 0, 'firstname' => '', 'lastname' => '', 'email' => $email);
                    }
                }
            }

            //$customersFiltered = self::intersectByEntityId($tempMod, $customersFiltered);
            $customersFiltered = $tempMod;
        }
        //FINE GESTISCO LE MODIFICHE MANUALI

        return $customersFiltered;
    }

    /**
     * Get Filter Hints.
     *
     * @return array
     */
    public function getFilterHints()
    {
        $filter_hints = array();
        try {
            // fetch write database connection that is used in Mage_Core module
            $connectionRead = Mage::getSingleton('core/resource')->getConnection('core_read');

            // now $write is an instance of Zend_Db_Adapter_Abstract
            $result = $connectionRead->query('select * from emailchef_filter_hints');

            while ($row = $result->fetch()) {
                array_push($filter_hints, array('filter_name' => $row['filter_name'], 'hints' => $row['hints']));
            }
        } catch (Exception $e) {
            Mage::log('Exception: '.$e->getMessage(), 0);
            die($e);
        }

        return $filter_hints;
    }

    /**
     * Save Filter Hint.
     *
     * @param type $filter_name
     * @param type $post
     */
    public function saveFilterHint($filter_name, $post)
    {
        try {
            $hints = '';
            foreach ($post as $k => $v) {
                if ($v != '' && $k != 'form_key') {
                    if ($hints != '') {
                        $hints .= '|';
                    }
                    $hints .= $k.'='.$v;
                }
            }
            //(e.g. $hints = 'emailchefCustomers=2|emailchefSubscribed=1';)
            $connectionWrite = Mage::getSingleton('core/resource')->getConnection('core_write');
            $connectionWrite->query("INSERT INTO emailchef_filter_hints (filter_name, hints) VALUES ('".$filter_name."', '".$hints."')");
        } catch (Exception $e) {
            Mage::log('Exception: '.$e->getMessage(), 0);
            die($e);
        }
    }

    /**
     * Delete Filter Hint.
     *
     * @param type $filter_name
     */
    public function deleteFilterHint($filter_name)
    {
        try {
            $connectionWrite = Mage::getSingleton('core/resource')->getConnection('core_write');
            $connectionWrite->query("DELETE FROM emailchef_filter_hints WHERE filter_name LIKE '".$filter_name."'");
        } catch (Exception $e) {
            Mage::log('Exception: '.$e->getMessage(), 0);
            die($e);
        }
    }

    /**
     * Get Field Mapping.
     *
     * @todo    Fix to use the config for mappings, per store..
     *
     * @param   int
     *
     * @return array
     */
    public function getFieldsMapping($storeId = null)
    {
        $config = Mage::getModel('emailchef/config');
        /* @var $config EMailChef_EMailChefSync_Model_Config */
        return $config->getFieldsMapping($storeId);
    }

    /**
     * @depreciated
     *
     * @param   array
     */
    /*public function saveFieldMapping($post)
    {
        try {
            $connectionWrite = Mage::getSingleton('core/resource')->getConnection('core_write');
            $connectionWrite->query('DELETE FROM emailchef_fields_mapping');
            foreach ($post as $k => $v) {
                if (strlen($v) == 0) {
                    continue;
                }
                $connectionWrite->insert('emailchef_fields_mapping', array(
                    'magento_field_name' => $k,
                    'emailchef_field_id' => $v,
                ));
            }
        } catch (Exception $e) {
            Mage::log('Exception: '.$e->getMessage(), 0);
            die($e);
        }
    }*/

    /**
     * Get the config.
     *
     * @return EMailChef_EMailChefSync_Model_Config
     */
    protected function _config()
    {
        return $this->_config;
    }

    /**
     * Recursive intersection of $array1 and $array2 by entity IDs
     * NOTE that php's self::intersectByEntityId is not recursive, so cannot be used on arrays of arrays.
     *
     * @param array $array1
     * @param array $array2
     *
     * @return array
     */
    public static function intersectByEntityId($array1, $array2)
    {
        $tempIds = array();
        foreach ($array1 as $entity1) {
            if (isset($entity1['entity_id'])) {
                $tempIds[$entity1['entity_id']] = true;
            }
        }
        $tempArray = array();
        foreach ($array2 as $entity2) {
            if (isset($entity2['entity_id']) && isset($tempIds[$entity2['entity_id']])) {
                $tempArray[] = $entity2;
            }
        }

        return $tempArray;
    }
}
