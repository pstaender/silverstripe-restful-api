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

  static function to_camelcase($input, $checkID = true) {
    $s = "";
    for($i=0; $i < strlen($input); $i++) {
      if ($i===0) {
        $input[0] = strtoupper($input[0]);
      }
      if ($input[$i]==='_') {
        continue;
      }
      if ((isset($input[$i+1])) && ($input[$i+1] === '_') && (isset($input[$i+2]))) {
        $input[$i+2] = strtoupper($input[$i+2]);
      }
      // else
      $s .= $input[$i];
    }
    if ($checkID) {
      $s = preg_replace("/([a-z]{1})Id$/","$1ID", $s);
    }
    return $s;
  }

  /**
   * Convert recursive a mixed array (including DataLists and DataObject) to a pure associative array
   * @param   mixed   $object     Can be an array or DataObject or DataList
   * @param   int     $level      Prevent endless nested converting (should not occure anyway)
   * @param   array   &$data      Return value (as reference)
   */
  static function to_nested_array($object, $level = 0, &$data) {
    if ($level > 5) return null;
    $level++;
    $data = null;
    if ((is_a($object, 'DataList')) || (is_a($object, 'ArrayList'))) {
      $data = array();
      foreach($object as $item) {
        self::to_nested_array($item, $level, $data[]);
      }
    } else if (is_a($object, 'DataObject')) {
      if ($object->hasMethod('forApi')) {
        // if we have an APIDataObject (best practice)
        $data = $object->forApi();
      } else if ($object->hasMethod('toMap')) {
        // if we have an DataObject
        $data = $object->toMap();
      }
    } else if (is_array($object)) {
      $data = array();
      foreach($object as $key => $value) {
        self::to_nested_array($value, $level, $data[$key]);
      }
    } else {
      // primitive data type
      $data = $object;
    }
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
    if (preg_match("/\_id$/", $field)) {
      return $field = ApiDataObject::to_camelcase($field, $checkID = true);
    } else if (preg_match("/^[a-z\_0-9]+$/", $field)) {
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
    $resolveHasOneRelations = (isset($options['resolveHasOneRelations'])) ? $options['resolveHasOneRelations'] : (($this->owner->config()->get('resolveHasOneRelations') !== null) ? $this->owner->config()->get('resolveHasOneRelations') : true );
    $resolveHasManyRelations = (isset($options['resolveHasManyRelations'])) ? $options['resolveHasManyRelations'] : (($this->owner->config()->get('resolveHasManyRelations') !== null) ? $this->owner->config()->get('resolveHasManyRelations') : true );
    $databaseFields = $this->owner->inheritedDatabaseFields();//DataObject::database_fields($this->owner->class);

    $apiFields = (isset($options['fields'])) ? $options['fields'] : $this->inheritedApiFields();
    if (!is_array($apiFields)) {
      // use inherited database fields
      $apiFields = $databaseFields;
      $apiFields['ID'] = 'PrimaryKey';
    }

    $data = array(); // all final data will be stored in this array

    if ($resolveHasOneRelations) {
      $hasOne = Config::inst()->get($this->owner->class, 'has_one', Config::INHERITED);
      $e = array();
      if ($hasOne) {
        foreach(array_keys($hasOne) as $relationName) {
          if ($this->owner->hasMethod($relationName)) {

            $fieldName = $relationName;
            if ($underscoreFields) {
              $fieldName = self::to_underscore($fieldName);
            }
            $o = $options;
            $o['resolveHasOneRelations'] = false;
            $relation = $this->owner->{$relationName}();
            // we only add the record if it exists in the db
            if ($relation->isInDB())
              $data[$fieldName] = $relation->forApi($o);
          }
        }
      }
    }

    if ($resolveHasManyRelations) {
      $hasOne = Config::inst()->get($this->owner->class, 'has_many', Config::INHERITED);
      $e = array();
      if ($hasOne) {
        foreach(array_keys($hasOne) as $relationName) {
          if ($this->owner->hasMethod($relationName)) {

            $fieldName = $relationName;
            if ($underscoreFields) {
              $fieldName = self::to_underscore($fieldName);
            }
            $o = $options;
            $o['resolveHasManyRelations'] = false;
            $relations = $this->owner->{$relationName}();
            // we only add the record if it exists in the db
            foreach($relations as $relation) {
              if ($relation->isInDB())
                $data[$fieldName][] = $relation->forApi($o);
            }
          }
        }
      }
    }

    // check if assoz. array
    if (array_keys($apiFields) !== range(0, count($apiFields) - 1)) {
      // if assoziative array (default), leave it as it is
      $fields = $apiFields;
    } else {
      // convert to assoz. array
      $fields = array();
      foreach($apiFields as $field) {
        $fields[$field] = $field;
      }
    }

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
      } else if ($fieldType === 'int' || $fieldType === 'primarykey') {
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
