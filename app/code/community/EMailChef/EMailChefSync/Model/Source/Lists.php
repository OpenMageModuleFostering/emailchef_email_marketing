<?php
/**
 * Lists.php.
 */
require_once dirname(__DIR__).'/EMailChefWsImport.php';
require_once dirname(__DIR__).'/Wssend.php';

class EMailChef_EMailChefSync_Model_Source_Lists
{
    /**
     * @var array
     */
    protected $_cache = array();

    /**
     * Get as options.
     *
     * array(
     *     array(
     *          'value'     => (string)$list['idList'],
     'label'     => (string)$list['listName'],
     'guid'      =>(string)$list['listGUID'],
     "groups"    => array(
     *           ...
     *          )
     *     )
     * )
     *
     * @return array
     */
    public function toOptionArray($storeId = null)
    {
        $websiteCode = Mage::app()->getRequest()->getParam('website');
        $storeCode = Mage::app()->getRequest()->getParam('store');

        if (isset($storeId) && $storeId != false) {
            $storeId = $storeId; // ?
        } elseif ($storeCode) {
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

        // Create select
        $selectLists = array();

        if (Mage::getStoreConfig('emailchef_newsletter/emailchef/username_ws', $storeId)
            && Mage::getStoreConfig('emailchef_newsletter/emailchef/password_ws', $storeId)) {
            $wsSend = new EMailChefWsSend($storeId);
            $accessKey = $wsSend->loginFromId();

            if ($accessKey !== false) {
                require_once dirname(__DIR__).'/EMailChefWsImport.php';
                $wsImport = new EMailChefWsImport($storeId);

                $lists = $wsImport->GetNlList($accessKey);

                $selectLists = array(array('value' => 0, 'label' => '-- Select a list (if any) --'));

                foreach ($lists as $list) {
                    $rGroups = array();
                  /*
                  $groups= $list->groups;
                  foreach($groups as $group){
                    $rGroups[(string)$group->id] = (string)$group->name;
                  }*/
                  $selectLists[] = array(
                      'value' => (string) $list->id,
                      'label' => (string) $list->name,
                      'guid' => (string) $list->id,
                      'groups' => $rGroups,
                  );
                }
            } else {
                if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log', $storeId)) {
                    Mage::log('LoginFromId failed');
                }
                $selectLists[0] = array('value' => 0, 'label' => $GLOBALS['__sl_emailchef_login_error']);
            }
        }

        return $selectLists;
    }

    /**
     * Get an array of list data, and its groups.
     *
     * @param $listId
     * @param $storeId
     *
     * @return bool|array
     */
    public function getListDataArray($listId, $storeId)
    {
        $listData = $this->getDataArray($storeId);
        if (isset($listData[$listId])) {
            return $listData[$listId];
        }

        // If list not found, return false
        if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log', $storeId)) {
            Mage::log('Invalid List ID: '.$listId);
        }

        return false;
    }

    /**
     * Get an array of all lists, and their groups!
     *
     * @param string $storeId
     *
     * @return array
     */
    public function getDataArray($storeId)
    {
        $selectLists = array();

        // If cache is set, use that
        if (isset($this->_cache[$storeId])) {
            return $this->_cache[$storeId];
        }

        // If login details not set, return empty list
        if (!$this->_config()->getUsername($storeId) ||
                !$this->_config()->getPassword($storeId)) {
            if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log', $storeId)) {
                Mage::log('Login details not complete - cannot retrieve lists');
            }

            return $selectLists;
        }

        // Attempt login (return empty if fails)
        $wsSend = new EMailChefWsSend($storeId);
        $accessKey = $wsSend->loginFromId();
        if ($accessKey === false) {
            if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log', $storeId)) {
                Mage::log('Login failed - cannot retrieve lists');
            }

            return $selectLists;
        }

        // Attempt to make call to get lists from API
        require_once dirname(__DIR__).'/EMailChefWsImport.php';
        $wsImport = new EMailChefWsImport($storeId);
        $lists = $wsImport->GetNlList($accessKey, true);
        if (!$lists) {
            if (Mage::getStoreConfig('emailchef_newsletter/emailchef/enable_log', $storeId)) {
                Mage::log('eMailChefWsImport got empty response when fetching lists even though login succeeded');
            }

            return $selectLists;
        }

        foreach ($lists as $list) {
            $rGroups = array();
            $groups = $list->groups;
            foreach ($groups as $group) {
                $rGroups[(string) $group->id] = (string) $group->name;
            }
            $selectLists[$list->id] = array(
              'value' => (string) $list->id,
              'label' => (string) $list->name,
              'guid' => (string) $list->id,
              'groups' => $rGroups,
          );
        }
        // Cache results as this is a success
        $this->_cache[$storeId] = $selectLists;

        return $selectLists;
    }

    /**
     * Get a List Guid.
     *
     * @param   int
     * @param   int
     *
     * @return string|false
     */
    public function getListGuid($listId, $storeId)
    {
        $listData = $this->getListDataArray($listId, $storeId);

        if ($listData === false || !isset($listData['listGUID'])) {
            return false;
        }

        return $listData['listGUID'];
    }

    /**
     * Get the groups for a given list.
     *
     * @param   int|false
     */
    public function getListGroups($listId, $storeId)
    {
        $listData = $this->getListDataArray($listId, $storeId);

        if ($listData === false || !isset($listData['groups'])) {
            return false;
        }

        return $listData['groups'];
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
}
