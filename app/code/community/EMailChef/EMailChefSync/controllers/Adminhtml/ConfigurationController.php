<?php

require_once dirname(__FILE__).'/../../Model/EMailChefWsImport.php';
require_once dirname(__FILE__).'/../../Model/Wssend.php';
use EMailChef\Command\Api\CreateCustomFieldCommand;
class EMailChef_EMailChefSync_Adminhtml_ConfigurationController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $url = Mage::getModel('adminhtml/url');
        $url = $url->getUrl('adminhtml/system_config/edit', array(
            'section' => 'emailchef_newsletter',
        ));
        Mage::app()->getResponse()->setRedirect($url);
    }


      /**
       * Get lists.
       */
      public function getlistsAction()
      {

          $lists = Mage::getSingleton('emailchef/source_lists')->toOptionArray(null);

          // Render output directly (as output is so simple)
          $output = '';
          foreach ($lists as $list) {
              $output .= "<option value=\"{$list['value']}\">{$list['label']}</option>\n";
          }

          $this->getResponse()->setBody($output);
      }

    /**
     * Get groups for given list.
     */
    public function getgroupsAction()
    {
        // Get passed list ID to get groups for
        $listId = $this->getRequest()->getParam('list');
        if ($listId === null) {
            $output = '<option>-- Could not find list --</option>';
        } else {
            $groups = Mage::getSingleton('emailchef/source_groups')->toOptionArray(null, $listId);

            // Render output directly (as output is so simple)
            $output = '';
            foreach ($groups as $group) {
                $output .= "<option value=\"{$group['value']}\">{$group['label']}</option>\n";
            }
        }

        $this->getResponse()->setBody($output);
    }

    /**
     * Run connection-to-emailchef test other system configurations that area relevant.
     */
    public function testconnectionAction()
    {
        Mage::app()->cleanCache();

        // Get login details from AJAX
        $usernameWs = $this->getRequest()->getParam('username_ws');
        $passwordWs = $this->getRequest()->getParam('password_ws');

        // Ensure that all required fields are given
        if ($usernameWs === null || $passwordWs === null) {
            $class = 'notice';
            $message = $this->__('Please fill in eMailChef Email and Password before testing');
            $output = '<ul class="messages"><li class="'.$class.'-msg"><ul><li>'.$message.'</li></ul></li></ul>';
            $this->getResponse()->setBody($output);

            return;
        }

        $messages = array();

        // Close connection to avoid mysql gone away errors
        $res = Mage::getSingleton('core/resource');
        $res->getConnection('core_write')->closeConnection();

        // Test connection
        $storeId = Mage::app()->getStore();
        $retConn = Mage::helper('emailchef')->testConnection($usernameWs, $passwordWs, $storeId);
        $messages = array_merge($messages, $retConn);

        // Config tests
        $retConfig = Mage::helper('emailchef')->testConfig();
        $messages = array_merge($messages, $retConfig);

        // Re-open connection to avoid mysql gone away errors
        $res->getConnection('core_write')->getConnection();

        // Connect up the messages to be returned as ajax
        $renderedMessages = array();
        if (count($messages) > 0) {
            foreach ($messages as $msg) {
                $renderedMessages[] = '<li class="'.$msg['type'].'-msg"><ul><li>'.$msg['message'].'</li></ul></li>';
            }
        }
        $output = '<ul class="messages">'.implode("\n", $renderedMessages).'</ul>';
        $this->getResponse()->setBody($output);
    }

    public function createfieldsAction(){
      $storeId = Mage::app()->getStore();
      $wsSend = new EMailChefWsSend($storeId);
      $authKey = $wsSend->loginFromId();
      $listId = Mage::getStoreConfig('emailchef_newsletter/emailchef/list');
      $createCustomFieldCommand = new CreateCustomFieldCommand();
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_TEXT,'Company','company');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_TEXT,'Address','address');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_TEXT,'City','city');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_TEXT,'ZIP','zip');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_TEXT,'Province','province');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_TEXT,'Region','region');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_TEXT,'Country','country');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_TEXT,'Phone','phone');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_TEXT,'Fax','fax');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_TEXT,'Date Of Birth','dateofbirth');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_TEXT,'Gender','gender');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_NUMBER,'Customer ID','customerid');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_NUMBER,'Latest Abandoned Cart Total','latestabandonedcarttotal');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_DATE,'Latest Abandoned Cart Date','latestabandonedcartdate');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_DATE,'Latest Shipped Order Date','latestshippedorderdate');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_NUMBER,'Latest Shipped Order ID','latestshippedorderid');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_TEXT,'All Ordered Product IDs','allorderedproductids');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_TEXT,'Latest Order Category IDs','latestordercategoryids');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_NUMBER,'Total Ordered Last 30d','totalorderedlast30d');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_NUMBER,'Total Ordered Last 12m','totalorderedlast12m');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_NUMBER,'Total Ordered','totalordered');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_NUMBER,'Latest Abandoned Cart ID','latestabandonedcartid');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_NUMBER,'Latest Order Amount','latestorderamount');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_DATE,'Latest Order Date','latestorderdate');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_NUMBER,'Latest Order ID','latestorderid');
      $createCustomFieldCommand->execute($authKey,$listId,CreateCustomFieldCommand::DATA_TYPE_TEXT,'Latest Order Product IDs','latestorderproductids');

      $map = array(
        'Company'=>'company',
        'Address'=>'address',
        'City'=>'city',
        'ZIP'=>'zip',
        'Province'=>'province',
        'Region'=>'region',
        'LatestAbandonedCartTotal'=>'latestabandonedcarttotal',
        'LatestAbandonedCartDate'=>'latestabandonedcartdate',
        'LatestShippedOrderDate'=>'latestshippedorderdate',
        'LatestShippedOrderID'=>'latestshippedorderid',
        'AllOrderedProductIDs'=>'allorderedproductids',
        'LatestOrderCategoryIDs'=>'latestordercategoryids',
        'TotalOrderedLast30d'=>'totalorderedlast30d',
        'TotalOrderedLast12m'=>'totalorderedlast12m',
        'TotalOrdered'=>'totalordered',
        'LatestAbandonedCartID'=>'latestabandonedcartid',
        'Fax'=>'fax',
        'DateOfBirth'=>'dateofbirth',
        'Gender'=>'gender',
        'Country'=>'country',
        'CustomerID'=>'customerid',
        'Phone'=>'phone',
        'LatestOrderAmount'=>'latestorderamount',
        'LatestOrderDate'=>'latestorderdate',
        'LatestOrderID'=>'latestorderid',
        'LatestOrderProductIDs'=>'latestorderproductids',
      );
      foreach($map as $key=>$value){
        Mage::getConfig()->saveConfig('emailchef_newsletter/emailchef_mapping/'.$key, $value, 'default', 0);
      }

      Mage::app()->cleanCache();

      Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl('emailchef/adminhtml_configuration/index'));
    }
}
