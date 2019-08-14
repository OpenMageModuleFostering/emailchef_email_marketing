<?php

require_once dirname(__FILE__).'/../../Model/EMailChefWsImport.php';
require_once dirname(__FILE__).'/../../Model/Wssend.php';
/**
 * FilterController.php.
 */
class EMailChef_EMailChefSync_Adminhtml_FilterController extends Mage_Adminhtml_Controller_Action
{
    /**
     * split customers into batches.
     */
    const BATCH_SIZE = 2000;
    const STATUS_SUBSCRIBED = 'subscribed';
    const STATUS_NOT_SUBSCRIBED = 'not_subscribed';

    /**
     * Default Action.
     */
    public function indexAction()
    {
        $this->checkRunningImport();
        $this->loadLayout()->renderLayout();
    }

    /**
     * Confirm / Final Step.
     */
    public function confirmAction()
    {
        $this->checkRunningImport();
        $this->loadLayout()->renderLayout();
    }

    /**
     * Handle Posted Data.
     */
    public function postAction()
    {
        $post = $this->getRequest()->getPost();
        $storeId = isset($post['store_id']) ? (int) $post['store_id'] : null;

        if (empty($post)) {
            Mage::throwException($this->__('Invalid form data.'));
        }

        $config = Mage::getModel('emailchef/config');
        /* @var $config EMailChef_EMailChefSync_Model_Config */
        //$subscriber = Mage::getModel('newsletter/subscriber');
        /* @var $subscriber Mage_Newsletter_Model_Subscriber */

        $emailchefCustomerIds = Mage::getSingleton('core/session')->getEMailChefCustomerIds();
        //$totalCustomers = count($emailchefCustomerIds);
        $batches = $this->_getBatches($emailchefCustomerIds, $storeId);
        //$totalBatches = count($customerIdBatches);
        $db_write = Mage::getSingleton('core/resource')->getConnection('core_write');

        $wsSend = new EMailChefWsSend($storeId);
        $accessKey = $wsSend->loginFromId();

        /*
         * Create a New Group on EMailChef
         */
        $post['emailchefNewGroupName'] = trim($post['emailchefNewGroupName']);
        if ($post['emailchefNewGroup'] && strlen($post['emailchefNewGroupName'])) {
            require_once dirname(__FILE__).'/../../Model/EMailChefWsImport.php';
            $wsImport = new EMailChefWsImport($storeId);
            $post['emailchefGroupId'] = $wsImport->CreaGruppo(array(
                'idList' => $post['emailchefIdList'],
                'listGUID' => $post['emailchefListGUID'],
                'newGroupName' => $post['emailchefNewGroupName'],
            ), $accessKey);
        }

        /*
         * Makes batches if required. Separate the jobs into max amount of customers.
         * Create a new job for each batch.
         */
        foreach ($batches as $batchNumber => $batch) {
            try {
                $customerCount = 0;
                /*
                 * We have split into subscribers and non-subscribers
                 */
                foreach ($batch as $subscribeStatus => $customerIdArray) {
                    if (empty($customerIdArray)) {
                        continue;
                    }

                    $job = Mage::getModel('emailchef/job');
                    /* @var $job EMailChef_EMailChefSync_Model_Job */
                    $job->setData(array(
                        'emailchefgroupid' => $post['emailchefGroupId'],
                        'status' => 'queued',
                        'queue_datetime' => gmdate('Y-m-d H:i:s'),
                        'store_id' => $storeId,
                        'list_id' => $post['emailchefIdList'],
                        'list_guid' => $post['emailchefListGUID'],
                    ));
                    try {
                        $job->save();
                        $config->dbLog(
                            sprintf(
                                'Job [Insert] [Group:%s] [%s] [%d]',
                                $post['emailchefGroupId'],
                                $subscribeStatus,
                                count($customerIdArray)
                           ),
                           $job->getId(),
                           $storeId
                       );
                    } catch (Exception $e) {
                        $config->dbLog("Job [Insert] [FAILED] [Group:{$post['emailchefGroupId']}] ", 0, $storeId);
                        $config->log($e);
                        throw $e;
                    }
                    /*
                     * Each Customer
                     */
                    foreach ($customerIdArray as $customerId) {
                        ++$customerCount;
                        //$customer = Mage::getModel('customer/customer');
                        /* @var $customer Mage_Customer_Model_Customer */
                        $jobTask = Mage::getModel('emailchef/sync');
                        /* @var $jobTask EMailChef_EMailChefSync_Model_Sync */
                        try {
                            $jobTask->setData(array(
                                'customer_id' => $customerId,
                                'entity' => 'customer',
                                'job_id' => $job->getId(),
                                'needs_sync' => true,
                                'last_sync' => null,
                                'store_id' => $storeId,
                            ));
                            $jobTask->save();
                        } catch (Exception $e) {
                            $config->dbLog("Job Task [Sync] [FAILED] [customer:{$customerId}] [Update]", $job->getId(), $storeId);
                            $config->log($e);
                        }
                    }
                }
                $config->dbLog("Job Task [Sync] [Customer Count:{$customerCount}]", $job->getId(), $storeId);
                /*
                 * Insert a new scheduled Task for the job.
                 */
                $cronDelay = (int) ($batchNumber * 15) + 2;
                $db_write->insert(Mage::getSingleton('core/resource')->getTableName('cron_schedule'), array(
                    'job_code' => 'emailchef_emailchefsync',
                    'status' => 'pending',
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'scheduled_at' => gmdate('Y-m-d H:i:s', strtotime("+{$cronDelay}minutes")),
                ));

                /*$schedule = Mage::getModel('cron/schedule');
                $schedule->setJobCode($jobCode)
                    ->setCreatedAt($timecreated)
                    ->setScheduledAt($timescheduled)
                    ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
                    ->save();*/

                //$config->dbLog("Secheduled Task: " . gmdate("Y-m-d H:i:s"), $job_id, $storeId);
                $message = $this->__('Members have been sent correctly');
                Mage::getSingleton('adminhtml/session')->addSuccess($message);
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $errorMessage = $this->__('Warning: no member has been selected');
                Mage::getSingleton('adminhtml/session')->addError($errorMessage);
            }
        }

        $this->_redirect('*/*');
    }

