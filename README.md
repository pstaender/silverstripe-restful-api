# Restul API for SilverStripe

## Configuration

The following attributes can be configured:

```yml
---
Name: RestfulApiConfig
---
ApiController:
  underscoreFields: true
  useAccesstokenAuth: true
  accessTokenPropertyName: 'X-Accesstoken'
AuthSession:
  validInMinutesFromNow: 10080
  adminAccessToken: null
  requiredGroup: null
  requiredPermission: null
  urlSegment: 'auth'
DataObject:
  jsonDateFormat: 'D M d Y H:i:s O'
  underscoreFields: true
  castDataObjectFields: true
  extensions:
    - ApiDataObject
```

To avoid unnecessary authentication **during development** you can define a `adminAccessToken`.

## How to use

### Authentication

#### Login

`POST:http://localhost/auth/session` with (json) body:

```json
  {
    "email":"your@email.com",
    "password":"yourpassword"
  }
```

Returns (with StatusCode 200):

```json
  {
    "data":{
      "accesstoken":"8d69f3f127a9ddc4f73d75c5803c846696bb10ff",
      "valid_until":"Mon Sep 28 2015 03:01:06 +0200",
      "valid_until_timestamp":1443402066,
      "user":{
        "id":2,
        …
      },
      "uri":"http://localhost/auth/session/"
    }
  }
```

Now you can use the accesstoken to perform actions.

#### Session info

`GET:http://localhost/auth/session` with header `X-Accesstoken: 8d69f3f127a9ddc4f73d75c5803c846696bb10ff` (you always have to attach the Accesstoken this way to get authenticated!) returns (with StatusCode 200):

```json
  {
    "data":{
      "accesstoken":"8d69f3f127a9ddc4f73d75c5803c846696bb10ff",
      "valid_until":"Mon Sep 28 2015 03:28:06 +0200",
      "valid_until_timestamp":1443403686,
      "user":{
        "id":2,
        …
      },
      "uri":"http://localhost/auth/session/"
    }
  }
```

### Logout (delete Session)

`DELETE:http://localhost/auth/session/8d69f3f127a9ddc4f73d75c5803c846696bb10ff` with header `X-Accesstoken: 8d69f3f127a9ddc4f73d75c5803c846696bb10ff` returns (with StatusCode 202):

```json
  {
    "message":"resource deleted successfully"
  }
```

### Write your API Controller

You can also (check out this more detailed example)[https://github.com/pstaender/silverstripe-restful-api/blob/master/code/examples/Country.php].

How to define your own API Controller including some methods (we assume that you've added s.th. like `"myapicontroller//$Action/$ID": "MyApiController"` to your `routes.yml`):

```php

  class MyApiController extends APIController {

    private static $api_parameters = [
      "GET:client" => [
        '$ID' => "int",
      ],
      "POST:client" => [
        'email' => "/^[^@]+@[^@]+$/",
      ],
    ];

    private static $api_allowed_actions = [
      "GET:client"    => true,                // everyone can read here
      "DELETE:client" => 'ADMIN',             // only admins can delete
      "POST:client"   => '->isBasicApiUser',  // method `isBasicApiUser` checks permission
      ]

    /**
     * Will respond with a JSON object and 200 if found
     * with a 404 and a JSON msg object otherwise
     */
    function clientGET() {
      return $this->sendData(
        Client::get()->byID($this->request->param("ID"))
      );
    }

    function clientPOST() {
      $client = Client::create();
      $data = $this->requestDataAsArray('Client');
      $populateOnlyTheseseFields = [ "Email", "FirstName", "Surname" ];
      $country->populateWithData($data, $populateOnlyTheseseFields);
      $country->write();
      return $this->sendSuccesfulPost($country->URL());
    }

    function clientDELETE() {
      $client = Client::get()->byID($this->request->param("ID");
      if (!$client)
        return $this->sendNotFound();
      $client->delete();
      return $this->sendSuccessfulDelete();
    }

    protected function isBasicApiUser() {
      return Permission::check('BASIC_API') || Permission::check('EXTERNAL_API');
    }

  }

## Interesting Controller helpers:

  * **getAccessTokenFromRequest**
    - Returns the accesstoken from the header
  * **getSessionFromRequest**
    - The session DataObject (if existing)
  * **sendData($data = null, $code = null)**
    - Send a DataList, DataObject or an Array; will be rendered as JSON by default
  * **sendJSON($data = null, $code = null)**
    - Same as sendData, except to force JSON
  * **sendError($errMsg, $code = 500)**
    - Use to send comprehensible error messages for the API user
  * **sendNotFound($msg = 'resource not found', $code = 404)**
    - Helper to send a default "Resource not found" error message
  * **sendSuccessfulDelete($msg = 'resource deleted successfully', $code = 202)**
    - Helper to send a default "Resource deleted successfully" message after a `DELETE`
  * **sendSuccessfulPut($msg = 'resource updated successfully', $code = 202)**
    - Helper to send a default "Resource updated successfully" message after a `PUT`
  * **sendSuccessfulPost($uriOrData = null, $msg = 'resource created succesfully', $code = 201)**
    - Helper to redirect to new resource (if $uriOrData is a string) or send a default "Resource created successfully" message after a `POST`

To keep it simple: the arguments order of `$msg` and `$code` is arbitrary.

### Tests

```sh
  $ sake dev/tests/module/silverstripe-restful-api flush=1
```

or

```sh
  $ sake /dev/tests/AuthControllerTest
```

to run specific tests.

Ensure that you have defined [$_FILE_TO_URL_MAPPING](http://doc.silverstripe.org/framework/en/topics/commandline) in `_ss_environment.php` to run controller tests correctly (otherwise redirects will throw an user error for instance).

### Better performance

By deactivating blocking sessions, [you can decrease the response time](http://www.silverstripe.org/improving-silverstripe-performance/).

Beware that none across request session will work anymore on your SilverStripe project because persistent session storage is deactivated in that scenario (not needed in pure restful services anyway).

To activate this feature, add to your `_config.php`:

```php
  define('RESTFUL_API_MODULE_NON_BLOCKING_SESSION', true);
```

### Licence

MIT License
