<?php

class AuthController extends ApiController {

  private static $api_parameters = array(
    "GET:session" => array(
      '$ID!' => "/^.{32,}$/",
    ),
    "DELETE:session" => array(
      '$ID!' => "*",
    ),
    "POST:session" => array(
      "email!" => "*",
      "password!" => "*",
    ),
  );

  private static $api_allowed_actions = array(
    "POST:session" => true,
    "GET:session" => true,
    "DELETE:session" =>true,
    "GET:sessions" => true,
    "DELETE:sessions" => true,
  );

  function session() {
    if ($this->request->isGET()) {
      return $this->sendJSON($this->getSessionFromRequest());
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
        $session->MemberID = $member->ID;
        $session->write();
        return $this->sendJSON($session);
      }
      return $this->sendError("Couldn't match password / email", 400);
    } else if ($this->request->isDELETE()) {
      if ($session = $this->request->session) {
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
      return $this->sendJSON(array(
        "count" => AuthSession::get()->filter(array("MemberID" => $this->request->session->MemberID))->Count()
      ));
    } else if ($this->request->isDELETE()) {
      AuthSession::get()->filter(array("MemberID" => $this->request->session->MemberID))->removeAll();
      return $this->sendSuccessfulDelete();
    }
  }

}
