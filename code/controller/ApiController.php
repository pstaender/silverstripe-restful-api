<?php

class ApiController extends Controller {

  protected $statusCode = null;
  protected $code = null;
  protected $data = null;
  protected $error = null;
  protected $message = null;
  protected $help = null;
  protected $format = 'json';
  protected $parameters = null;
  protected $restfulSession = null;

  function init() {
    parent::init();
    // We extend the request object with the properties `data` and `session`
    $this->request->data = $this->requestBodyAsDataObject();
    if ($this->config()->get('useAccesstokenAuth')) {
      $this->restfulSession = $this->getSessionFromRequest();
      // echo Debug::show($this->restfulSession);
      $this->setSessionByApiSession();
    }
  }

  function getAccessTokenFromRequest() {
    $accesstoken = $this->request->getHeader($this->config()->get('accessTokenPropertyName'));
    if (!$accesstoken)
      $accesstoken = (string) (isset($data['accesstoken'])) ? $data['accesstoken'] : ((isset($_REQUEST['accesstoken'])) ? $_REQUEST['accesstoken'] : null );
    return $accesstoken;
  }

  function getSessionFromRequest() {
    $adminAccessToken = Config::inst()->get('AuthSession', 'adminAccessToken');
    $accessToken = $this->getAccessTokenFromRequest();
    if (($adminAccessToken) && ($adminAccessToken === $accessToken)) {
      return AuthSession::find_admin_session_by_accesstoken($adminAccessToken);
    }
    return AuthSession::find_by_accesstoken($accessToken);
  }

  /**
   * Use the api accesstoken auth process to define a user session in SilverStripe
   * By default SilverStripe using a form + session stored auth process,
   * but with stateless restful we actually don't need request outlasting sessions
   * (only for SilverStripe specifics mechanims, like Permission::check() etc.)
   * @return int                  ID of the "logged in" member
   */
  protected function setSessionByApiSession() {
    $id = (($this->restfulSession)&&($this->restfulSession->MemberID)) ? $this->restfulSession->MemberID : 0;//(($this->restfulSession)&&($this->restfulSession->Member())) ? $this->restfulSession->Member()->ID : null;
    if ($id)
      Session::set("loggedInAs", $id);
    else
      Session::clear('loggedInAs');
    return $id;
  }

  protected function requestBodyAsArray() {
    return json_decode($this->request->getBody(), true);
  }

  protected function requestBodyAsDataObject() {
    $data = $this->requestBodyAsArray();
    return (is_array($data)) ? new ArrayData($data) : null;
  }

  protected function requestDataAsArray($className) {
    $data = $this->requestBodyAsArray();
    $d = array();
    foreach($data as $key => $value) {
      $d[ApiDataObject::real_field_name($key, $className)] = $value;
    }
    return $d;
  }

  protected function requestDataAsDataObject($className) {
    $data = $this->requestDataAsArray();
    return (is_array($data)) ? new ArrayData($data) : null;
  }

