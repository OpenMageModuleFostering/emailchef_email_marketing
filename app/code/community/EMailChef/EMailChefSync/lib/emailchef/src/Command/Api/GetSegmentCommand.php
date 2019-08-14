<?php

namespace EMailChef\Command\Api;

use EMailChef\Service\ApiService;

class GetSegmentCommand
{
    protected $apiService;
    public function __construct($apiService = null)
    {
        $this->apiService = $apiService ?: new ApiService();
    }
    public function execute($authKey, $listId, $segmentId)
    {
        $response = $this->apiService->call('get', 'apps/api/v1/lists/'.$listId.'/segments/'.$segmentId, null, $authKey);
        if ($response['code'] != '200') {
            throw new \Exception('Unable to get segment');
        } else {
            $segment = $response['body'];

            return $segment;
        }
    }
}
