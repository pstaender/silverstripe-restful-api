<?php


class ApiDataObjectTest extends SapphireTest {

    // Define the fixture file to use for this test class
    protected static $fixture_file = 'ApiDataObjectTest.yml';

    /**
      * This is not a test method, more a check to ensure that we work
      * with the `correct` parameters
      */
    function testExpectedDefaultConfigValues() {
      ApiControllerTest::ensure_correct_config($this);
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
        $this->assertEquals($expected, ApiDataObject::real_field_name($input, new Member()));
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
          'class_name' => 'Member',
        ];
        $obj = $this->objFromFixture('Member', 'api');
        $apiData = $obj->forApi();
        foreach($expectedData as $field => $value) {
          $this->assertEquals($value, $apiData[$field]);
        }
    }

    function testPopulateWithArrayData() {
      $data = [
        "FirstName" => "Philipp",
        "Email" => "philipp@home.com",
        "ID" => 123,
        "NumVisit" => 333333,
      ];
      $member = $this->objFromFixture('Member', 'api');
      $member->populateWithArrayData($data, null, [ "NumVisit" ]);
      foreach($data as $field => $exptectedValue) {
        if ($field === 'NumVisit')
          $this->assertFalse($exptectedValue === $member->{$field});
        else
          $this->assertEquals($exptectedValue, $member->{$field});
      }
      // we also ensure that we can work with underscore field names
      $underscoreData = [
        "first_name" => "Philipp",
        "email" => "philipp@home.com",
        "id" => 123,
        "num_visit" => 333333,
      ];
      $member = $this->objFromFixture('Member', 'api');
      $member->populateWithArrayData($underscoreData, null, [ "NumVisit" ]);
      foreach($data as $field => $exptectedValue) {
        $field = ApiDataObject::real_field_name($field, $member);
        $this->assertEquals($exptectedValue, $member->{$field});
      }
    }

    // TODO: populateWithData, populateWithArrayData, inheritedApiFields
}
