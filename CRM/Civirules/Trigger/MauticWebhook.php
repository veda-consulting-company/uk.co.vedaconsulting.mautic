<?php

use CRM_Mautic_ExtensionUtil as E;

class CRM_Civirules_Trigger_MauticWebhook extends CRM_Civirules_Trigger_Post {
  /**
   * This is a pseudo entity.
   * @var string
   */
  protected $objectName = 'MauticWebhook';

  /**
   * Returns an array of entities on which the trigger reacts
   *
   * @return CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function reactOnEntity() {
    return new CRM_Civirules_TriggerData_EntityDefinition($this->objectName, $this->objectName, $this->getDaoClassName(), 'MauticWebhook');
  }

  /**
   * Return the name of the DAO Class. If a dao class does not exist return an empty value
   *
   * @return string
   */
  protected function getDaoClassName() {
    return 'CRM_Mautic_DAO_MauticWebhook';
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a trigger
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleId
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleId) {
    return FALSE;
  }

  /**
   * Returns a description of this trigger
   *
   * @return string
   */
  public function getTriggerDescription() {
    return E::ts('Mautic Webhook processed');
  }

  /**
   * Trigger a rule for this trigger
   *
   * @param $op
   * @param $objectName
   * @param $objectId
   * @param $objectRef
   */
  public function triggerTrigger($op, $objectName, $objectId, $objectRef, $eventID) {
    $triggerData = $this->getTriggerDataFromPost($op, $objectName, $objectId, $objectRef, $eventID);
    if (isset($triggerData->getEntityData('MauticWebhook')['civirules_do_not_process'])) {
      return;
    }
    CRM_Civirules_Engine::triggerRule($this, clone $triggerData);
  }

  /**
   * Alter the trigger data with extra data
   *
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function alterTriggerData(CRM_Civirules_TriggerData_TriggerData &$triggerData) {
    $hook_invoker = CRM_Civirules_Utils_HookInvoker::singleton();
    $hook_invoker->hook_civirules_alterTriggerData($triggerData);

    // Set the trigger contact id to the Webhook data.
    $webhook = $triggerData->getEntityData('MauticWebhook');
    // Retrieve all data for webhook
    $originalData = $triggerData->getOriginalData();
    $webhook = array_merge($originalData, $webhook);
    // Decode webhook data
    $webhook['data'] = json_decode($webhook['data'], TRUE);
    $triggerData->setEntityData('MauticWebhook', $webhook);
    // Set contact ID from mautic data
    if (!$triggerData->getContactId()) {
      $contact = CRM_Mautic_BAO_MauticWebhook::getProvidedData('contact', $webhook['data']);
      $triggerData->setContactId(CRM_Mautic_Contact_FieldMapping::lookupMauticValue('civicrm_contact_id', $contact));
    }
  }

}
