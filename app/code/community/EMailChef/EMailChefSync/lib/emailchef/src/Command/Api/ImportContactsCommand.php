<?php

namespace EMailChef\Command\Api;

use EMailChef\Service\ApiService;

class ImportContactsCommand
{
    protected $apiService;
    public function __construct($apiService = null)
    {
        $this->apiService = $apiService ?: new ApiService();
    }
    public function execute($authKey, $contacts, $listId)
    {
        $contactsImport = array();
        foreach ($contacts as $contact) {
            $c = array();
            foreach ($contact as $field => $value) {
                $c[] = array(
            'placeholder' => $field,
            'value' => $value,
        );
            }
            $contactsImport[] = $c;
        }

        $data = array(
		  'instance_in' => array(
			'contacts' => $contactsImport,
			'notification_link' => '',
		  ),
		);
        $response = $this->apiService->call('post', 'apps/api/v1/lists/'.$listId.'/import', json_encode($data), $authKey);
        if ($response['code'] != '200') {
            throw new \Exception('Unable to import contacts');
        } else {
            //$body = $response['body'];
			return true;
        }
    }
}
