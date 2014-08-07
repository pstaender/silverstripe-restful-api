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

### CRUD

### Licence

MIT License
