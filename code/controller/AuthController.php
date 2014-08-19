<?php

class AuthController extends ApiController {

  private static $api_parameters = array(
    "GET:session" => array(
      '$ID' => "*",
    ),
    "DELETE:session" => array(
      '$ID!' => "*",
    ),
    "POST:session" => array(
      "email!" => "*",
      "password!" => "*",
    ),
    "GET:permission" => array(
      '$ID!'    => "/^[a-zA-Z\_]+$/",
      'integer' => 'int',     // only for tests (see AuthControllerTest->testTypeOfFields())
      'float'   => 'float',   // "
      'boolean' => 'boolean', // "
    )
  );

  private static $api_allowed_actions = array(
    "GET:index"                       => true,
    "POST:session"                    => true,
    "GET:session"                     => true,
    "DELETE:session"                  => true,
    "GET:sessions"                    => true,
    "DELETE:sessions"                 => true,
    "GET:permission"                  => true,
    // these inoffensive methods are for testing only and have no productive reason
    "GET:testWrongMethodName"         => '->permissionMethodShouldNotExists',
    "GET:testIsValidSession"          => '->isValidApiSession',
    "GET:testAPIPermission"           => 'API_ACCESS',
    "GET:testADMINPermission"         => 'ADMIN',
    "GET:testPermissionFailure"       => true,
    "GET:testSendError"               => true,
    "PUT:testSuccessfulPut"           => true,
    "DELETE:testSendSuccessfulDelete" => true,
    "POST:testSendSuccessfulPost"     => true,
    "GET:testSendNotFound"            => true,
    "GET:testSendingEmptyData"        => true,
  );

  function session() {
    if ($this->request->isGET()) {
      $session = $this->getSessionFromRequest();
      $this->restfulSession = $session;
      $this->setSessionByApiSession();
      return ($session) ? $this->sendData($session) : $this->sendNotFound();
    } else if ($this->request->isPOST()) {
      $data = $this->request->data;
      $member = Member::get()->filter(array("Email" => $data->email))->First();
      if (($member)&&($member->checkPassword($data->password)->valid())) {
        // TODO: check for group / permission
        if ($requiredGroup = Config::inst()->get('AuthSession', 'requiredGroup')) {
          // check that user is in Group
          if (!$member->inGroup($requiredGroup)) {
            return $this->sendPermissionFailure("Member is not in the required group `$requiredGroup`");
          }
        }
        if ($requiredPermission = Config::inst()->get('AuthSession', 'requiredPermission')) {
          // check that member has required permission
          if (!Permission::checkMember($member, $requiredPermission)) {
            return $this->sendPermissionFailure("Member has no `$requiredPermission` permission");
          }
        }
        $session = new AuthSession();
        $session->Member = $member;
        $session->MemberID = $member->ID;
        $session->write();
        return $this->sendSuccessfulPost($session);
      }
      return $this->sendError("Couldn't match password / email", 400);
    } else if ($this->request->isDELETE()) {
      if ($session = $this->restfulSession) {
        $session->delete();
        return $this->sendSuccessfulDelete();
      } else {
        return $this->sendNotFound('No session could be detected');
      }
    }
  }

  function sessions() {
    if (!$this->isValidApiSession())
      return $this->sendInvalidApiSession();
    if ($this->request->isGET()) {
      return $this->sendData(array(
        "count" => AuthSession::get()->filter(array(
          "MemberID" => $this->restfulSession->Member()->ID
        ))->Count()
      ));
    } else if ($this->request->isDELETE()) {
      AuthSession::get()->filter(array(
        "MemberID" => $this->restfulSession->Member()->ID
      ))->removeAll();
      return $this->sendSuccessfulDelete();
    }
  }

  function permissionGET() {
    $code = $this->request->param("ID");
    return $this->sendData(array(
      "permission" => array(
        "code"    => $code,
        "granted" => Permission::check($code),
      ),
    ));
  }

  function testIsValidSession() {
    return $this->sendData(array(
      "message" => "This data should only be seen if we have a valid session",
    ));
  }

  function testPermissionFailure() {
    return $this->sendPermissionFailure();
  }

  function testSendError() {
    return $this->sendError();
  }

  function testSuccessfulPut() {
    return $this->sendSuccessfulPut();
  }

  function testSendSuccessfulDelete() {
    return $this->sendSuccessfulDelete();
  }

  function testSendNotFound() {
    return $this->sendNotFound();
  }

  function testSendSuccessfulPost() {
    return $this->sendSuccessfulPost();
  }

  function testAPIPermission() {
    return $this->testIsValidSession();
  }

  function testADMINPermission() {
    return $this->testIsValidSession();
  }

  function testSendingEmptyData() {
    return $this->sendData(Member::get()->byID(52435435324));
  }

}
