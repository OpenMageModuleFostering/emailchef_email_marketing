<?php

namespace EMailChef\Command\Api;

use EMailChef\Service\ApiService;

class UpdateSegmentCommand
{
    protected $apiService;
    public function __construct($apiService = null)
    {
        $this->apiService = $apiService ?: new ApiService();
    }
    public function execute($authKey, $listId, $name, $description, $logic = 'AND', $conditionGroups, $groupId)
    {
        $data = array(
      'instance_in' => array(
        'id' => ''.$groupId,
        'list_id' => ''.$listId,
        'logic' => $logic,
        'name' => $name,
        'condition_groups' => $conditionGroups,
        'description' => $description,
        'tmp_name' => $name,
        'tmp_description' => $description,
      ),
    );
        $response = $this->apiService->call('put', 'apps/api/v1/segments/'.$groupId.'?list_id='.$listId, json_encode($data), $authKey);
        if ($response['code'] != '200') {
            throw new \Exception('Unable to update segment');
        } else {
            $group = $response['body'];
            $groupId = $group->id;

            return $groupId;
        }
    }
}
