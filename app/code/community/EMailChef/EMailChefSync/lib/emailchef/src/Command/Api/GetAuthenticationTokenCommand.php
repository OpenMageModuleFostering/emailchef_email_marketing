<?php

namespace EMailChef\Command\Api;

use EMailChef\Service\ApiService;

class GetAuthenticationTokenCommand
{
    protected $apiService;
    public function __construct($apiService = null)
    {
        $this->apiService = $apiService ?: new ApiService();
    }
    public function execute($username, $password)
    {
        $response = $this->apiService->call('post', 'api/login', json_encode(array('username' => $username, 'password' => $password)));
        if ($response['code'] != '200') {
            throw new \Exception('Unable to login');
        } else {
            if (ApiService::STAGING) {
                return $response['body']->authkeystaging;
            }

            return $response['body']->authkey;
        }
    }
}
