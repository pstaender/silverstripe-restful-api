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

To avoid unnecessary authentication **during development** you can define a `adminAccessToken`. The check of `requiredGroup` and `requiredPermission` is not implemented, yet.

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

## Interesting Controller helpers:

  * **getAccessTokenFromRequest**
    - Returns the accesstoken from the header
  * **getSessionFromRequest**
    - The session DataObject (if existing)
  * **sendData($data = null, $code = null)**
    - Send a DataList, DataObject or an Array; will be rendered as JSON by default
  * **sendJSON($data = null, $code = null)**
    - Same as sendData, except to force JSON
  * **sendError($errMsg, $errCode = 500)**
    - Use to send comprehensible error messages for the API user
  * **sendNotFound($code = 404, $msg = 'resource not found')**
    - Helper to send a default "Resource not found" error message
  * **sendSuccessfulDelete($code = 202, $msg = 'resource deleted successfully')**
    - Helper to send a default "Resource deleted successfully" message after a `DELETE`
  * **sendSuccessfulPut($code = 202, $msg = 'resource updated successfully')**
    - Helper to send a default "Resource updated successfully" message after a `PUT`
  * **sendSuccessfulPost($uriOrData = null, $code = 201, $msg = 'resource created succesfully')**
    - Helper to redirect to new resource (if $uriOrData is a string) or send a default "Resource created successfully" message after a `POST`


### CRUD

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

### Increase performance

By deactivating blocking sessions, you can increase the response time.

Beware that none across request session will work anymore because persistent session storage is deactivated in that scenario (not needed in pure restful services anyway).

To activate this feature, add to your `_config.php`:

```php
  define('RESTFUL_API_MODULE_NON_BLOCKING_SESSION', true);
```

### Licence

MIT License
