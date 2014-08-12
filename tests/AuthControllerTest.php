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


}
