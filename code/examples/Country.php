<?php

/*
 * Example model for restful API representation
 */

class Country extends DataObject {

  static $db = array(
    "Name"      => "Varchar",
    "Code"      => "Varchar(3)",
    "Lon"       => "Float",
    "Lat"       => "Float",
    "Color"     => "Varchar(8)", // hexcolor, e.g. #333333
    "Note"      => "Text",
    "Status"    => "Int",
  );

  static $has_one = array(
    "Icon"      => "Image",
  );

  function forApi() {
    $data = parent::forApi();
    // you can change $data for the public API here
    return $data;
  }

}

/*

  Example controller(s) for Country

  Add to routes.yml:

  Director:
  rules:
    'country/$ID//$Action': 'Country_Controller'
    'countries': 'Countries_Controller'
*/

class Country_Controller extends ApiController {

  private static $api_parameters = [
    /* Define existence (mandatory/optional) and casting (by regex) parameters for each request method */
    "GET:index" => [
      '$ID!' => "/^\d$/",
      /*
        'ID!' => … `!` means mandatory
        '$ID' => … `$` means url paramter, e.g. /country/$ID//$Action
        '?ID' => … `?` means POST or GET parameter
        'ID'  => …     is an JSON attribute (recommend way)
      */
    ],
    "DELETE:index" => [
      '$ID!' => "/^\d$/",
    ],
    "POST:index" => [
      "Name!" => "/^.+$/",
      "Code!" => "/^[A-Z]+$/",
      "Lon!"  => "/^\d+(\.\d+)*$/",
      "Lat!"  => "/^\d+(\.\d+)*$/",
      "Color" => "/^#[a-e]{6}$/",
      "Note"  => "*",
    ],
    "PUT:index" => [
      "Name!" => "/^.+$/",
      "Code!" => "/^[A-Z]+$/",
      "Lon!"  => "/^\d+(\.\d+)*$/",
      "Lat!"  => "/^\d+(\.\d+)*$/",
      "Color" => "/^#[a-e]{6}$/",
      "Note"  => "*",
    ],
  ];

  /* similar to `allowed_actions`, but connected to request method */
  private static $api_allowed_actions = [
    "GET:index"    => true, //everyone can read
    "POST:index"   => "admin",
    "PUT:index"    => "admin",
    "DELETE:index" => "admin",
  ];

  /* Instead of using `indexGET()`/`indexPOST()`/… you could
     define `index()` and seperate inside the function
     with `$this->request->isGET()` … the default way in SilverStripe
   */
  function indexGET() {
    $id = $this->request->param("ID");
    $country = Country::get()->byID($id);
    return ($country) ? $this->sendData($country) : $this->sendNotFound();
  }

  function indexPOST() {
    $country = new Country();
    $data = $this->requestDataAsArray('Country');
    if (Country::get()->filter([ "Code" => $data['Code']])->First()) {
      return $this->sendError("Country `{$data['Code']}` exists already in db");
    }
    $country->populateWithData($data, ["Name", "Lon", "Lat", "Code", "Color", "Note"]);
    $country->write();
    return ($country) ? $this->sendData($country) : $this->sendNotFound();
  }

  function indexPUT() {
    $id = $this->request->param("ID");
    $country = Country::get()->byID($id);
    $data = $this->requestDataAsArray('Country');
    if ($country) {
      $country->populateWithData($data, ["Name", "Lon", "Lat", "Code", "Color", "Note"]);
      $country->write();
      return $this->sendData($country);
    } else {
      return $this->sendNotFound();
    }
  }

  function indexDELETE() {
    $id = $this->request->param("ID");
    $country = Country::get()->byID($id);
    if ($country) {
      $country->delete();
      return $this->sendSuccessfulDelete();
    } else {
      return $this->sendNotFound();
    }
  }

}

class Countries_Controller extends ApiController {

  private static $api_allowed_actions = [
    "GET:index" => "admin",
    "GET:europe" => "admin",
  ];

  /* Accessible via `countries/` */
  function index() {
    return $this->sendData(Country::get());
  }

  /* Accessible via `countries/europe` */
  function europe() {
    return $this->sendData(Country::get()->filter([])); // define some filters for european countries ;)
  }

}
