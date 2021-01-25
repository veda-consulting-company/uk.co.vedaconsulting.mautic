<?php

use CRM_Mautic_ExtensionUtil as E;

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
    return E::ts('Mautic WebHook processed');
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
    $webhook = $triggerData->getEntityData('mauticwebhook');
    $webhook['data'] = json_decode($webhook['data'], TRUE);
    $triggerData->setEntityData('mauticwebhook', $webhook);
    if (!$triggerData->getContactId()) {
      $contact = CRM_Mautic_BAO_MauticWebHook::getProvidedData('contact', $webhook['data']);
      $triggerData->setContactId(CRM_Mautic_Contact_FieldMapping::getValue($contact, 'civicrm_contact_id', NULL));
    }
  }

}
