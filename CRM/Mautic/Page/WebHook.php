<?php
use CRM_Mautic_ExtensionUtil as E;

class CRM_Mautic_Page_WebHook extends CRM_Core_Page {

  /**
   * Process a webhook
   * {@inheritDoc}
   * @see CRM_Core_Page::run()
   */
  public function run() {
    $civi_key = CRM_Mautic_WebHook::getkey();
    $request_key = CRM_Utils_Request::retrieveValue('key', 'String', NULL, FALSE, 'GET');
    if (!$civi_key || $request_key != $civi_key) {
      http_response_code(400);
      exit(1);
    }
    // Process.
    $rawData = file_get_contents("php://input");
    $handler = new CRM_Mautic_WebHook_Handler();
    $handler->process($rawData);
    // Filter out
  }

}
