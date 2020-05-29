<?php
/**
 * CiviRules action to create contact from Mautic Webhook.
 */

class CRM_Civirules_Action_ContactSyncToMautic extends CRM_Civirules_Action {

  protected $ruleAction = array();

  protected $action = array();

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $this->logAction(__CLASS__, $triggerData);
  }
}
