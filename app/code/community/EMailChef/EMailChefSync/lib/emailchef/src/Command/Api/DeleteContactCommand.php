<?php

namespace EMailChef\Command\Api;

use EMailChef\Service\ApiService;

class DeleteContactCommand
{
    protected $apiService;
    public function __construct($apiService = null)
    {
        $this->apiService = $apiService ?: new ApiService();
    }
    public function execute($listId, $contactId, $authKey)
    {
        $data = array(
      'instance_in' => array(
          'contact_id' => $contactId,
          'list_id' => $listId,
          'mode' => 'ADMIN',
          'remove' => true,
      ),
    );
        $response = $this->apiService->call('delete', 'apps/api/v1/contacts/'.$contactId, $data, $authKey);
        if ($response['code'] != '200') {
            throw new \Exception('Unable to login');
        } else {
            return true;
        }
    }
}
