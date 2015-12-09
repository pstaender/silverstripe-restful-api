<?php

class ApiControllerTest extends SapphireTest {

  protected static $fixture_file = 'ApiDataObjectTest.yml';

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
    // TODO:
    // ---
    // Except:
    //   environment: 'live'
    // ---
    // AuthSession:
    //   adminAccessToken: 84c44b7ee63a9919aa272673eecfc7f9b7424e47
    return Director::test($url, $postVars = null, $session = null, $httpMethod = $method, $body, $headers);
  }

  static function extract_code_and_data_from_response($resp, $expectJSON = true) {
    $data = json_decode($resp->getBody(), true);
    if (!$data) {
      if ($expectJSON) {
        user_error('Could not parse JSON from body `'.$resp->getBody().'`');
      } else {
        return [
          "body" => $resp->getBody(),
          "statusCode" => $resp->getStatusCode(),
        ];
      }
    }
    return [
      "statusCode" => $resp->getStatusCode(),
      "data" => $data,
    ];
  }

  static function send_test($method, $url, $data = null, $accessToken = null, $expectJSON = true) {
    return self::extract_code_and_data_from_response(self::request($method, $url, $data, $accessToken), $expectJSON);
  }

  static function ensure_correct_config($testSuite) {
    $expectedConfig = [
      "ApiController" => [
        "underscoreFields" => true,
        "useAccesstokenAuth" => true,
        "accessTokenPropertyName" => 'X-Accesstoken',
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
        "useDataProperty" => false,
      ],
    ];
    foreach($expectedConfig as $className => $fields) {
      foreach($fields as $property => $value) {
        $testSuite->assertEquals($value, Config::inst()->get($className, $property));
      }
    }
  }

  /**
   * We check some basic methods of the ApiController (as functional tests, not unit tests for now)
   * TODO: replace with unit tests maybe …
   * e.g. checkAccessAction, checkPermission …
   */
  function testResponseTypes() {
    $invalidAccessToken = sha1(rand(0,10000).time());
    $adminAccessToken   = Config::inst()->get('AuthSession', 'adminAccessToken');
    $expectJSON = false;
    $msgValidSession = "This data should only be seen if we have a valid session";
    $res = ApiControllerTest::send_test('GET', 'auth/testIsValidSession', $data = null, $invalidAccessToken, $expectJSON);
    // $this->assertEquals($res['body'], "Action 'testIsValidSession' isn't allowed on class AuthController.");
    $this->assertEquals($res['statusCode'], 403);
    $res = ApiControllerTest::send_test('GET', 'auth/testIsValidSession', $data = null, $adminAccessToken);
    $this->assertEquals($res['data']['message'], $msgValidSession);
    $this->assertEquals($res['statusCode'], 200);
    $res = ApiControllerTest::send_test('GET', 'auth/testAPIPermission', $data = null, $invalidAccessToken, $expectJSON);
    // $this->assertEquals($res['body'], "Action 'testAPIPermission' isn't allowed on class AuthController.");
    $this->assertEquals($res['statusCode'], 403);
    $session = $this->objFromFixture('AuthSession', 'api');
    $res = ApiControllerTest::send_test('GET', 'auth/testAPIPermission', $data = null, $session->Accesstoken());
    $this->assertEquals($res['data']['message'], $msgValidSession);
    $this->assertEquals($res['statusCode'], 200);
    $res = ApiControllerTest::send_test('GET', 'auth/testADMINPermission', $data = null, $session->Accesstoken(), $expectJSON);
    $this->assertEquals($res['statusCode'], 403);
    $session = $this->objFromFixture('AuthSession', 'valid');
    $res = ApiControllerTest::send_test('GET', 'auth/testADMINPermission', $data = null, $session->Accesstoken());
    $this->assertEquals($res['data']['message'], $msgValidSession);
    $this->assertEquals($res['statusCode'], 200);
    $res = ApiControllerTest::send_test('GET', 'auth/testADMINPermission', $data = null, $adminAccessToken, $expectJSON);
    $this->assertEquals($res['data']['message'], $msgValidSession);
    $this->assertEquals($res['statusCode'], 200);
    $res = ApiControllerTest::send_test('GET', 'auth/testAPIPermission', $data = null, $session->Accesstoken());
    $this->assertEquals($res['statusCode'], 200);
    $res = ApiControllerTest::send_test('GET', 'auth/testADMINPermission', $data = null, $adminAccessToken);
    $this->assertEquals($res['data']['message'], $msgValidSession);
    $this->assertEquals($res['statusCode'], 200);
    $res = ApiControllerTest::send_test('GET', 'auth/testPermissionFailure', $data = null, $adminAccessToken);
    $this->assertEquals($res['statusCode'], 401);
    $this->assertEquals($res['data']['error'], 'permission failure');
    $res = ApiControllerTest::send_test('GET', 'auth/testSendError', $data = null, $adminAccessToken);
    $this->assertEquals($res['statusCode'], 500);
    $this->assertEquals($res['data']['error'], 'unspecified error');
    $res = ApiControllerTest::send_test('PUT', 'auth/testSuccessfulPut', $data = null, $adminAccessToken);
    $this->assertEquals($res['statusCode'], 201);
    $this->assertEquals($res['data']['message'], 'resource updated successfully');
    $res = ApiControllerTest::send_test('DELETE', 'auth/testSendSuccessfulDelete', $data = null, $adminAccessToken);
    $this->assertEquals($res['statusCode'], 202);
    $this->assertEquals($res['data']['message'], 'resource deleted successfully');
    $res = ApiControllerTest::send_test('POST', 'auth/testSendSuccessfulPost', $data = null, $adminAccessToken);
    $this->assertEquals($res['statusCode'], 201);
    $this->assertEquals($res['data']['message'], 'resource created succesfully');
    $res = ApiControllerTest::send_test('GET', 'auth/testSendNotFound', $data = null, $adminAccessToken);
    $this->assertEquals($res['statusCode'], 404);
    $this->assertEquals($res['data']['message'], 'resource not found');
    $res = ApiControllerTest::send_test('GET', 'auth/testWrongMethodName', $data = null, $adminAccessToken, $expectJSON);
    $this->assertEquals($res['statusCode'], 404);
    $this->assertEquals($res['body'], "Action 'testWrongMethodName' isn't available on class AuthController.");
    $res = ApiControllerTest::send_test('GET', 'auth/testSendingEmptyData', $data = null, $adminAccessToken, $expectJSON);
    $this->assertEquals($res['statusCode'], 404);
  }

}