  function handleAction($request, $action) {
    $method = $request->httpMethod(); // POST|GET|PUT…
    $allParams = $request->allParams(); // from router
    $extension = $request->getExtension(); // .html
    $requestVars = $request->requestVars(); // POST + GET
    $getVars = $request->getVars();
    $postVars = $request->postVars();
    $body = $request->getBody();
    $headers = $request->getHeaders();
    $data = null;
    $contentType = (isset($headers['Content-Type'])) ? $headers['Content-Type'] : null;
    // parse json
    if (preg_match('/json$/',$contentType)) {
      $data = json_decode($body, true);
      if (!$data) {
        $msg = null;
        switch(json_last_error()) {
          case JSON_ERROR_DEPTH:
           $msg = 'reached max. stack depth';
          break;
          case JSON_ERROR_CTRL_CHAR:
           $msg = 'unexpected control character';
          break;
          case JSON_ERROR_SYNTAX:
           $msg = 'syntax error in JSON';
          // break;
          // case JSON_ERROR_NONE:
          //  $msg = null;
          break;
        }
        if ($msg) {
          return $this->sendError(400, "JSON Parser Error ($msg)");
        }
      }
    }
    $underscoreFields = $this->config()->get('underscoreFields');

    $errorType = null;
    $errorMessage = "";

    $parameters = [];
    $apiParameters = $this->stat('api_parameters');

    $alternateAction = $action.$method; // actionMETHOD, e.g. indexPOST()
    if ($this->hasAction($alternateAction)) {
      $actualAction = $alternateAction; // prefer this naming
    } else {
      $actualAction = $action;
    }

    if(!$this->hasAction($actualAction)) {
      return $this->sendError("Action `$actualAction` isn't available on API class `$this->class`", 404);
    }
    if(!$this->checkAccessAction($action) || in_array(strtolower($action), array('run', 'init'))) {
      return $this->sendError("No permission to access `$action` ($actualAction) on `$this->class`", 403);
    }

    $params = array();

    // check expected parameters against existing
    if (is_array($apiParameters)) foreach ($apiParameters as $methodAndAction => $parameters) {
      if (preg_match("/^".$method.":".$action."$/", $methodAndAction)) {
        foreach ($parameters as $field => $type) {
          $value = null;
          // strip ?…! from field
          $isRequired = ($field[strlen($field)-1] === '!') ? true : false;
          $isQueryParameter = ($field[0] === '?') ? true : false;
          $isURLParameter = ($field[0] === '$') ? true : false;
          $field = preg_replace('/^(\?|\$)*(.*?)(\!)*$/', "$2", $field);
          $camelCaseFieldName = null;
          // if (preg_match("/^[a-z\_]$/",$field)) {
          //   return user_error("Field identifier `$field` in \$api_fields has to be CamelCase to work correctly.");
          // }
          // if (($underscoreFields) && (preg_match("/^[A-Z]$/",$field))) {
          //   $camelCaseFieldName = $field;
          //   $field = ApiDataObject::to_underscore($field);
          // }

          if ($isQueryParameter) {
            if (isset($requestVars[$field])) {
              $value = $requestVars[$field];
            } else if (($field !== $camelCaseFieldName) && (isset($requestVars[$camelCaseFieldName]))) {
              $value = $requestVars[$camelCaseFieldName];
            }
          } else if ($isURLParameter) {
            // routing uses camelcase as default, this is why we do a check here again
            if (isset($allParams[$field])) {
              $value = $allParams[$field];
            } else if (($field !== $camelCaseFieldName) && (isset($allParams[$camelCaseFieldName]))) {
              $value = $allParams[$camelCaseFieldName];
            }
          } else {
            if (isset($data[$field])) {
              $value = $data[$field];
            } else if (($field !== $camelCaseFieldName) && (isset($data[$camelCaseFieldName]))) {
              $value = $data[$camelCaseFieldName];
            }
          }
          $parameterType = "JSON property";
          if ($isQueryParameter) {
            $parameterType = "POST|GET parameter";
          } else if ($isURLParameter) {
            $parameterType = "URL parameter";
          }
          if (($isRequired) && ($value == null)) {
            $errorMessage .= "The $parameterType `$field` is required";
            return $this->sendError($errorMessage, 422);
          }
          $valueType = (strtolower($type));
          if ($value === null) {
            // var_dump($data);
            // null is always an accepted value if field is not required
            // so if we have null, we skip the type check
          } else if (($type[0]==='/')&&($type[strlen($type)-1]==='/')) {
            // regular pregmatch
            if (!preg_match($type, $value)) {
              return $this->sendError("The $parameterType `$field` has to match the following pattern: `$type`", 422);
            }
          } else if (($valueType==='int')||($valueType==='integer')) {
            // integer
            if (!is_int($value))
              return $this->sendError("The $parameterType `$field` has to be an integer", 422);
          } else if (($valueType==='float')||($valueType==='number')) {
            // float
            if ((!is_int($value)) && (!is_float($value)))
              return $this->sendError("The $parameterType `$field` has to be a float", 422);
          } else if ($valueType==='boolean') {
            if (!is_bool($value))
              return $this->sendError("The $parameterType `$field` has to be a boolean", 422);
          }
          $params[$field] = $value;
        }
      }
    }
    $this->parameters = $params;
    return parent::handleAction($request, $actualAction);
  }

  /**
   * Checks method and action for request
   * RequestHandler.php -> handleRequest()
   * @param  string $action
   * @param  string $method
   * @return boolean
   */
  public function checkAccessAction($action) {
    $method = $this->request->httpMethod();
    $apiActions = Config::inst()->get(get_class($this), 'api_allowed_actions');
    $isAllowed = false;
    if ($apiActions === null) {
      // all actions + methods are allowed, use default check
      return parent::checkAccessAction($action);
    } else if ($apiActions === true) {
      return true;
    }
    if (is_array($apiActions)) {
      foreach($apiActions as $apiAction => $permission) {
        preg_match("/^(.+?):(.+)$/", $apiAction, $matches);
        if ((!isset($matches[1]))||(!isset($matches[2]))) {
          return user_error("Ensure that `api_allowed_actions` fulfills the following pattern: `\$method:\$action` => `\$permission`");
        }
        $allowedMethod = strtoupper($matches[1]);
        $allowedAction = strtolower($matches[2]);
        if ((($allowedMethod === $method)||($allowedMethod === "*")) && ($allowedAction === $action)) {
          if ($permission === true) {
            // wildcard
            $isAllowed = true;
          } else if ($permission === '->') {
            $isAllowed = true;
          } else if (substr($permission,0,2) === '->') {
            // use method
            $permissionMethod = substr($permission,2);
            if (!$this->hasMethod($permissionMethod)) {
              return user_error("Permission method `$permissionMethod` doesn't exists on `$this->class`");
            } else {
              $isAllowed = $this->{$permissionMethod}();
            }
          } else {
            $isAllowed = Permission::check($permission);
          }
        }
      }
    }
    return $isAllowed;
  }

