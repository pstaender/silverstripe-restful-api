<?php

class ApiControllerTest extends SapphireTest {

  /**
   * Some static helpers for api specific requests and data tramsforming
   */

  static function request($method, $url, array $data, $accessToken = null) {
    $body = json_encode($data);
    $headers = [
      "Content-Type" => "application/json",
    ];
    if ($accessToken) {
      $headers['X-Accesstoken'] = $accesstoken;
    }
    return Director::test('auth/session', $postVars = null, $session = null, $httpMethod = $method, $body, $headers);
  }

  static function extract_code_and_data_from_response($resp) {
    $data = json_decode($resp->getBody(), true);
    if (!$data) {
      user_error('Could not parse JSON from body `'.$resp->body.'`');
    }
    return [
      "statusCode" => $resp->getStatusCode(),
      "data" => $data,
    ];
  }

  static function test($method, $url, array $data, $accessToken = null) {
    return self::extract_code_and_data_from_response(self::request($method, $url, $data, $accessToken));
  }

  static function ensure_correct_config($testSuite) {
    $expectedConfig = [
      "ApiController" => [
        "underscoreFields" => true,
        "useAccesstokenAuth" => true,
      ],
      "AuthSession" => [
        "validInMinutesFromNow" => 10080,
        "adminAccessToken" => null,
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

}
