<?php

namespace EMailChef\Command\Api;

use EMailChef\Service\ApiService;

class CreateContactCommand
{
    protected $apiService;
    public function __construct($apiService = null)
    {
        $this->apiService = $apiService ?: new ApiService();
    }
    public function execute($listId, $email, $authKey)
    {
        $data = array(
      'instance_in' => array(
        'list_id' => $listId,
        'email' => $email,
        'mode' => 'ADMIN',
        'firstname' => '',
        'lastname' => '',
        'status' => 'ACTIVE',
        'custom_fields' => array(),
      ),
    );
        $response = $this->apiService->call('post', 'apps/api/v1/contacts', json_encode($data), $authKey);
        if ($response['code'] != '200') {
            throw new \Exception('Unable to create segment');
        } else {
            return $response['body'];
        }
    }
}
