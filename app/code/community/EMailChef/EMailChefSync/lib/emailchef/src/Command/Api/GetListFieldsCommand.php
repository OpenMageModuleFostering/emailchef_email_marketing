<?php

namespace EMailChef\Command\Api;

use EMailChef\Service\ApiService;

class GetListFieldsCommand
{
    protected $apiService;
    public function __construct($apiService = null)
    {
        $this->apiService = $apiService ?: new ApiService();
    }
    public function execute($listId, $authKey)
    {
        $response = $this->apiService->call('get', 'apps/api/v1/lists/'.$listId.'/customfields', null, $authKey);
        if ($response['code'] != '200') {
            throw new \Exception('Unable to get custom fields');
        } else {
            return $response['body'];
        }
    }
}
