<?php

class AuthSession extends DataObject
{

    private static $db = array(
    "UID" => "Varchar(64)",
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
    'Accesstoken', 'ValidUntil', 'IsValid', 'ValidUntilTimestamp', 'User', 'URI'
  );

    public static function find_by_accesstoken($token)
    {
        return AuthSession::get()->filter(array(
      'UID' => $token,
      'ValidUntil:GreaterThan' => time(),
    ))->First();
    }

  /**
   * We create / get a valid session by a defined accesstoken
   */
  public static function find_admin_session_by_accesstoken($token)
  {
      $session = self::find_by_accesstoken($token);
      if (!$session) {
          if (!(strlen(trim($token))>6)) {
              return user_error('admin authtoken must be at least 6 chars long');
          }
          $session = AuthSession::create(array(
        "UID" => $token,
      ));
          $session->write();
      }
      if ((!$session->Member()) || (!$session->Member()->inGroup('ADMIN'))) {
          // we need to attach an admin user to session to fulfill a valid session datao object with admin privileges
      $admin = Permission::get_members_by_permission('ADMIN')->First();
          $session->Member = $admin;
          $session->MemberID = $admin->ID;
          $session->write();
      }
      if (!$session->isValid()) {
          // renew session
      $session->setValidInMinutesFromNow(302400);
          $session->write();
      }
      return $session;
  }

    public function Accesstoken()
    {
        return $this->UID;
    }

    public function User()
    {
        return $this->Member();
    }

    public function URI()
    {
        return Director::absoluteBaseURL().$this->config()->get('urlSegment')."/session/";
    }

    public function setValidInMinutesFromNow($minutes = null)
    {
        if (!is_integer($minutes)) {
            $minutes = $this->config()->get('validInMinutesFromNow');
        }
        return $this->ValidUntil = time() + ($minutes * 60);
    }

    public function ValidUntilTimestamp()
    {
        return strtotime($this->dbObject('ValidUntil')->value);
    }

    public function IsValid()
    {
        return time() <= $this->ValidUntilTimestamp();
    }

    public function onBeforeWrite()
    {
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
