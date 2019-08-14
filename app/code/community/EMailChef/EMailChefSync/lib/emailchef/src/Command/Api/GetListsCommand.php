<?php

namespace EMailChef\Command\Api;

use EMailChef\Service\ApiService;

class GetListsCommand
{
    protected $apiService;
    public function __construct($apiService = null)
    {
        $this->apiService = $apiService ?: new ApiService();
    }
    public function execute($authKey, $fillGroups = true)
    {
        $response = $this->apiService->call('get', 'apps/api/v1/lists?limit=1000', null, $authKey);
        if ($response['code'] != '200') {
            throw new \Exception('Unable to login');
        } else {
            $lists = $response['body'];

            if ($fillGroups) {
                $getListSegmentsCommand = new GetListSegmentsCommand();
                foreach ($lists as &$list) {
                    $list->groups = $getListSegmentsCommand->execute($list->id, $authKey);
                    if (!is_array($list->groups)) {
                        $list->groups = array();
                    }
                }
            }

            return $lists;
        }
    }
}
