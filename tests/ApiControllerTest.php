<?php

class ApiControllerTest extends SapphireTest {

  /**
   * Some static helpers for api specific requests and data tramsforming
   */

  static function request($method, $url, $data = null, $accessToken = null) {
    $body = null;
    if (is_array($data)) {
      $body = json_encode($data);
      if (!$body) {
        user_error('Could not encode json from $data');
      }
    }
    $headers = [
      "Content-Type" => "application/json",
    ];
    if ($accessToken) {
      $headers['X-Accesstoken'] = $accessToken;
    }
    return Director::test($url, $postVars = null, $session = null, $httpMethod = $method, $body, $headers);
  }

  static function extract_code_and_data_from_response($resp) {
    $data = json_decode($resp->getBody(), true);
    if (!$data) {
      user_error('Could not parse JSON from body `'.$resp->getBody().'`');
    }
    return [
      "statusCode" => $resp->getStatusCode(),
      "data" => $data,
    ];
  }

  static function send_test($method, $url, $data = null, $accessToken = null) {
    return self::extract_code_and_data_from_response(self::request($method, $url, $data, $accessToken));
  }

  static function ensure_correct_config($testSuite) {
    $expectedConfig = [
      "ApiController" => [
        "underscoreFields" => true,
        "useAccesstokenAuth" => true,
        "accessTokenPropertyName" => 'X-Accesstoken',
        "allowOverrideConfiguration" => true,
      ],
      "AuthSession" => [
        "validInMinutesFromNow" => 10080,
        "adminAccessToken" => "84c44b7ee63a9919aa272673eecfc7f9b7424e47",
        "requiredGroup" => null,
        "requiredPermission" => null,
        "urlSegment" => 'auth',
      ],
      "DataObject" => [
        "jsonDateFormat" => 'D M d Y H:i:s O',
        "underscoreFields" => true,
        "castDataObjectFields" => true,
      ],
    ];
    foreach($expectedConfig as $className => $fields) {
      foreach($fields as $property => $value) {
        $testSuite->assertEquals($value, Config::inst()->get($className, $property));
      }
    }
  }

  function testActionPermissions() {
    // TODO: write test
  }

}
