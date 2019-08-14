<?php

namespace EMailChef\Command\Api;

use EMailChef\Service\ApiService;

class CreateSegmentCommand
{
    protected $apiService;
    public function __construct($apiService = null)
    {
        $this->apiService = $apiService ?: new ApiService();
    }
    public function execute($authKey, $listId, $name, $description, $logic = 'AND', $conditionGroups = array())
    {
        $data = array(
      'instance_in' => array(
        'list_id' => $listId,
        'logic' => $logic,
        'name' => $name,
        'condition_groups' => $conditionGroups,
        'description' => $description,
      ),
    );
        $response = $this->apiService->call('post', 'apps/api/v1/segments?list_id='.$listId, json_encode($data), $authKey);
        if ($response['code'] != '200') {
            throw new \Exception('Unable to create segment');
        } else {
            $group = $response['body'];
            $groupId = $group->id;

            return $groupId;
        }
    }
}
