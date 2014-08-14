<?php


class AuthControllerTest extends SapphireTest {

    // Define the fixture file to use for this test class
    protected static $fixture_file = 'ApiDataObjectTest.yml';

    function testExpectedDefaultConfigValues() {
      ApiControllerTest::ensure_correct_config($this);
    }

    function testAuthWithWrongCredentials() {
      $data = [
        "email" => "jdancm",
        "password" => "cklmncac",
      ];
      $res = ApiControllerTest::test('POST', 'auth/session', $data);
      $this->assertEquals(400, $res['statusCode']);
      $this->assertEquals("Couldn't match password / email", $res['data']['error']);
      unset($data["password"]);
      $res = ApiControllerTest::test('POST', 'auth/session', $data);
      $this->assertEquals(422, $res['statusCode']);
      $this->assertEquals("The JSON property `password` is required", $res['data']['error']);
    }

    function testAuthWithValidCredentials() {
      $data = [
        "email" => "admin@silverstripe.com",
        "password" => "password",
      ];
      $res = ApiControllerTest::test('POST', 'auth/session', $data);
      $this->assertEquals(201, $res['statusCode']);
      $this->assertTrue(strlen($res['data']['data']['accesstoken'])>6);
    }

    function testValidSession() {
      $data = [
        "email" => "admin@silverstripe.com",
        "password" => "password",
      ];
      $res = ApiControllerTest::test('POST', 'auth/session', $data);
      $accessToken = $res['data']['data']['accesstoken'];
      $res = ApiControllerTest::test('GET', 'auth/session', null, $accessToken);
      $this->assertEquals(200, $res['statusCode']);
      $this->assertEquals($accessToken, $res['data']['data']['accesstoken']);
      // TODO: test validuntil!
    }

    function testDeleteSession() {
      $data = [
        "email" => "admin@silverstripe.com",
        "password" => "password",
      ];
      // create session
      $res = ApiControllerTest::test('POST', 'auth/session', $data);
      $accessToken = $res['data']['data']['accesstoken'];
      // check session exists (redundant test segment (post+get), but ensures that test succeeded until here)
      $res = ApiControllerTest::test('GET', 'auth/session/', null, $accessToken);
      $this->assertEquals(200, $res['statusCode']);
      // delete session (i.e. logout)
      $url = 'auth/session/'.$accessToken;
      $res = ApiControllerTest::test('DELETE', $url, null, $accessToken);
      $this->assertEquals(202, $res['statusCode']);
      $res = ApiControllerTest::test('GET', 'auth/session/', null, $accessToken);
      $this->assertEquals(404, $res['statusCode']);
      // TODO: test validuntil!
    }

    function testOverrideConfigValuesInHeader() {

    }

    function testAdminAccessToken() {
      $adminAccessToken = Config::inst()->get('AuthSession', 'adminAccessToken');
      $accessToken = sha1('somerandomaccesstoken');
      $res = ApiControllerTest::test('GET', 'auth/session/', null, $accessToken);
      // we expect neither to find a session
      $this->assertEquals(404, $res['statusCode']);
      $res = ApiControllerTest::test('GET', 'auth/permission/ADMIN', null, $accessToken);
      // neither to have any admin privileges
      $this->assertEquals('ADMIN', $res['data']['data']['permission']['code']);
      $this->assertEquals(false, $res['data']['data']['permission']['granted']);
      // but we should have both with ad admin access token:
      // a valid session
      $res = ApiControllerTest::test('GET', 'auth/session/', null, $adminAccessToken);
      $this->assertEquals($adminAccessToken, $res['data']['data']['accesstoken']);
      $this->assertTrue($res['data']['data']['is_valid']);
      // and admin permission(s)
      $res = ApiControllerTest::test('GET', 'auth/permission/ADMIN', null, $adminAccessToken);
      $this->assertEquals('ADMIN', $res['data']['data']['permission']['code']);
      $this->assertEquals(true, $res['data']['data']['permission']['granted']);
    }


}
