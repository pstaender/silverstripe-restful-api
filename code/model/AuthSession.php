<?php

class AuthSession extends DataObject {

  private static $db = array(
    "UID" => "Varchar(64)",
    "ValidUntilTimestamp" => "Int",
    "ValidUntil" => "SS_DateTime",
    "RequestedFromIP" => "Varchar(32)",
  );

  private static $has_one = array(
    "Member" => "Member",
  );

  private static $indexes = array(
    "UID" => true,
  );

  private static $api_fields = array(
    'Accesstoken', 'ValidUntil', 'ValidUntilTimestamp', 'User', 'URI'
  );

  static function get_by_authtoken($token) {
    return AuthSession::get()->filter(array(
      'UID' => $token,
      'ValidUntil:GreaterThan' => time(),
    ))->First();
  }

  function Accesstoken() {
    return $this->UID;
  }

  function User() {
    return $this->Member();
  }

  function URI() {
    return Director::absoluteBaseURL()."auth/session/";//.(($withUID) ? $this->UID : '');
  }

  function setValidInMinutesFromNow($minutes = null) {
    if (!is_integer($minutes)) {
      $minutes = $this->config()->get('validInMinutesFromNow');
    }
    $this->ValidUntilTimestamp = $this->ValidUntil = time() + ( $minutes * 60 );
  }

  function IsValid() {
    return time() <= $this->ValidUntilTimestamp;
  }

  function onBeforeWrite() {
    parent::onBeforeWrite();
    if (!$this->ValidUntil) {
      $this->setValidInMinutesFromNow();
    }
    if (!$this->RequestedFromIP) {
      $this->RequestedFromIP = $_SERVER['REMOTE_ADDR'];
    }
    if (!$this->UID) {
      $generator = new RandomGenerator();
      $this->UID = $generator->randomToken('sha1');
    }
  }

}
