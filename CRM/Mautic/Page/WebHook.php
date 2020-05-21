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
    $request_key = CRM_Utils_Request::retrieve('key', 'String', CRM_Core_DAO::$_nullObject, TRUE, NULL, 'GET');
    if (!$civi_key || $request_key != $civi_key) {
       throw new Exception("Invalid Mautic Webhook key.");  
    }
    // Process.
    // parent::run();
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData);
    CRM_Mautic_WebHook::processWebHookPayload($data);
    // Filter out 
    CRM_Core_Error::debug_var('webhookReceived', []);
  }
  
}
