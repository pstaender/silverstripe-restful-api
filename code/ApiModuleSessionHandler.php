<?php

// http://www.silverstripe.org/improving-silverstripe-performance/
// http://nz1.php.net/manual/en/class.sessionhandlerinterface.php

class ApiModuleSessionHandler implements SessionHandlerInterface {

  private $storage      = array();
  private $namespace    = "_";
  private $maxlifetime  = array();

  function open($savePath, $sessionName) {
    $this->namespace = $sessionName;
    return true;
  }

  function close() {
    return true;
  }

  function read($id) {
    return (isset($this->storage[$this->namespace][$id])) ? $this->storage[$this->namespace][$id] : null;
  }

  public function write($id, $data) {
    $this->storage[$this->namespace][$id] = $data;
    return true;
  }

  public function destroy($id) {
    unset($this->storage[$this->namespace][$id]);
    return true;
  }

  public function gc($maxlifetime) {
    $this->maxlifetime[$this->namespace] = $maxlifetime;
    // foreach (glob("$this->savePath/sess_*") as $file) {
    //   if (filemtime($file) + $maxlifetime < time() && file_exists($file)) {
    //     unlink($file);
    //   }
    // }
    return true;
  }
}
