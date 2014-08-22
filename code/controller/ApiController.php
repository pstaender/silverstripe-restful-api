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
    if ($id) {
      Session::set("loggedInAs", $id);
    } else if ($this->config()->get('useAccesstokenExclusivelyAsAuthentication')){
      Session::clear('loggedInAs');
    } else {
      $id = Member::currentUserID();
    }
    return $id;
  }

  protected function requestBodyAsArray() {
    return json_decode($this->request->getBody(), true);
  }

  protected function requestBodyAsDataObject() {
    $data = $this->requestBodyAsArray();
    return (is_array($data)) ? new ArrayData($data) : null;
  }

  protected function requestDataAsArray($className = null) {
    $data = $this->requestBodyAsArray();
    $d = array();
    if (!$className) {
      $className = $this->stat('api_model');
      if (!$className) {
        user_error("You have to reference the controller to a Model class. Recommend way is to use Controller::\$api_model = '…'");
      }
    }

    if (is_array($data)) {
      foreach($data as $key => $value) {
        $d[ApiDataObject::real_field_name($key, $className)] = $value;
      }
    } else {
      $d = array();
    }
    return $d;
  }

  protected function requestDataAsDataObject($className = null) {
    $data = $this->requestDataAsArray($className);
    return (is_array($data)) ? new ArrayData($data) : null;
  }

  // public function handleRequest(SS_HTTPRequest $request, DataModel $model) {
  //   exit("!");
  // }

  protected function handleAction($request, $action) {
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

    $parameters = array();
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
            return $this->sendParameterError($errorMessage);
          }
          $valueType = (strtolower($type));
          if ($value === null) {
            // null is always an accepted value if field is not required
            // so if we have null, we skip the type check
          } else if (($type[0]==='/')&&($type[strlen($type)-1]==='/')) {
            // regular pregmatch
            if (!preg_match($type, $value)) {
              return $this->sendParameterError("The $parameterType `$field` has to match the following pattern: `$type`");
            }
          } else if (($valueType==='int')||($valueType==='integer')) {
            // integer
            if (!preg_match("/^[\+\-]*\d+$/", $value)) {
              return $this->sendParameterError("The $parameterType `$field` has to be an integer");
            } else {
              $value = (int) $value;
            }
          } else if (($valueType==='float')||($valueType==='number')) {
            // float
            if (!preg_match("/^[\+\-]*(\d+(\.\d*)*|(\d*\.\d+))+$/", $value)) {
              return $this->sendParameterError("The $parameterType `$field` has to be a float");
            } else {
              $value = (float) $value;
            }
          } else if ($valueType==='boolean') {
            if ((!is_bool($value)) && (!preg_match("/^(true|false|1|0)+$/", $value))) {
              return $this->sendParameterError("The $parameterType `$field` has to be a boolean");
            } else {
              $value = (boolean) $value;
            }
          }
          $params[$field] = $value;
        }
      }
    }
    $this->parameters = $params;
    return parent::handleAction($request, $actualAction);
  }

  /**
   * Checks if this request handler has a specific action,
   * even if the current user cannot access it.
   * We check for actionNameMETHOD() as well if no action exists
   * RequestHandler.php -> handleAction()
   *
   * @param string $action
   * @return bool
   */
  public function hasAction($action) {
    if (!parent::hasAction($action)) {
      if (
        ($this->hasMethod($action.'GET')) || ($this->hasMethod($action.'POST')) || ($this->hasMethod($action.'DELETE')) || ($this->hasMethod($action.'PUT')) || ($this->hasMethod($action.'PATCH'))
      ) {
        return true;
      }
    }
    return parent::hasAction($action);
  }

  /**
   * Checks method and action for request
   * RequestHandler.php -> handleRequest()
   * @param  string $action
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
        $allowedAction = $matches[2];
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
    $api = array();
    if ($data) {
      $this->dataRecord = $data;
    }
    ApiDataObject::to_nested_array($data, 0, $data);
    $api['data'] = $data;
    if (!$this->config()->get('useDataProperty')) {
      if (is_array($api['data'])) {
        foreach($api['data'] as $key => $value) {
          $api[$key] = $value;
        }
      } else {
        $api = $api['data'];
      }
      unset($api['data']);
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
    if (($this->dataRecord === null) && (!$this->error) && (!$this->message) && (!$this->help)) {
      return null;
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

  function queryParametersToSQLFilter($parameters = null, $class = null) {
    if (!is_array($parameters)) {
      $class = $parameters;
      $parameters = $this->request->getVars();
      unset($parameters['url']); // is used by silverstripe
    }
    if (!$class) {
      $class = $this->stat('api_model');
    }
    $filter = array();
    $underscoreFields = $this->config()->get('underscoreFields');
    foreach($parameters as $field => $value) {
      $searchFilterModifier = '';
      // http://doc.silverstripe.com/framework/en/topics/datamodel
      if (($value[0] === '%')&&($value[strlen($value)-1] === '%')) {
        $searchFilterModifier = ':PartialMatch';
        $value = substr($value, 1, -1);
      } else if ($value[0] === '%') {
        $searchFilterModifier = ':EndsWith';
        $value = substr($value, 0, -1);
      } else if ($value[strlen($value)-1]==='%') {
        $searchFilterModifier = ':StartsWith';
        $value = substr($value, 0, -1);
      } elseif ($value[0] === '!') {
        $searchFilterModifier = ':Negation';
        $value = substr($value, 1);
      } elseif (substr($value, 0, 2) === '<=') {
        $searchFilterModifier = ':LessThanOrEqual';
        $value = substr($value, 2);
      } elseif ($value[0] === '<') {
        $searchFilterModifier = ':LessThan';
        $value = substr($value, 1);
      } elseif (substr($value, 0, 2) === '>=') {
        $searchFilterModifier = ':GreaterThanOrEqual';
        $value = substr($value, 2);
      } elseif ($value[0] === '<') {
        $searchFilterModifier = ':GreaterThan';
        $value = substr($value, 1);
      }
      if (($underscoreFields) && ($class)) {
        $field = ApiDataObject::real_field_name($field, $class);
      }
      $filter[$field.$searchFilterModifier] = $value;
    }
    return $filter;
  }

  function sendData($data = null, $code = null) {
    $apiData = $this->prepareApiData($data);
    if ($apiData === null) {
      return $this->sendNotFound();
    }
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
    $this->code  = $this->statusCode = $args['code'];
    return $this->sendData();
  }

  function sendSuccessfulPut($msg = 'resource updated successfully', $code = 201) {
    $args = $this->sortCodeAndMessage($code, $msg);
    $this->code = $this->statusCode = $args['code'];
    $this->message = $args['message'];
    return $this->sendData();
  }

  function sendSuccessfulDelete($msg = 'resource deleted successfully', $code = 202) {
    $args = $this->sortCodeAndMessage($code, $msg);
    $this->code = $this->statusCode = $args['code'];
    $this->message = $args['message'];
    return $this->sendData();
  }

  function sendNotFound($msg = 'resource not found', $code = 404) {
    $args = $this->sortCodeAndMessage($code, $msg);
    $this->code = $this->statusCode = $args['code'];
    $this->message = $args['message'];
    return $this->sendData();
  }

  function sendSuccessfulPost($uriOrData = null, $code = 201, $msg = 'resource created succesfully') {
    $args = $this->sortCodeAndMessage($code, $msg);
    $this->code = $this->statusCode = $args['code'];
    $this->message = $args['message'];
    if (is_string($uriOrData)) {
      $this->statusCode = 303;
      return $this->redirect($uriOrData, $this->statusCode);
    } else {
      return $this->sendData($uriOrData);
    }
  }

  function sendPermissionFailure($msg = 'permission failure', $code = 401) {
    return $this->sendError($msg, $code);
  }

  function sendParameterError($msg = 'wrong / missing parameter(s)', $code = 422) {
    return $this->sendError($msg, $code);
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
