<?php
/**
 * CiviRules action to create contact from Mautic Webhook.
 */

class CRM_Civirules_Action_MauticWebHookCreateContact extends CRM_Civirules_Action {

  protected $ruleAction = array();

  protected $action = array();

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    // @todo: implement.
    CRM_Core_Error::debug_log_message('MauticWebHookCreateContact action processed..');
  }
  
  public function getExtraDataInputUrl($ruleActionId) {
    // @todo: implement input form.
    return ''; 
  }
}
