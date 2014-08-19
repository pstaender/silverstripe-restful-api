<?php

class ApiDataObject extends DataExtension {


  /**
   * Transforms a (CamelCase) string to a underscore string
   *
   * @param   String  $input  String to transform
   * @return  String          String as underscore string
   */
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
  function inheritedApiFields() {
    $fields     = array();
    $currentObj = $this->owner->class;

    while($currentObj != 'DataObject') {
      $apiFields  = singleton($currentObj)->stat('api_fields');
      if ($apiFields === null) {
        $apiFields = $this->owner->stat('db');
        $fields['ID'] = 'ID'; // we have to add manually 'ID'
        $fields['ClassName'] = 'ClassName';
        $fields['Created'] = 'Created';
        $fields['LastEdited'] = 'LastEdited';
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
      $ownerClass = Injector::inst()->create($ownerClass);//singleton($ownerClass);
    }
    $fieldnameWithoutUnderscore = str_replace('_', '', $field);
    if (preg_match("/^[a-z\_0-9]+$/", $field)) {
      foreach($ownerClass->inheritedApiFields() as $fieldName => $type) {
        if (strtolower($fieldName)===$fieldnameWithoutUnderscore)
          return $fieldName;
      }
    } else {
      return $field;
    }
  }


  /**
   * This method is used by `ApiController` to get an array of data for the default output.
   * Override this method on your model to apply custom behavior and fields.
   * By default all fields which are defined on `api_fields` will be used (all field if not defined).
   *
   * @param   array   $options    optional assoz. array, fields => array of fields
   * @return  array   $data       assoz. array containing data for the output
   */
  function forApi($options = null) {
    $jsonDateFormat = $this->owner->config()->get('jsonDateFormat');
    $underscoreFields = $this->owner->config()->get('underscoreFields');
    $castDataObjectFields = $this->owner->config()->get('castDataObjectFields');
    $databaseFields = $this->owner->inheritedDatabaseFields();//DataObject::database_fields($this->owner->class);
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

    // $record = $this->owner->toMap();

    $data = [];
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

      $data[$k] = $value;

      if (!$castDataObjectFields) {
        continue;
      }
      // Types: http://doc.silverstripe.com/framework/en/topics/data-types
      // castDataObjectFields
      $fieldType = strtolower($type); // lowercase is easier to compare
      if ($value === null) {
        // keep as null
        $data[$k] = null;
      } else if ($fieldType === 'int' || $fieldType === 'foreignkey' || $fieldType === 'primarykey') {
        $data[$k] = (int) $value;
      } else if ($fieldType === 'float' || $fieldType === 'decimal' || $fieldType === 'currency' || $fieldType === 'percentage') {
        $data[$k] = (float) $value;
      } else if ($fieldType === 'boolean') {
        if (is_string($value)) {
          $data[$k] = ($value === "true") ? true : false;
        } else {
          $data[$k] = (boolean) $value;
        }
      } else if ($fieldType === 'ss_datetime' || $fieldType === 'date' || $fieldType === 'time') {
        $data[$k] = $this->owner->dbObject($key)->Format($jsonDateFormat);
      } else if (is_a($value, 'DataObject')) {
        // if we have a dataobject, call recursive ->forApi()
        $data[$k] = $value->forApi($options);
      }
    }
    return $data;
  }

  /**
   * This method will populate a DataObject with data from a given array (recommend) or DataObject
   * Use this method to apply data from request(s) (body or other parameters)
   *
   * @param   array|DataObject    Data to populate
   * @only    array               an array with fields to populate exclusive, e.g. [ "Name", "Email" ]
   */
  function populateWithData($data, $only = null, $exclude = null) {
    if (is_a($data, 'DataObject')) {
      $data = $data->toMap();
    }
    return $this->populateWithArrayData($data, $only, $exclude);
  }

  function populateWithArrayData(array $data, $only = null, $exclude = null) {
    $hasOnlyFilter = is_array($only);
    $hasExcludeFilter = is_array($exclude);
    if ($hasExcludeFilter) {
      foreach($exclude as $field) {
        $altField = ApiDataObject::real_field_name($field, $this->owner);
        if (isset($data[$field])) {
          unset($data[$field]);
        } else if (isset($data[$altField])) {
          unset($data[$altField]);
        }
      }
    }
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
