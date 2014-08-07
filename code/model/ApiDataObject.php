<?php

class ApiDataObject extends DataExtension {


  static function to_underscore($input) {
    preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
    $ret = $matches[0];
    foreach ($ret as &$match) {
      $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
    }
    return implode('_', $ret);
  }

  /**
   * Array of inherited static desriptions of `api_fields`
   * Copied from @DataOject->inheritedDatabaseFields()
   * @return array fields
   */
  private function inheritedApiFields() {
    $fields     = array();
    $currentObj = $this->owner->class;

    while($currentObj != 'DataObject') {
      $apiFields  = $this->owner->stat('api_fields');
      if ($apiFields === null) {
        $apiFields = $this->owner->stat('db');
        $fields['ID'] = 'ID'; // we have to add manually 'ID'
        foreach ($apiFields as $field => $type) {
          $fields[$field] = $field;
        }
      }
      else if (is_array($apiFields)) {
        $fields = array_merge($fields, $apiFields);
      }
      $currentObj = get_parent_class($currentObj);
    }

    return array_unique($fields);
  }
  /**
   * Matches the CamelCase field name of a underscore named field
   * e.g. `user_id` => `UserID`
   * @param  string $field      Fieldname in underscore
   * @param  object $ownerClass DataObject
   * @return string | null      Name of the original field in CamelCase
   */
  static function real_field_name($field, $ownerClass) {
    if (is_string($ownerClass)) {
      $ownerClass = singleton($ownerClass);
    }
    if (preg_match("/^[a-z\_0-9]+$/", $field)) {
      foreach($ownerClass->inheritedDatabaseFields() as $fieldName => $type) {
        if (strtolower($fieldName)===str_replace('_', '', $field))
          return $fieldName;
      }
    } else {
      return $field;
    }
  }


  function forApi($options = null) {
    $jsonDateFormat = $this->owner->config()->get('jsonDateFormat');//ApiController::config()->jsonDateFormat;
    $underscoreFields = $this->owner->config()->get('underscoreFields');//ApiController::config()->underscoreFields;
    $castDataObjectFields = $this->owner->config()->get('castDataObjectFields');
    $databaseFields = DataObject::database_fields($this->owner->class);//$this->owner->inheritedDatabaseFields();
    $apiFields = (isset($options['fields'])) ? $options['fields'] : $this->inheritedApiFields();
    if (!is_array($apiFields)) {
      // use inherited database fields
      $apiFields = $databaseFields;
      $apiFields['ID'] = 'PrimaryKey';
    }
    // check if assoz. array
    if (array_keys($apiFields) !== range(0, count($apiFields) - 1)) {
      // if assoziative array (default), leave it as it is
      $fields = $apiFields;
    } else {
      // convert to assoz. array
      $fields = [];
      foreach($apiFields as $field) {
        $fields[$field] = $field;
      }
    }

    $data = [];
    // $record = $this->owner->toMap();
    foreach($fields as $key => $k) {
      // $key, original field, $k target field to match
      $type = (isset($databaseFields[$key])) ? $databaseFields[$key] : null;
      if ($this->owner->hasMethod($key)) {
        $value = $this->owner->{$key}();
      } else {
        $value = $this->owner->{$key};
      }
      if ($underscoreFields) {
        $k = self::to_underscore($k);
      }
      if (!$castDataObjectFields) {
        $data[$k] = $value;
        continue;
      }
      // Types: http://doc.silverstripe.com/framework/en/topics/data-types
      // castDataObjectFields
      $fieldType = strtolower($type); // lowercase is less ambigious to compare
      if ($value === null) {
        // keep as null
        $data[$k] = null;
      } else if ($fieldType === 'int' || $fieldType === 'foreignkey' || $fieldType === 'primarykey') {
        $data[$k] = (int) $value;
      } else if ($fieldType === 'float' || $fieldType === 'decimal' || $fieldType === 'currency' || $fieldType === 'percentage') {
        $data[$k] = (float) $value;
      } else if ($fieldType === 'boolean') {
        if (is_string($value)) {
          $value = ($value === "true") ? true : false;
        } else {
          $data[$k] = (boolean) $value;
        }

      } else if ($fieldType === 'ss_datetime' || $fieldType === 'date' || $fieldType === 'time') {
        $data[$k] = $this->owner->dbObject($key)->Format($jsonDateFormat);
      } else if (is_a($value, 'DataObject')) {
        // if we have a dataobject, call recursive ->forApi()
        $data[$k] = $value->forApi();
      }else {
        $data[$k] = $value;
      }
    }
    return $data;
  }

  function populateWithData($data, $only = null) {
    // TODO: convert to array if DataObject
    return $this->populateWithArrayData($data, $only);
  }

  function populateWithArrayData(array $data, $only = null) {
    $hasOnlyFilter = is_array($only);
    foreach($data as $key => $value) {
      $field = ApiDataObject::real_field_name($key, $this->owner);
      if (($hasOnlyFilter) && (!in_array($field, $only, true))) {
        continue;
      } else {
        $this->owner->{$field} = $value;
      }
    }
  }

}