  protected function prepareApiData($data = null) {
    $api = [];
    if ($data) {
      $this->dataRecord = $data;
    }
    if ($this->dataRecord) {
      if (is_a($this->dataRecord, 'DataList')) {
        $api["data"] = [];
        foreach($this->dataRecord as $item) {
          if (is_callable([$item,'forApi'])) {
            $api["data"][] = $item->forApi();
          } else if (is_callable([$item,'toMap'])) {
            $api["data"][] = $item->toMap();
          } else {
            $api["data"][] = $item;
          }
        }
      } else if (is_a($this->dataRecord, 'DataObject')) {
        if ($this->dataRecord->hasMethod('forApi')) {
          // if we have an APIDataObject (best practice)
          $api["data"] = $this->dataRecord->forApi();
        } else if ($this->dataRecord->hasMethod('toMap')) {
          // if we have an DataObject
          $api["data"] = $this->dataRecord->toMap();
        }
      } else if (is_array($this->dataRecord)) {
        $api["data"] = $this->dataRecord;
      } else {
        $this->error = "There is no valid object to map for the api.";
        $this->code = 500;
      }
    }
    if ($this->code) {
      $api["code"] = $this->code;
    }
    if ($this->error) {
      $api["error"] = (String) $this->error;
    }
    if ($this->message) {
      $api["message"] = (String) $this->message;
    }
    if ($this->help) {
      $api["help"] = (String) $this->help;
    }
    return $api;
  }

  private function sortCodeAndMessage($code, $message) {
    if (is_int($message)) {
      $a = $code;
      $code = $message;
      $message = $a;
    } else if (is_string($code)) {
      $a = $message;
      $message = $code;
      $code = (is_int($a)) ? $a : null;
    }
    return array(
      "message" => $message,
      "code" => $code,
    );
  }

  function sendData($data = null, $code = null) {
    $apiData = $this->prepareApiData($data);
    if ($code) $this->code = $code;
    $this->response = new SS_HTTPResponse();
    $this->response->addHeader('Content-Type', 'application/'.$this->format);
    $this->response->setStatusCode(($this->statusCode) ? $this->statusCode : ((is_int($this->code)) ? $this->code : 200));
    return json_encode($apiData);
  }

  function sendJSON($data = null, $code = null) {
    return $this->sendData($data, $code);
  }

  function sendError($errMsg = 'unspecified error', $errCode = 500) {
    $args = $this->sortCodeAndMessage($errMsg, $errCode);
    $this->error = $args['message'];
    $this->code  = $args['code'];
    return $this->sendData();
  }

  function sendSuccessfulPut($code = 201, $msg = 'resource updated successfully') {
    $args = $this->sortCodeAndMessage($code, $msg);
    $this->statusCode = $args['code'];
    $this->message = $args['message'];
    return $this->sendData();
  }

  function sendSuccessfulDelete($code = 202, $msg = 'resource deleted successfully') {
    $args = $this->sortCodeAndMessage($code, $msg);
    $this->statusCode = $args['code'];
    $this->message = $args['message'];
    return $this->sendData();
  }

  function sendNotFound($code = 404, $msg = 'resource not found') {
    $args = $this->sortCodeAndMessage($code, $msg);
    $this->statusCode = $args['code'];
    $this->message = $args['message'];
    return $this->sendData();
  }

  function sendSuccessfulPost($uriOrData = null, $code = 201, $msg = 'resource created succesfully') {
    $args = $this->sortCodeAndMessage($code, $msg);
    $this->statusCode = $args['code'];
    $this->message = $args['message'];
    if (is_string($uriOrData)) {
      $this->statusCode = 303;
      return $this->redirect($uriOrData, $this->statusCode);
    } else {
      return $this->sendData($uriOrData);
    }
  }

  function isValidApiSession() {
    if ($this->restfulSession) {
      return $this->restfulSession->IsValid();
    }
    return false;
  }

  function sendInvalidApiSession() {
    $this->sendError('No valid api session detected', 403);
  }

}
