<?php

namespace EMailChef\Command\Api;

use EMailChef\Service\ApiService;

class CreateCustomFieldCommand
{
    const DATA_TYPE_TEXT = 'text';
    const DATA_TYPE_NUMBER = 'number';
    const DATA_TYPE_DATE = 'date';

    protected $apiService;
    public function __construct($apiService = null)
    {
        $this->apiService = $apiService ?: new ApiService();
    }
    public function execute($authKey, $listId, $type, $name, $placeHolder)
    {
      $data = array(
        'instance_in' => array(
        'data_type' => $type,
        'name' => $name,
        'place_holder' => $placeHolder,
      ),
    );
        $response = $this->apiService->call('post', 'apps/api/v1/lists/'.$listId.'/customfields', json_encode($data), $authKey);
        if ($response['code'] != '200') {
            throw new \Exception('Unable to create custom field');
        } else {
            $result = $response['body'];

            return $result->custom_field_id;
        }
    }
}
