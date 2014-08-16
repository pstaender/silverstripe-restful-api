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
    "POST:session"      => true,
    "GET:session"       => true,
    "DELETE:session"    => true,
    "GET:sessions"      => true,
    "DELETE:sessions"   => true,
    "GET:permission"    => true,
    "GET:no_permission" => '->isValidSession',
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
            return $this->sendError("Member is not in the required group `$requiredGroup`", 401);
          }
        }
        if ($requiredPermission = Config::inst()->get('AuthSession', 'requiredPermission')) {
          // check that member has required permission
          if (!Permission::checkMember($member, $requiredPermission)) {
            return $this->sendError("Member has no `$requiredPermission` permission", 401);
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
        return $this->sendError('No session could be detected', 404);
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

  function permission() {
    // will be handled by permissionGET
    // but the method is needed for the framework to check the called action
    // TODO: get rid of this workaround
    return null;
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

}