    /**
     * Build batches from the list of ids.
     *
     * We will make batches of a certain size to avoid huge long running proceses.
     * We also need to generate a different job for subscribers and none subscribers
     *
     * array(
     *      0 => array(
     *          'subscribed'     => array(),
     *          'not_subscribed' => array(),
     *      )
     * )
     *
     * @param   array   array of ids
     * @param   int
     *
     * @return array
     */
    protected function _getBatches($emailchefCustomerIds, $storeId)
    {
        $helper = Mage::helper('emailchef');
        /* @var $helper EMailChef_EMailChefSync_Helper_Data */
        $totalCustomers = count($emailchefCustomerIds);
        $batches = array_chunk($emailchefCustomerIds, self::BATCH_SIZE);
        //$totalBatches = count($customerIdBatches);
        if ($totalCustomers > self::BATCH_SIZE) {
            $this->_config()->dbLog("Batching Customers [{$totalCustomers}] CHUNKS [".self::BATCH_SIZE.']', 0, $storeId);
        }
        $batchArray = array();
        $customerCount = 0;
        foreach ($batches as $batch) {
            $subscribed = array();
            $notSubscribed = array();
            foreach ($batch as $customerId) {
                ++$customerCount;
                if ($helper->isSubscriber($customerId, $storeId)) {
                    $subscribed[] = $customerId;
                } else {
                    $notSubscribed[] = $customerId;
                }
            }
            /*
             * @todo    only return segmented if both not empty.
             */
            $batchArray[] = array(
                self::STATUS_SUBSCRIBED => $subscribed,
                self::STATUS_NOT_SUBSCRIBED => $notSubscribed,
            );
        }

        return $batchArray;
    }

