<?php


class ApiDataObjectTest extends SapphireTest {

    // Define the fixture file to use for this test class
    protected static $fixture_file = 'ApiDataObjectTest.yml';

    /**
      * This is not a test method, more a check to ensure that we work
      * with the `correct` parameters
      */
    function testExpectedDefaultConfigValues() {
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
        ],
        "DataObject" => [
          "jsonDateFormat" => 'D M d Y H:i:s O',
          "underscoreFields" => true,
          "castDataObjectFields" => true,
        ],
      ];
      foreach($expectedConfig as $className => $fields) {
        foreach($fields as $property => $value) {
          $this->assertEquals($value, Config::inst()->get($className, $property));
        }
      }
    }

    function testRealFieldNameMapping() {
      $map = [
        "first_name" => "FirstName",
        "email" => "Email",
        "id" => "ID",
        "created" => "Created",
        "class_name" => "ClassName",
        "last_edited" => "LastEdited",
      ];
      foreach($map as $input => $expected) {
        $this->assertEquals($expected, ApiDataObject::real_field_name($input, 'Member'));
      }
    }

    function testUnderscoreTransforming() {
      $map = [
        "first_name" => "FirstName",
        "email" => "Email",
        "id" => "ID",
        "created" => "Created",
        "class_name" => "ClassName",
        "last_edited" => "LastEdited",
      ];
      foreach($map as $expected => $input) {
        $this->assertEquals($expected, ApiDataObject::to_underscore($input));
      }
    }

    function testDataForApi() {
        $expectedData = [
          'first_name' => 'Api',
          'surname' => 'User',
        ];
        $obj = $this->objFromFixture('Member', 'api');
        $apiData = $obj->forApi();
        foreach($expectedData as $field => $value) {
          $this->assertEquals($value, $apiData[$field]);
        }
    }

    // TODO: populateWithData, populateWithArrayData, inheritedApiFields
}
