<?php

namespace EMailChef\Command\Api;

use EMailChef\Service\ApiService;

class GetContactFromEmailCommand
{
    protected $apiService;
    public function __construct($apiService = null)
    {
        $this->apiService = $apiService ?: new ApiService();
    }
    public function execute($listId, $email, $authKey)
    {
        $response = $this->apiService->call('get', 'apps/api/v1/contacts?limit=1000&list_id='.$listId.'&query_string='.urlencode($email), null, $authKey);
        if ($response['code'] != '200') {
            throw new \Exception('Unable to get contacts');
        } else {
            $body = $response['body'];
            foreach ($body as $c) {
                if ($c->email == $email) {
                    return $c;
                }
            }

            return false;
        }
    }
}
