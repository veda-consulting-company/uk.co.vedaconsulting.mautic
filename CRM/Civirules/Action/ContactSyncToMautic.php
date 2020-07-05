<?php
/**
 * 
 * CiviRules action to create contact from Mautic Webhook.
 */
use CRM_Mautic_Utils as U;
use CRM_Mautic_Connection as MC;

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
    // The civi api gives more data compared to $triggerData::getEntityData().
    $contact_id = $triggerData->getContactId();
    $fields = CRM_Mautic_Contact_FieldMapping::getMapping();
    unset($fields['civicrm_contact_id']);
    $fields = array_keys($fields);
    $contact = civicrm_api3('Contact', 'getsingle', ['id' => $contact_id, 'return' => $fields]);
    U::checkDebug("Contact for CiviRules Action", $contact);
    if ($contact) {
      $mauticContact = CRM_Mautic_Contact_FieldMapping::convertToMauticContact($contact);
      $mauticContactId = CRM_Mautic_Contact_ContactMatch::getMauticFromCiviContact($contact);
      if ($mauticContact) {
        $api = MC::singleton()->newApi('contacts');
        if ($mauticContactId) {
          U::checkDebug("Updating mautic contact.");
          $api->edit($mauticContactId, $mauticContact);
        }
        else {
          U::checkDebug("Creating mautic contact.");
          $api->create($mauticContact);
        }
      }
      
    }
  }
 
  /**
   * 
   * {@inheritDoc}
   * @see CRM_Civirules_Action::getExtraDataInputUrl()
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return NULL; 
  }
}