    /**
     * @var EMailChef_EMailChefSync_Model_Config
     */
    protected $_config;

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

    /**
     * Generate CSV.
     *
     * @todo    include stores
     */
    public function csvAction()
    {
        $post = $this->getRequest()->getPost();
        $file = '';

        if ($post['countPost'] > 0) {
            //preparo l'elenco degli iscritti da salvare nel csv
            $emailchefCustomerIds = Mage::getSingleton('core/session')->getEMailChefCustomerIds();

            //require_once(dirname(__FILE__) . '/../Helper/Data.php');
            $customersData = EMailChef_EMailChefSync_Helper_Data::getCustomersData();

            //CSV Column names
            $file = '"Email","First Name","Last Name"';
            if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_emailchef_synchro') == 1) {
                $file .= ',"Company","City","Province","Zip code","Region","Country code","Address","Fax","Phone","Customer id"';
                $file .= ',"Last Order id","Last Order date","Last Order total","Last order product ids","Last order category ids"';
                $file .= ',"Last sent order date","Last sent order id"';
                $file .= ',"Last abandoned cart date","Last abandoned cart total","Last abandoned cart id"';
                $file .= ',"Total orders amount","Last 12 months amount","Last 30 days amount","All products ids"';
            }
            $file .= ';';

            foreach ($emailchefCustomerIds as $customerId) {
                foreach ($customersData as $subscriber) {
                    if ($subscriber['email'] == $customerId['email']) {
                        $file .= "\n";
                        $file .= '"'.$subscriber['email'].'"';
                        $file .= ',"'.((!empty($subscriber['nome'])) ? $subscriber['nome'] : '').'"';
                        $file .= ',"'.((!empty($subscriber['cognome'])) ? $subscriber['cognome'] : '').'"';

                        $synchroConfig = Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_emailchef_synchro') == 1;

                        $file .= ',"'.($synchroConfig && (!empty($subscriber['azienda'])) ? $subscriber['azienda'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['città'])) ? $subscriber['città'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['provincia'])) ? $subscriber['provincia'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['cap'])) ? $subscriber['cap'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['regione'])) ? $subscriber['regione'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['paese'])) ? $subscriber['paese'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['indirizzo'])) ? $subscriber['indirizzo'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['fax'])) ? $subscriber['fax'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['telefono'])) ? $subscriber['telefono'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['IDCliente'])) ? $subscriber['IDCliente'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['IDUltimoOrdine'])) ? $subscriber['IDUltimoOrdine'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['DataUltimoOrdine'])) ? $subscriber['DataUltimoOrdine'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['TotaleUltimoOrdine'])) ? $subscriber['TotaleUltimoOrdine'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['IDProdottiUltimoOrdine'])) ? $subscriber['IDProdottiUltimoOrdine'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['IDCategorieUltimoOrdine'])) ? $subscriber['IDCategorieUltimoOrdine'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['DataUltimoOrdineSpedito'])) ? $subscriber['DataUltimoOrdineSpedito'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['IDUltimoOrdineSpedito'])) ? $subscriber['IDUltimoOrdineSpedito'] : '').'"';

                        $file .= ',"'.($synchroConfig && (!empty($subscriber['DataCarrelloAbbandonato'])) ? $subscriber['DataCarrelloAbbandonato'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['TotaleCarrelloAbbandonato'])) ? $subscriber['TotaleCarrelloAbbandonato'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['IDCarrelloAbbandonato'])) ? $subscriber['IDCarrelloAbbandonato'] : '').'"';

                        $file .= ',"'.($synchroConfig && (!empty($subscriber['TotaleFatturato'])) ? $subscriber['TotaleFatturato'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['TotaleFatturatoUltimi12Mesi'])) ? $subscriber['TotaleFatturatoUltimi12Mesi'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['TotaleFatturatoUltimi30gg'])) ? $subscriber['TotaleFatturatoUltimi30gg'] : '').'"';
                        $file .= ',"'.($synchroConfig && (!empty($subscriber['IDTuttiProdottiAcquistati'])) ? $subscriber['IDTuttiProdottiAcquistati'] : '').'"';
                        $file .= ';';

                        continue 2;
                    }
                }
            }
        }

        //lancio il download del file
        header('Content-type: application/csv');
        header('Content-Disposition: attachment;Filename=filtered_customers.csv');

        echo $file;
    }

    /**
     * Save Filters.
     */
    public function saveFilterHintAction()
    {
        $this->checkRunningImport();
        try {
            $post = $this->getRequest()->getPost();
            $filter_name = $post['filter_name'];
            unset($post['filter_name']);

            $EMailChefWsImport = Mage::getModel('emailchef/ws');
            $wsImport = new EMailChefWsImport();
            $wsImport->saveFilterHint($filter_name, $post);
        } catch (Exception $e) {
            $errorMessage = $this->__('Error: unable to save current filter');
            Mage::getSingleton('adminhtml/session')->addError($errorMessage);
        }

        $this->_redirect('*/*');
    }

    /**
     * Delete a Filter Hint.
     */
    public function deleteFilterHintAction()
    {
        $this->checkRunningImport();
        try {
            $post = $this->getRequest()->getPost();

            $EMailChefWsImport = Mage::getModel('emailchef/ws');
            $wsImport = new EMailChefWsImport();
            $wsImport->deleteFilterHint($post['filter_name']);
        } catch (Exception $e) {
            $errorMessage = $this->__('Error: unable to delete the filter');
            Mage::getSingleton('adminhtml/session')->addError($errorMessage);
        }

        $this->_redirect('*/*');
    }

    /**
     * Check if an import is currently running.
     *
     * @return type
     */
    public function checkRunningImport()
    {
        $syncTableName = Mage::getSingleton('core/resource')->getTableName('emailchef/sync');
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $cron_schedule_table = Mage::getSingleton('core/resource')->getTableName('cron_schedule');

        /*
         * @todo    check if a cron has been run in the past X minites
         *          notify if cron is npt up and running
         */
        $lastTime = $db->fetchOne("SELECT max(last_sync) FROM {$syncTableName}"); // 2013-04-18 19:23:55
        if (!empty($lastTime)) {
            $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $lastTime);
            $lastTimeObject = clone $dateTime;
            if ($dateTime) {
                $dateTime->modify('+30 minutes');
                $now = new DateTime();
                //if($dateTime < $now) {
                    Mage::getSingleton('adminhtml/session')
                        ->addNotice($this->__("Last Sync Performed: {$lastTimeObject->format('Y-m-d H:i:s e')}"))
                    ;
                //}
            }
        }

        $running_processes = $db->fetchOne(
            "SELECT count(*)
            FROM $cron_schedule_table
            WHERE job_code='emailchef_emailchefsync' AND status='running'"
        );
        if ($running_processes) {
            Mage::getSingleton('adminhtml/session')->addNotice($this->__('A eMailChef import process is running.'));

            return;
        }

        $scheduled_processes = $db->fetchOne(
            "SELECT count(*)
            FROM $cron_schedule_table
            WHERE job_code='emailchef_emailchefsync' AND status='pending'"
        );
        if ($scheduled_processes) {
            Mage::getSingleton('adminhtml/session')->addNotice($this->__('A eMailChef import process is schedules and will be executed soon.'));

            return;
        }
    }

    public function testCronAction()
    {
        $cron = new EMailChef_EMailChefSync_Model_Cron();
        $cron->run();
    }

    public function testFieldsAction()
    {
        $wsSend = new EMailChefWsSend();
        $accessKey = $wsSend->loginFromId();

        if ($accessKey !== false) {
            $fields = $wsSend->GetFields($accessKey);
            print_r($fields);
            die('success');
        } else {
            die('no access key returned');
        }
    }
}
