<?php

require_once dirname(__DIR__).'/Model/EMailChefWsImport.php';
require_once dirname(__DIR__).'/Model/Wssend.php';

/**
 * Data.php.
 *
 * @todo    get rid of these static methods!
 */
class EMailChef_EMailChefSync_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * importType takes one of the following values, varying:
     *   - Whether to import email, sms or both (in the former cases, the other is discarded)
     *   - Whether empty fields over-write the values in EMailChef (4-6) or are ignored (1-3).
     */
    const IMPORT_TYPE_IGNORE_EMPTY_ONLY_EMAIL = 1;
    const IMPORT_TYPE_IGNORE_EMPTY_ONLY_SMS = 2;
    const IMPORT_TYPE_IGNORE_EMPTY_EMAIL_AND_SMS = 3;
    const IMPORT_TYPE_REPLACE_EMPTY_ONLY_EMAIL = 4;
    const IMPORT_TYPE_REPLACE_EMPTY_ONLY_SMS = 5;
    const IMPORT_TYPE_REPLACE_EMPTY_EMAIL_AND_SMS = 6;

    /**
     * Whether the mobile input type splits the international code into a seperate field.
     */
    const MOBILE_INPUT_TYPE_INCLUDE_INTL_CODE = 1;
    const MOBILE_INPUT_TYPE_SPLIT_INTL_CODE = 2;

    /**
     * split customers into batches.
     */
    const BATCH_SIZE = 2000;

    /**
     * Get the Customer Data.
     *
     * @param   array
     *
     * @return array
     */
    public static function getCustomersData($customerCollection = null)
    {
        $config = Mage::getModel('emailchef/config');
        /* @var $config EMailChef_EMailChefSync_Model_Config */

        if ($config->isLogEnabled()) {
            $config->log('Getting customers data');
        }

        if (is_array($customerCollection) && empty($customerCollection)) {
            if ($config->isLogEnabled()) {
                $config->log('CustomerCollection is Empty!');
            }
        }

        $dateFormat = 'm/d/y h:i:s';
        $lastDateTime = date($dateFormat, Mage::getModel('core/date')->timestamp(time()) - 7 * 3600 * 24);
        $thirtyDaysAgo = date($dateFormat, Mage::getModel('core/date')->timestamp(time()) - 30 * 3600 * 24);
        $twelveMonthsAgo = date($dateFormat, Mage::getModel('core/date')->timestamp(time()) - 365 * 3600 * 24);

        $parseSubscribers = false;
        $toSend = array();
        if ($customerCollection === null) {
            /*
             * @todo    Change to only load form current store/website
             */
            $customerCollection = Mage::getModel('customer/customer')->getCollection();
            $parseSubscribers = true;
            if ($config->isLogEnabled()) {
                $config->log('Parsing Subscribers, NULL collection passed.');
            }
        }
        foreach ($customerCollection as $currentCustomerId) {
            if (is_object($currentCustomerId)) {
                $currentCustomerId = $currentCustomerId->getId();
            }

            if (!$currentCustomerId) {
                if ($config->isLogEnabled()) {
                    $config->log('Skipping Empty Customer ID!');
                    continue;
                }
            }

            if ($config->isLogEnabled()) {
                $config->log('Customer with id '.$currentCustomerId);
            }
            $customer = Mage::getModel('customer/customer')->load($currentCustomerId);
            /* @var $customer Mage_Customer_Model_Customer */
            $i = $customer->getEmail();

            // Get order dates, numbers and totals for the current customer
            //TODO: This would be more efficient with just a few SQL statements to gather this
            $allOrdersTotalAmount = 0;
            $allOrdersDateTimes = array();
            $allOrdersTotals = array();
            $allOrdersIds = array();
            $allProductsIds = array();
            $last30daysOrdersAmount = 0;
            $last12monthsOrdersAmount = 0;
            $lastShipmentOrderId = null;
            $lastShipmentOrderDate = null;

            if ($config->isLogEnabled()) {
                $config->log('Parsing orders of customer with id '.$currentCustomerId);
            }
            // Setup collection to fetch orders for this customer and valid statuses
            $orders = Mage::getResourceModel('sales/order_collection')
                ->addAttributeToFilter('customer_id', $currentCustomerId);
            Mage::helper('emailchef/order')->addStatusFilterToOrders($orders);

            foreach ($orders as $order) {
                if ($config->isLogEnabled()) {
                    $config->log("ORDER STATUS: {$order->getState()} / {$order->getStatus()}");
                }

                // Get current and total orders
                $currentOrderTotal = floatval($order->getGrandTotal());
                $allOrdersTotalAmount += $currentOrderTotal;

                $currentOrderCreationDate = $order->getCreatedAt();
                if ($currentOrderCreationDate > $thirtyDaysAgo) {
                    $last30daysOrdersAmount += $currentOrderTotal;
                }
                if ($currentOrderCreationDate > $twelveMonthsAgo) {
                    $last12monthsOrdersAmount += $currentOrderTotal;
                }

                $currentOrderTotal = self::_formatPrice($currentOrderTotal);
                $currentOrderId = $order->getIncrementId();
                $allOrdersTotals[$currentOrderId] = $currentOrderTotal;
                $allOrdersDateTimes[$currentOrderId] = $currentOrderCreationDate;
                $allOrdersIds[$currentOrderId] = $currentOrderId;

                if ($order->hasShipments() and ($order->getId() > $lastShipmentOrderId)) {
                    $lastShipmentOrderId = $order->getId();
                    $lastShipmentOrderDate = self::_retriveDateFromDatetime($order->getCreatedAt());
                }

                $items = $order->getAllItems();
                foreach ($items as $item) {
                    $allProductsIds[] = $item->getProductId();
                }
            }

            $toSend[$i]['TotaleFatturatoUltimi30gg'] = self::_formatPrice($last30daysOrdersAmount);
            $toSend[$i]['TotaleFatturatoUltimi12Mesi'] = self::_formatPrice($last12monthsOrdersAmount);
            $toSend[$i]['IDTuttiProdottiAcquistati'] = implode(',', $allProductsIds);

            ksort($allOrdersDateTimes);
            ksort($allOrdersTotals);
            ksort($allOrdersIds);

            //recupero i carrelli abbandonati del cliente
            if ($config->isLogEnabled()) {
                $config->log('Parsing abandoned carts of customer with id '.$currentCustomerId);
            }
            $cartCollection = Mage::getResourceModel('reports/quote_collection');
            $cartCollection->prepareForAbandonedReport($config->getAllStoreIds());
            $cartCollection->addFieldToFilter('customer_id', $currentCustomerId);
            $cartCollection->load();

            $datetimeCart = null;
            if (!empty($cartCollection)) {
                $lastCart = $cartCollection->getLastItem();
                $toSend[$i]['TotaleCarrelloAbbandonato'] = '';
                $toSend[$i]['DataCarrelloAbbandonato'] = '';
                $toSend[$i]['IDCarrelloAbbandonato'] = '';

                if (!empty($lastCart)) {
                    if ($config->isLogEnabled()) {
                        $config->log('Customer with id '.$currentCustomerId.' has abandoned cart');
                    }
                    $datetimeCart = $lastCart->getUpdatedAt();
                    //$toSend[$i]['TotaleCarrelloAbbandonato'] = self::_formatPrice($lastCart->getGrandTotal());
                    $toSend[$i]['TotaleCarrelloAbbandonato'] = self::_formatPrice($lastCart->getSubtotal());
                    $toSend[$i]['DataCarrelloAbbandonato'] = self::_retriveDateFromDatetime($datetimeCart);
                    $toSend[$i]['IDCarrelloAbbandonato'] = $lastCart->getId();
                } else {
                    if ($config->isLogEnabled()) {
                        $config->log('Customer with id '.$currentCustomerId.' has empty LAST CART');
                    }
                }
            } else {
                if ($config->isLogEnabled()) {
                    $config->log('Customer id '.$currentCustomerId.' has empty abandoned cart collection');
                }
            }

            $toSend[$i]['IDUltimoOrdineSpedito'] = $lastShipmentOrderId;
            $toSend[$i]['DataUltimoOrdineSpedito'] = $lastShipmentOrderDate;

            $lastOrderDateTime = end($allOrdersDateTimes);

            if ($customer->getUpdatedAt() > $lastDateTime
                || $lastOrderDateTime > $lastDateTime
                || ($datetimeCart && $datetimeCart > $lastDateTime)) {
                if ($config->isLogEnabled()) {
                    $config->log('Adding customer with id '.$currentCustomerId);
                }

                $toSend[$i]['nome'] = $customer->getFirstname();
                $toSend[$i]['cognome'] = $customer->getLastname();
                $toSend[$i]['email'] = $customer->getEmail();
                $toSend[$i]['IDCliente'] = $currentCustomerId;

                // Custom customer attributes
                $customerAttributes = Mage::helper('emailchef/customer')->getCustomCustomerAttrCollection();
                foreach ($customerAttributes as $attribute) {
                    $code = $attribute->getAttributeCode().'_custom_customer_attributes';
                    $value = $customer->getData($attribute->getAttributeCode());
                    if ($attribute->usesSource()) {
                        /* Attempt to get source model. As we cannot trust customers to have not leave broken
                           attributes with invalid source models around, we will test this directly */
                        $source = Mage::getModel($attribute->getSourceModel());
                        if ($source == false) {
                            if ($config->isLogEnabled()) {
                                $config->log('Invalid source model for attribute '.$attribute->getAttributeCode());
                            }
                            $toSend[$i][$code] = null;
                        } else {
                            $toSend[$i][$code] = $attribute->getSource()->getOptionText($value);
                        }
                    } else {
                        $toSend[$i][$code] = $value;
                    }
                }

                $toSend[$i]['registeredDate'] = self::_retriveDateFromDatetime($customer->getCreatedAt());

                //controllo se iscritto o meno alla newsletter
                if (Mage::getModel('newsletter/subscriber')->loadByCustomer($customer)->isSubscribed()) {
                    $toSend[$i]['subscribed'] = 'yes';
                } else {
                    $toSend[$i]['subscribed'] = 'no';
                }

                //recupero i dati dal default billing address
                $customerAddressId = $customer->getDefaultBilling();
                if ($customerAddressId) {
                    $address = Mage::getModel('customer/address')->load($customerAddressId);
                    $toSend[$i]['azienda'] = $address->getData('company');
                    $toSend[$i]['paese'] = $address->getCountry();
                    $toSend[$i]['città'] = $address->getData('city');
                    $toSend[$i]['regione'] = $address->getData('region');
                    $regionId = $address->getData('region_id');
                    $regionModel = Mage::getModel('directory/region')->load($regionId);
                    $regionCode = $regionModel->getCode();
                    $toSend[$i]['provincia'] = $regionCode;
                    $toSend[$i]['cap'] = $address->getData('postcode');
                    $toSend[$i]['indirizzo'] = $address->getData('street');
                    $toSend[$i]['fax'] = $address->getData('fax');
                    $toSend[$i]['telefono'] = $address->getData('telephone');
                } else {
                    $toSend[$i]['azienda'] = '';
                    $toSend[$i]['paese'] = '';
                    $toSend[$i]['città'] = '';
                    $toSend[$i]['regione'] = '';
                    $toSend[$i]['provincia'] = '';
                    $toSend[$i]['cap'] = '';
                    $toSend[$i]['indirizzo'] = '';
                    $toSend[$i]['fax'] = '';
                    $toSend[$i]['telefono'] = '';
                }

                $toSend[$i]['DataUltimoOrdine'] = self::_retriveDateFromDatetime($lastOrderDateTime);
                $toSend[$i]['TotaleUltimoOrdine'] = end($allOrdersTotals);
                $toSend[$i]['IDUltimoOrdine'] = end($allOrdersIds);

                $toSend[$i]['TotaleFatturato'] = self::_formatPrice($allOrdersTotalAmount);

                //ottengo gli id di prodotti e categorie (dell'ultimo ordine)
                $lastOrder = Mage::getModel('sales/order')->loadByIncrementId(end($allOrdersIds));
                $items = $lastOrder->getAllItems();
                $productIds = array();
                $categoryIds = array();
                foreach ($items as $item) {
                    $productId = $item->getProductId();
                    $productIds[] = $productId;
                    $product = Mage::getModel('catalog/product')->load($productId);
                    if ($product->getCategoryIds()) {
                        $categoryIds[] = implode(',', $product->getCategoryIds());
                    }
                }

                $toSend[$i]['IDProdottiUltimoOrdine'] = implode(',', $productIds);
                if ($toSend[$i]['IDProdottiUltimoOrdine']) {
                    $toSend[$i]['IDProdottiUltimoOrdine'] = ",{$toSend[$i]['IDProdottiUltimoOrdine']},";
                }
                $toSend[$i]['IDCategorieUltimoOrdine'] = implode(',', $categoryIds);
                if ($toSend[$i]['IDCategorieUltimoOrdine']) {
                    $toSend[$i]['IDCategorieUltimoOrdine'] = ",{$toSend[$i]['IDCategorieUltimoOrdine']},";
                }
            }

            $toSend[$i]['DateOfBirth'] = self::_retriveDobFromDatetime($customer->getDob());
            $toSend[$i]['Gender'] = $customer->getAttribute('gender')->getSource()->getOptionText($customer->getGender());

            //unsetto la variabile
            unset($customer);
        }

        /*
         *  disabled cause useless in segmentation
        if ($parseSubscribers) {
            if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) Mage::log('Parsing subscribers', 0);
            $subscriberCollection = Mage::getModel('newsletter/subscriber')
                ->getCollection()
                ->useOnlySubscribed()
                ->addFieldToFilter('customer_id', 0);

            foreach ($subscriberCollection as $subscriber) {
                $subscriber = Mage::getModel('newsletter/subscriber')->load($subscriber->getId());
                $i = $subscriber->getEmail();
                if (strlen($i)) continue;
                if (isset($toSend[$i])) continue;
                $toSend[$i]['nome'] = '';
                $toSend[$i]['cognome'] = '';
                $toSend[$i]['email'] = $i;
                $toSend[$i]['subscribed'] = 'yes';
            }
        }
        */

        if ($config->isLogEnabled()) {
            $config->log('End getting customers data');
        }

        return $toSend;
    }

    /**
     * Send Customer Data.
     *
     * @param array $emailchefCustomerIds
     * @param   array
     * @param   int
     *
     * @return int|false ReturnCode
     */
    public static function generateAndSendCustomers($emailchefCustomerIds, $post = null, $storeId = null)
    {
        $config = Mage::getModel('emailchef/config');
        /* @var $config EMailChef_EMailChefSync_Model_Config */

        $wsSend = new EMailChefWsSend($storeId);
        require_once dirname(__FILE__).'/../Model/EMailChefWsImport.php';
        $wsImport = new EMailChefWsImport($storeId);
        $accessKey = $wsSend->loginFromId();

        if (empty($emailchefCustomerIds)) {
            if ($config->isLogEnabled($storeId)) {
                $config->log('generateAndSendCustomers [Empty Customer ID Array]');
            }

            return false;
        }

        $jobId = $post['id'];
        $jobModel = Mage::getModel('emailchef/job')->load($post['id']);
        /* @var $jobModel EMailChef_EMailChefSync_Model_Job */

        if ($accessKey === false) {
            Mage::throwException('no access key returned');
        }

        $fields_mapping = $wsImport->getFieldsMapping($storeId); // Pass StoreId
        if (count($fields_mapping) == 0) {
            if ($config->isLogEnabled($storeId)) {
                $config->log('No mappings set, so cannot sync customers');
            }

            return false;
        }

        // Define the group we're adding customers to
        $groupId = $post['emailchefGroupId'];
        $listGUID = $post['emailchefListGUID'];
        $idList = $post['emailchefIdList'];

        /*
         * Create a new EMailChef Group.
         */
        if ($post['emailchefNewGroup'] == 1) {
            $newGroup = array(
                'idList' => $idList,
                'listGUID' => $listGUID,
                'newGroupName' => $post['emailchefNewGroupName'],
            );
            $groupId = $wsImport->CreaGruppo($newGroup, $accessKey);
        }

        $importProcessData = array(
            'idList' => $idList,
            'listGUID' => $listGUID,
            'idGroup' => $groupId,
            'xmlDoc' => '',
            'idGroups' => $groupId,
            'importType' => self::IMPORT_TYPE_REPLACE_EMPTY_ONLY_EMAIL,
            'mobileInputType' => self::MOBILE_INPUT_TYPE_SPLIT_INTL_CODE,
            'ConfirmEmail' => 0,
            'asOptOut' => 0,
            'forceOptIn' => 0, // Dangerous to use as this can over-write pending/un-subscribe statuses
            'replaceGroups' => 0,
        );

        $subscribers_counter = 0;
        $totalCustomers = sizeof($emailchefCustomerIds);
        $customers = array();
        foreach ($emailchefCustomerIds as $customerId) {
            ++$subscribers_counter;
            $customers[] = self::getCustomerData($customerId, $fields_mapping, $storeId);
        }
        /*
         * Build Data for StartImportProcesses
         */
        $startImportProcessesData = array(
            'listsIDs' => $post['emailchefIdList'],
            'listsGUIDs' => $post['emailchefListGUID'],
            'groupsIDs' => $groupId,
            'idList' => $idList,
            'customers' => $customers,
            'importType' => self::IMPORT_TYPE_REPLACE_EMPTY_ONLY_EMAIL,
            'mobileInputType' => self::MOBILE_INPUT_TYPE_SPLIT_INTL_CODE,
            'ConfirmEmail' => 0,
            'asOptOut' => 0,
            'forceOptIn' => 0, // Dangerous to use as this can over-write pending/un-subscribe statuses
            'replaceGroups' => 0,
        );

        if ($config->isLogEnabled($storeId)) {
            $config->log("emailchef: StartImportProcesses (STORE: {$storeId})", $storeId);
            $config->log($startImportProcessesData);
        }
        $startProcessesReturnCode = $wsImport->StartImportProcesses($startImportProcessesData, $accessKey);
        /*
         * Save the Job Model, and update the tries as we've just tried to Start the Process
         */
        $jobModel->incrementTries();
        try {
            $jobModel->save();
        } catch (Exception $e) {
            Mage::log($e->getMessage());
        }
        if ($config->isLogEnabled($storeId)) {
            if ($startProcessesReturnCode < 0) {
                $config->dbLog(sprintf('StartImportProcesses [ReturnCode] [ERROR] [%d]', $startProcessesReturnCode), $jobId, $storeId);
            } else {
                $config->dbLog(sprintf('StartImportProcesses [ReturnCode] [SUCCESS] [%d]', $startProcessesReturnCode), $jobId, $storeId);
            }
        }

        return $startProcessesReturnCode;
    }

    /**
     * Get a single customer data.
     *
     * @param int   $customerId
     * @param array $fields_mapping
     * @param int   $storeId
     *
     * @return string|false
     */
    protected static function getCustomerData($customerId, $fields_mapping, $storeId)
    {
        $config = Mage::getModel('emailchef/config');
        /* @var $config EMailChef_EMailChefSync_Model_Config */
        $data = array();
        $mappedData = array();
        $subscriber = self::getCustomersData(array($customerId));

        if (is_array($subscriber) && empty($subscriber)) {
            if ($config->isLogEnabled($storeId)) {
                $config->log('getCustomersData [EMPTY]');
            }
        }

        $subscriber = array_values($subscriber);
        $subscriber = $subscriber[0];

        $subscriber['DataCarrelloAbbandonato'] = self::_convertUTCToStoreTimezoneAndFormatForEMailChef($subscriber['DataCarrelloAbbandonato']);
        $subscriber['DataUltimoOrdineSpedito'] = self::_convertUTCToStoreTimezoneAndFormatForEMailChef($subscriber['DataUltimoOrdineSpedito']);
        $subscriber['registeredDate'] = self::_convertUTCToStoreTimezoneAndFormatForEMailChef($subscriber['registeredDate']);
        $subscriber['DataUltimoOrdine'] = self::_convertUTCToStoreTimezoneAndFormatForEMailChef($subscriber['DataUltimoOrdine']);

        foreach ($subscriber as $k => $v) {
            if (!strlen($subscriber[$k])) {
                $subscriber[$k] = ' '; // blank it out in emailchef
            } else {
                $subscriber[$k] = str_replace(array("\r\n", "\r", "\n"), ' ', $v);
            }
        }

        $defaultMappings = array(
            'Name' => 'nome',
            'Last' => 'cognome',
            'Email' => 'email',
            'Company' => 'azienda',
            'City' => 'città',
            'Province' => 'provincia',
            'ZIP' => 'cap',
            'Region' => 'regione',
            'Country' => 'paese',
            'Address' => 'indirizzo',
            'Fax' => 'fax',
            'Phone' => 'telefono',
            'CustomerID' => 'IDCliente',
            'LatestOrderID' => 'IDUltimoOrdine',
            'LatestOrderDate' => 'DataUltimoOrdine',
            'LatestOrderAmount' => 'TotaleUltimoOrdine',
            'LatestOrderProductIDs' => 'IDProdottiUltimoOrdine',
            'LatestOrderCategoryIDs' => 'IDCategorieUltimoOrdine',
            'LatestShippedOrderDate' => 'DataUltimoOrdineSpedito',
            'LatestShippedOrderID' => 'IDUltimoOrdineSpedito',
            'LatestAbandonedCartDate' => 'DataCarrelloAbbandonato',
            'LatestAbandonedCartTotal' => 'TotaleCarrelloAbbandonato',
            'LatestAbandonedCartID' => 'IDCarrelloAbbandonato',
            'TotalOrdered' => 'TotaleFatturato',
            'TotalOrderedLast12m' => 'TotaleFatturatoUltimi12Mesi',
            'TotalOrderedLast30d' => 'TotaleFatturatoUltimi30gg',
            'AllOrderedProductIDs' => 'IDTuttiProdottiAcquistati',
            'DateOfBirth' => 'DateOfBirth',
            'Gender' => 'Gender',
        );
        $defaultMappings = array_reverse($defaultMappings);

        foreach ($fields_mapping as $mappingKey => $mappingValue) {
            if (!$mappingValue) {
                continue;
            }
            $mappingKeyNamed = $mappingKey;
            if (isset($defaultMappings[$mappingKey])) {
                $mappingKeyNamed = $defaultMappings[$mappingKey];
            }
            if (isset($subscriber[$mappingKeyNamed]) && trim(($subscriber[$mappingKeyNamed]))) {
                $data[$mappingValue] = $subscriber[$mappingKeyNamed];
            }
        }

        // No point in continuing if there is no mapped data
        if (count($data) == 0) {
            if ($config->isLogEnabled($storeId)) {
                $config->log('No mappings set, so cannot sync customers');
            }

            return false;
        }

        return $data;
    }

    /**
     * Run a particular job.
     *
     * @param int
     */
    public function runJob($jobId)
    {
        $config = Mage::getModel('emailchef/config');
        /* @var $config EMailChef_EMailChefSync_Model_Config */
        require_once dirname(__FILE__).'/../Helper/Data.php';
        $db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $syncTableName = Mage::getSingleton('core/resource')->getTableName('emailchef/sync');
        $jobsTableName = Mage::getSingleton('core/resource')->getTableName('emailchef/job');
        $lastsync = gmdate('Y-m-d H:i:s');
        // reading customers (jobid == 0, their updates)
        $customer_entity_table_name = Mage::getSingleton('core/resource')->getTableName('customer_entity');
        $jobModel = Mage::getModel('emailchef/job')->load($jobId);
        /* @var $jobModel EMailChef_EMailChefSync_Model_Job */

        if (!$jobModel) {
            throw new Mage_Exception('No Job Exists: '.$jobId);
        }

        $job = $jobModel->getData();
        $stmt = $db_write->query(
            "UPDATE {$jobsTableName}
            SET status='started', start_datetime='".gmdate('Y-m-d H:i:s')."'
            WHERE id={$job['id']}"
        );
        $storeId = isset($job['store_id']) ? $job['store_id'] : null;
        //$storeId = Mage::app()->getDefaultStoreView()->getStoreId(); // Fallback incase not set?!?
        $customers = array();
        $job['emailchefNewGroup'] = 0;
        $job['emailchefIdList'] = Mage::getStoreConfig('emailchef_newsletter/emailchef/list', $storeId);
        $job['emailchefGroupId'] = $job['emailchefgroupid'];

        // If group is 0 and there is a default group, set group to this group
        $defaultGroupId = Mage::getStoreConfig('emailchef_newsletter/emailchef/default_group');
        if ($job['emailchefGroupId'] == 0 && $defaultGroupId !== null) {
            $job['emailchefGroupId'] = $defaultGroupId;
        }

        $tmp = Mage::getSingleton('emailchef/source_lists');
        $tmp = $tmp->toOptionArray($storeId); // pass store id!
        foreach ($tmp as $t) {
            if ($t['value'] == $job['emailchefIdList']) {
                $job['emailchefListGUID'] = $t['guid'];
                $job['groups'] = $t['groups'];
                break;
            }
        }
        unset($tmp);
        unset($t);
        $stmt = $db_read->query("
            SELECT ms.*, ce.email
            FROM {$syncTableName} ms
            JOIN $customer_entity_table_name ce
                ON (ms.customer_id = ce.entity_id)
            WHERE ms.needs_sync=1
            AND ms.entity='customer'
            AND job_id={$job['id']}"
        );
        while ($row = $stmt->fetch()) {
            $customers[] = $row['customer_id'];
        }
        /*
         * Send the Data!
         */
        $returnCode = self::generateAndSendCustomers($customers, $job, $storeId);
        /*
         * Check return OK
         */
        if ($returnCode === true) {
            $customerCount = count($customers);
            $db_write->query("
                UPDATE {$syncTableName} SET needs_sync=0, last_sync='$lastsync'
                WHERE job_id = {$job['id']}
                AND entity='customer'"
            );
            $config->dbLog("Job Task [update] [Synced] [customer count:{$customerCount}]", $job['id'], $storeId);
            // finishing the job also
            $db_write->query("
                UPDATE {$jobsTableName} SET status='finished', finish_datetime='".gmdate('Y-m-d H:i:s')."'
                WHERE id={$job['id']}"
            );
            $config->dbLog("Jobs [Update] [Complete] [{$job['id']}]", $job['id'], $storeId);
        }
        /*
         * Only successfull if we get 0 back. False is also a fail.
         */
        else {
            $stmt = $db_write->query(
                "UPDATE {$jobsTableName} SET status='queued' WHERE id={$job['id']}"
            );
            if ($config->isLogEnabled()) {
                $config->dbLog(sprintf('generateAndSendCustomers [ReturnCode] [ERROR] [%d]', $returnCode), $job['id'], $storeId);
            }
        }
    }

    /**
     * Get sub Categories of a Category.
     *
     * @param   int
     *
     * @return array|string
     */
    public function getSubCategories($categoryId)
    {
        // Not sure what version this was introduced.
        $parent = Mage::getModel('catalog/category')->load($categoryId);
        $children = $parent->getAllChildren(true);

        if (!empty($children) && is_array($children)) {
            return $children;
        }

        return array();

//        // Maybe fall back to this in older versions?
//        $ids = array();
//        $children = Mage::getModel('catalog/category')->getCategories($categoryId);
//        foreach ($children as $category) {
//            /* @var $category Mage_Catalog_Model_Category */
//            $ids[] = $category->getId();
//        }
//
//        return $ids;
    }

    /**
     * Format the Price.
     *
     * @param   float
     *
     * @return string
     */
    private static function _formatPrice($price)
    {
        return number_format($price, 2, '.', '');
    }

    /**
     * Get Date from DateTime.
     *
     * @param type $datetime
     *
     * @return string
     */
    private static function _retriveDateFromDatetime($datetime)
    {
        if (empty($datetime)) {
            return '';
        }

        return date('Y-m-d H:i:s', strtotime($datetime));
    }

    /**
     * Get DOB Format from DateTime.
     *
     * @param string $datetime
     *
     * @return string
     */
    private static function _retriveDobFromDatetime($datetime)
    {
        if (empty($datetime)) {
            return '';
        }

        return date('Y-m-d', strtotime($datetime));
    }

    public static function _convertUTCToStoreTimezone($datetime)
    {
        if (empty($datetime)) {
            return '';
        }

        $TIMEZONE_STORE = new DateTimeZone(Mage::getStoreConfig('general/locale/timezone'));
        $TIMEZONE_UTC = new DateTimeZone('UTC');

        $datetime = new DateTime($datetime, $TIMEZONE_UTC);
        $datetime->setTimezone($TIMEZONE_STORE);
        $datetime = (string) $datetime->format('Y-m-d H:i:s');

        return $datetime;
    }

    public static function _convertUTCToStoreTimezoneAndFormatForEMailChef($datetime)
    {
        if (empty($datetime)) {
            return '';
        }
        $datetime = self::_convertUTCToStoreTimezone($datetime);

        return date('Y-m-d', strtotime($datetime));
    }

    /**
     * Clean the Resource Table.
     */
    public function cleanResourceTable()
    {
        $sql = "DELETE FROM `core_resource` WHERE `code` = 'emailchef_setup';";
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        try {
            $connection->query($sql);
            die('deleted module in core_resource!');
        } catch (Exception $e) {
            Mage::log($e->getMessage());
        }
    }

    /**
     * Clean the Resource Table.
     */
    public function showResourceTable()
    {
        $sql = 'SELECT * FROM `core_resource`';
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        try {
            $result = $connection->fetchAll($sql);
            foreach ($result as $row) {
                echo $row['code'].'<br />';
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Get all product attributes.
     *
     * Note if we don't use a keyed array below the first item with key 0
     * gets replaced by an empty option by magento. this results in a missing attribute
     * from the list!
     *
     * @reutrn  array
     */
    public function getAllProductAttributes()
    {
        //$attributes = Mage::getModel('catalog/product')->getAttributes();
        $attributes = Mage::getSingleton('eav/config')
            ->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getAttributeCollection()
        ;
        // Localize attribute label (if you need it)
        $attributes->addStoreLabel(Mage::app()->getStore()->getId());
        $attributeArray = array();
        foreach ($attributes as $att) {
            /* @var $att Mage_Catalog_Model_Resource_Eav_Attribute */
            if ($att->getIsVisible()) {
                $attributeArray[$att->getAttributeCode()] = array(
                    'value' => $att->getAttributeCode(),
                    'label' => $att->getStoreLabel() ? $att->getStoreLabel() : $att->getFrontendLabel(),
                );
            }
        }

        return $attributeArray;
    }

    /**
     * Get all product attributes.
     *
     * Note if we don't use a keyed array below the first item with key 0
     * gets replaced by an empty option by magento. this results in a missing attribute
     * from the list!
     *
     * @reutrn  array
     */
    public function getAllCustomerAttributes()
    {
        //$attributes = Mage::getModel('catalog/product')->getAttributes();
        $attributes = Mage::getSingleton('eav/config')
            ->getEntityType('customer')->getAttributeCollection()
        ;
        // Localize attribute label (if you need it)
        $attributes->addStoreLabel(Mage::app()->getStore()->getId());
        $attributeArray = array();
        foreach ($attributes as $att) {
            /* @var $att Mage_Catalog_Model_Resource_Eav_Attribute */
            if ($att->getIsVisible()) {
                $attributeArray[$att->getAttributeCode()] = array(
                    'value' => $att->getAttributeCode(),
                    'label' => $att->getStoreLabel() ? $att->getStoreLabel() : $att->getFrontendLabel(),
                );
            }
        }

        return $attributeArray;
    }

    /**
     * Is someone a subscriber?
     *
     * @param   int
     * @param   int
     *
     * @return bool
     */
    public function isSubscriber($customerId, $storeId)
    {
        $statuses = array(
            Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED,
        );

        return $this->_isSubscriberInStatus($customerId, $statuses, $storeId);
    }

    /**
     * Is the given customer a subscriber with the given status?
     * Note that an empty set of statuses will just return true.
     *
     * @param $customerId
     * @param array $statuses
     * @param $storeId
     *
     * @return bool|mixed
     */
    protected function _isSubscriberInStatus($customerId, array $statuses, $storeId)
    {
        $customerId = (int) $customerId;
        $storeId = (int) $storeId;

        // If no status set given, just return true
        if (empty($statuses)) {
            return true;
        }

        $table = Mage::getSingleton('core/resource')->getTableName('newsletter_subscriber');
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
            Mage::log('check subscription status '.$customerId);
        }
        $sql = "SELECT * FROM {$table} WHERE customer_id = '{$customerId}'";
        try {
            $result = $connection->fetchAll($sql); // array
            if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log')) {
                Mage::log('check subscription result '.var_export($result, true).' statuses '.var_export($statuses, true));
            }
            if (count($result) == 0) {
                return false;
            }
            $result = $result[0];

            // Return whether status is in given set
            return array_search($result['subscriber_status'], $statuses) !== false;
        } catch (Exception $e) {
            Mage::log($e->getMessage());
        }

        return false;
    }

    /**
     * Schedule a Task.
     *
     * @param   string
     * @param   string
     */
    public function scheduleTask($when, $type = 'EMailChef_EMailChefSync')
    {
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $write->insert(Mage::getSingleton('core/resource')->getTableName('cron_schedule'), array(
            'job_code' => $type,
            'status' => 'pending',
            'created_at' => gmdate('Y-m-d H:i:s'),
            'scheduled_at' => $when,
        ));
    }

    /**
     * Retrieve Attribute Id Data By Id or Code.
     *
     * @param   mixed
     * @param   int
     *
     * @return int
     */
    public function getAttributeId($id, $entityTypeId = null)
    {
        if ($entityTypeId == null) {
            $entityTypeId = Mage::getModel('catalog/product')->getResource()->getEntityType()->getId();
        }

        $installer = new Mage_Catalog_Model_Resource_Eav_Mysql4_Setup('core_setup');
        if (!is_numeric($id)) {
            $id = $installer->getAttribute($entityTypeId, $id, 'attribute_id');
        }
        if (!is_numeric($id)) {
            //throw Mage::exception('Mage_Eav', Mage::helper('eav')->__('Wrong attribute ID.'));
            return false;
        }

        return $id;
    }

    /**
     * Get ListGuid (Alphanumeric code associated to a distribution list) for given list.
     *
     * @param $listId
     *
     * @return false|string
     */
    public function getListGuid($listId)
    {
        $wsSend = new EMailChefWsSend();
        $accessKey = $wsSend->loginFromId();
        $wsImport = new EMailChefWsImport();
        $lists = $wsImport->GetNlList($accessKey, true);

        $listGUID = false;
        foreach ($lists as $list) {
            if ($list->id == $listId) {
                $groups = $list->groups;
                foreach ($groups as $group) {
                    // TODO: check random group ?
              $listGUID = (string) $group->id;
                }
                break;
            }
        }

        return $listGUID;
    }


    /**
     * Test all EMailChef Connection details.
     *
     * @param $usernameWs
     * @param $passwordWs
     * @param $storeId
     *
     * @return array Return array of messages (success or errors)
     */
    public function testConnection($usernameWs, $passwordWs, $storeId)
    {
        $messages = array();

        // Run test for username and password
        if ($this->_testConnectionUserPassword($usernameWs, $passwordWs, $storeId) === false) {
            $messages[] = array(
                'message' => $this->__('Error in email / password'),
                'type' => 'error',
            );
        }

        if (empty($messages)) {
            $messages[] = array(
                'message' => $this->__('Success! Connection established with eMailChef with given details'),
                'type' => 'success',
            );
        }

        return $messages;
    }

    /**
     * Test configuration for common issues.
     *
     * @return array Return array of messages (warnings)
     */
    public function testConfig()
    {
        $messages = array();

        // Mysql timeout
        $timeout = ini_get('mysql.connect_timeout');
        if ($timeout !== false && $timeout < 60) {
            $messages[] = array(
                'message' => $this->__('Config warning: mysql.connect_timeout is %d which is a bit low. '.
                    'This may cause intermittent issues when connecting with eMailChef. '.
                    'Please contact your Web host to discuss an increase in the timeout setting.', $timeout),
                'type' => 'warning',
            );
        }

        return $messages;
    }

    /**
     * Test username and password.
     *
     * @param $usernameWs
     * @param $passwordWs
     * @param $storeId
     *
     * @return bool
     */
    protected function _testConnectionUserPassword($usernameWs, $passwordWs, $storeId)
    {
        $wssend = new EMailChefWsSend($storeId);

        $loginSuccess = $wssend->loginFromId($usernameWs, $passwordWs);

        return $loginSuccess !== false;
    }
}
