<?php

class CRM_Civirules_Trigger_MauticWebHook extends CRM_Civirules_Trigger_Post {
  /**
   * This is a pseudo entity.
   * @var string
   */  
  protected $objectName = 'MauticWebHook';
  
  /**
   * Returns an array of entities on which the trigger reacts
   *
   * @return CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function reactOnEntity() {
    return new CRM_Civirules_TriggerData_EntityDefinition($this->objectName, $this->objectName, $this->getDaoClassName(), 'MauticWebHook');
  }

  /**
   * Return the name of the DAO Class. If a dao class does not exist return an empty value
   *
   * @return string
   */
  protected function getDaoClassName() {
    return 'CRM_Mautic_DAO_MauticWebHook';
  }
  
  public function checkTrigger($objectName, $objectId, $op, $objectRef) {
    CRM_Core_Error::debug_var(__FUNCTION__, func_get_args());
    return $objectName == $this->objectName;
  }
  
  /**
   * Returns a redirect url to extra data input from the user after adding a trigger
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleId
   * @return bool|string
   * @access public
   * @abstract
   */
  public function getExtraDataInputUrl($ruleId) {
    return false;
  }
  
  /**
   * Returns a description of this trigger
   *
   * @return string
   * @access public
   * @abstract
   */
  public function getTriggerDescription() {
    return 'Mautic WebHook Recieved.';
  }
  
  /**
   * Alter the trigger data with extra data
   *
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function alterTriggerData(CRM_Civirules_TriggerData_TriggerData &$triggerData) {
    
    $hook_invoker = CRM_Civirules_Utils_HookInvoker::singleton();
    $hook_invoker->hook_civirules_alterTriggerData($triggerData);
    // Set the trigger contact id to the WebHook data.
    // The contact is discovered when the webhook is initially processed.
    if (!$triggerData->getContactId) {
      $webhook = $triggerData->getEntityData('mauticwebhook');
      if (!empty($webhook['contact_id'])) {
        $triggerData->setContactId($webhook['contact_id']);
      }
    }
    CRM_Core_Error::debug_var(__CLASS__ .'::'.__FUNCTION__, $webhook['contact_id']);
  }
  

}