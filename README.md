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
AuthSession:
  validInMinutesFromNow: 10080
  adminAccessToken: null
  requiredGroup: null
  requiredPermission: null
DataObject:
  jsonDateFormat: 'D M d Y H:i:s O'
  underscoreFields: true
  castDataObjectFields: true
  extensions:
    - ApiDataObject
```

To avoid unnessary authentication **during development** you can define a `adminAccessToken`.

## How to use

### Authentication

#### Login

`POST:http://localhost/auth/session` with body:

```json
  { "email": "you@email.com", "password": "yourpassword" }
```

Returns s.th. like:

`{"data":{"accesstoken":"8d69f3f127a9ddc4f73d75c5803c846696bb10ff","valid_until":"Mon Sep 28 2015 03:01:06 +0200","valid_until_timestamp":1443402066,"user":{"id":2,"first_name":"Firstname","surname":"Surname","email":"you@email.com","password":"$2y$10$30a34abddf59ff219c035uWmfqMW1y39zOeY3Qi0e16emeFxRIiUm","remember_login_token":null,"num_visit":0,"last_visited":null,"auto_login_hash":null,"auto_login_expired":null,"password_encryption":"blowfish","salt":"10$30a34abddf59af219c0353","password_expiry":null,"locked_out_until":null,"locale":"en_US","failed_login_count":0,"date_format":"MMM d, y","time_format":"h:mm:ss a"},"uri":"http:\/\/localhost\/auth\/session\/"}}` (200)

Now you can use the accesstoken to perform actions.

#### Session info

`GET:http://localhost/auth/session` with header `X-Accesstoken: 8d69f3f127a9ddc4f73d75c5803c846696bb10ff` (you always have to attach the Accesstoken this way to get authenticated!) returns:

`{"data":{"accesstoken":"8d69f3f127a9ddc4f73d75c5803c846696bb10ff","valid_until":"Mon Sep 28 2015 03:28:06 +0200","valid_until_timestamp":1443403686,"user":{"id":2,"first_name":"Philipp","surname":"St\u00e4nder","email":"philipp.staender@gmail.com","password":"$2y$10$30a34abddf59ff219c035uWmfqMW1y39zOtY3Qi0e16emeFxRIiUm","remember_login_token":null,"num_visit":0,"last_visited":null,"auto_login_hash":null,"auto_login_expired":null,"password_encryption":"blowfish","salt":"10$30a34abddf59ff219c0353","password_expiry":null,"locked_out_until":null,"locale":"en_US","failed_login_count":0,"date_format":"MMM d, y","time_format":"h:mm:ss a"},"uri":"http:\/\/localhost\/auth\/session\/"}}` (200)

### Logout (delete Session)

`DELETE:http://localhost/auth/session/8d69f3f127a9ddc4f73d75c5803c846696bb10ff` with header `X-Accesstoken: 8d69f3f127a9ddc4f73d75c5803c846696bb10ff` return:

`{"message":"resource deleted successfully"}` (202)

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

### Licence

MIT License
