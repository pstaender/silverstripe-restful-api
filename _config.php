<?php



if (!defined('RESTFUL_API_MODULE_NON_BLOCKING_SESSION')) {
  // deactivated by default
  define('RESTFUL_API_MODULE_NON_BLOCKING_SESSION', false);
}

if (RESTFUL_API_MODULE_NON_BLOCKING_SESSION === true) {
  $handler = new ApiModuleSessionHandler();
  session_set_save_handler($handler, true);
}
