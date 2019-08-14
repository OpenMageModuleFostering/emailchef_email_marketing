<?php

namespace EMailChef\Service;

class ApiService
{
    const ENDPOINT = 'https://app.emailchef.com/';
	//const ENDPOINT = 'http://staging.emailchef.com/';

    const STAGING = false;

    public function call($method, $path, $data = null, $authKey = null)
    {
        $response = null;
        switch ($method) {
      case 'post':
        $response = \Httpful\Request::post(self::ENDPOINT.$path, $data)->addHeader('Content-Type' , 'application/json')->addHeader(self::STAGING ? 'authkeystaging' : 'authkey', $authKey)->send();
      break;
      case 'delete':
        $response = \Httpful\Request::init(\Httpful\Http::DELETE)->addHeader('Content-Type' , 'application/json')->uri(self::ENDPOINT.$path)->body($data, 'application/json')->addHeader(self::STAGING ? 'authkeystaging' : 'authkey', $authKey)->send();
      break;
      case 'put':
        $response = \Httpful\Request::put(self::ENDPOINT.$path, $data)->addHeader('Content-Type' , 'application/json')->addHeader(self::STAGING ? 'authkeystaging' : 'authkey', $authKey)->send();
      break;
      case 'get':
      default:
        $response = \Httpful\Request::get(self::ENDPOINT.$path)->addHeader('Content-Type' , 'application/json')->addHeader(self::STAGING ? 'authkeystaging' : 'authkey', $authKey)->send();
      break;
    }

        return array('body' => $response->body, 'code' => $response->code, 'debug' => $response);
    }
}
