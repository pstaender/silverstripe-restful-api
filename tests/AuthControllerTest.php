<?php


class AuthControllerTest extends SapphireTest
{

    // Define the fixture file to use for this test class
    protected static $fixture_file = 'ApiDataObjectTest.yml';

    public function testExpectedDefaultConfigValues()
    {
        ApiControllerTest::ensure_correct_config($this);
    }

    public function testAuthWithWrongCredentials()
    {
        $data = [
        "email" => "jdancm",
        "password" => "cklmncac",
      ];
        $res = ApiControllerTest::send_test('POST', 'auth/session', $data);
        $this->assertEquals(400, $res['statusCode']);
        $this->assertEquals("Couldn't match password / email", $res['data']['error']);
        unset($data["password"]);
        $res = ApiControllerTest::send_test('POST', 'auth/session', $data);
        $this->assertEquals(422, $res['statusCode']);
        $this->assertEquals("The JSON property `password` is required", $res['data']['error']);
    }

    public function testAuthWithValidCredentials()
    {
        $data = [
        "email" => "admin@silverstripe.com",
        "password" => "password",
      ];
        $res = ApiControllerTest::send_test('POST', 'auth/session', $data);
        $this->assertEquals(201, $res['statusCode']);
        $this->assertTrue(strlen($res['data']['session']['accesstoken'])>6);
    }

    public function testValidSession()
    {
        $data = [
        "email" => "admin@silverstripe.com",
        "password" => "password",
      ];
        $res = ApiControllerTest::send_test('POST', 'auth/session', $data);
        $accessToken = $res['data']['session']['accesstoken'];
        $res = ApiControllerTest::send_test('GET', 'auth/session', null, $accessToken);
        $this->assertEquals(200, $res['statusCode']);
        $this->assertEquals($accessToken, $res['data']['session']['accesstoken']);
      // TODO: test validuntil!
    }

    public function testDeleteSession()
    {
        $data = [
        "email" => "admin@silverstripe.com",
        "password" => "password",
      ];
      // create session
      $res = ApiControllerTest::send_test('POST', 'auth/session', $data);
        $accessToken = $res['data']['session']['accesstoken'];
      // check session exists (redundant test segment (post+get), but ensures that test succeeded until here)
      $res = ApiControllerTest::send_test('GET', 'auth/session/', null, $accessToken);
        $this->assertEquals(200, $res['statusCode']);
      // delete session (i.e. logout)
      $url = 'auth/session/'.$accessToken;
        $res = ApiControllerTest::send_test('DELETE', $url, null, $accessToken);
        $this->assertEquals(202, $res['statusCode']);
        $res = ApiControllerTest::send_test('GET', 'auth/session/', null, $accessToken);
        $this->assertEquals(404, $res['statusCode']);
      // TODO: test validuntil!
    }

    public function testPermissionForSession()
    {
        $data = [
        "email" => "admin@silverstripe.com",
        "password" => "password",
      ];
      // create session
      $res = ApiControllerTest::send_test('POST', 'auth/session', $data);
        $accessToken = $res['data']['session']['accesstoken'];
      // check session exists (redundant test segment, but ensures that test succeeded until here)
      $res = ApiControllerTest::send_test('GET', 'auth/session/', null, $accessToken);
        $this->assertEquals(200, $res['statusCode']);
        $res = ApiControllerTest::send_test('GET', 'auth/permission/ADMIN', null, $accessToken);
        $this->assertEquals('ADMIN', $res['data']['permission']['code']);
        $this->assertEquals(true, $res['data']['permission']['granted']);
    }

    public function testAdminAccessToken()
    {
        $adminAccessToken = Config::inst()->get('AuthSession', 'adminAccessToken');
        $accessToken = sha1('somerandomaccesstoken');
        $res = ApiControllerTest::send_test('GET', 'auth/session/', null, $accessToken);
      // we expect neither to find a session
      $this->assertEquals(404, $res['statusCode']);
        $res = ApiControllerTest::send_test('GET', 'auth/permission/ADMIN', null, $accessToken);
      // neither to have any admin privileges
      $this->assertEquals('ADMIN', $res['data']['permission']['code']);
        $this->assertEquals(false, $res['data']['permission']['granted']);
      // but we should have both with ad admin access token:
      // a valid session
      $res = ApiControllerTest::send_test('GET', 'auth/session/', null, $adminAccessToken);
        $this->assertEquals($adminAccessToken, $res['data']['session']['accesstoken']);
        $this->assertTrue($res['data']['session']['is_valid']);
      // and admin permission(s)
      $res = ApiControllerTest::send_test('GET', 'auth/permission/ADMIN', null, $adminAccessToken);
        $this->assertEquals('ADMIN', $res['data']['permission']['code']);
        $this->assertEquals(true, $res['data']['permission']['granted']);
    }

    public function testRequiredFields()
    {
        $adminAccessToken = Config::inst()->get('AuthSession', 'adminAccessToken');
        $res = ApiControllerTest::send_test('GET', 'auth/permission/', null, $adminAccessToken);
        $this->assertEquals("The URL parameter `ID` is required", $res['data']['error']);
        $this->assertEquals(422, $res['data']['code']);
    }

    public function testTypeOfFields()
    {
        $adminAccessToken = Config::inst()->get('AuthSession', 'adminAccessToken');
        $negativeTests = [
          "integer" => [ 4.4, "abc", "true", "false", ],
          "float"   => [ "abc", "true", "false", ],
          "boolean" => [ "abc", 2 ],
      ];

        $positiveTests = [
          "integer" => [ -1, 0, 1 ],
          "boolean" => [ true, false, "true", "false", "0", "1" ],
          "float"   => [ 0.1, -1.1, 1.1, 1, 1.0 ],
      ];
        foreach ($negativeTests as $type => $values) {
            foreach ($values as $value) {
                $res = ApiControllerTest::send_test('GET', 'auth/permission/ADMIN', [ $type => $value ], $adminAccessToken);
                $this->assertEquals(422, $res['data']['code']);
                $this->assertEquals(422, $res['statusCode']);
                $this->assertEquals(1, preg_match("/^The JSON property `$type` has to be a(n)* $type$/", trim($res['data']['error'])));
            }
        }
        foreach ($positiveTests as $type => $values) {
            foreach ($values as $value) {
                $res = ApiControllerTest::send_test('GET', 'auth/permission/ADMIN', [ $type => $value ], $adminAccessToken);
                $this->assertEquals(200, $res['statusCode']);
            }
        }
        $res = ApiControllerTest::send_test('GET', 'auth/permission/2', [ $type => $value ], $adminAccessToken);
        $this->assertEquals(422, $res['statusCode']);
        $this->assertEquals(422, $res['data']['code']);
        $this->assertEquals("The URL parameter `ID` has to match the following pattern: `/^[a-zA-Z\_]+$/`", $res['data']['error']);
        $res = ApiControllerTest::send_test('GET', 'auth/permission/abc_EEFG', [ $type => $value ], $adminAccessToken);
        $this->assertEquals(200, $res['statusCode']);
    }


    public function testExpiredSession()
    {
        $session = $this->objFromFixture('AuthSession', 'expired');
        $this->assertEquals(false, $session->IsValid());
        $res = ApiControllerTest::send_test('GET', 'auth/session/', null, $session->Accesstoken());
        $session = $this->objFromFixture('AuthSession', 'valid');
        $this->assertEquals(true, $session->IsValid());
        $res = ApiControllerTest::send_test('GET', 'auth/session/', null, $session->Accesstoken());
    }
}
