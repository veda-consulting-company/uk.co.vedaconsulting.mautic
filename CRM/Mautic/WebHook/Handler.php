<?php 
use CRM_Mautic_Connection as MC;
use CRM_Mautic_ExtensionUtil as E;
use CRM_Mautic_Utils as U;

/**
 * @class
 * /
 * Functionality for processing Mautic Webhooks.
 * 
 * For each individual trigger event, tries to Match to a contact and creates a webhook entity and/or activity.
 * 
 * 
 */
class CRM_Mautic_WebHook_Handler extends CRM_Mautic_WebHook {
  
  
  public function identifyContact($contact) {
   // @todo: Add setting to select dedupe rule to identify
   // incoming contact data.
      
    // $contact is a Mautic contact in a std object.
    if (!$contact) {
      return;
    }
  }
  
  protected function processEvent($trigger, $data) {
     
    $civicrmContactId = $activityId = NULL;
    $eventTrigger = str_replace('mautic.', '', $trigger);
    // Data may include lead and contact properties - they appear to be the same.
    $contact = !empty($data->contact) ? $data->contact : NULL;
    CRM_Core_Error::debug_var("webhook trigger", $eventTrigger);
    if (!$eventTrigger || !$contact) {
     CRM_Core_Error::debug_log_message("Processing Mautic webhook: trigger or contact not found exiting.");
      return;
    } 
    
    // Don't act on updates being pushed from Civi.
    // We detect this from the connected user, which should be reserved by the extension.
    $modifiedBy = !empty($contact->modifiedBy) ? $contact->modifiedBy : $contact->createdBy;
    $ignoreTriggersIfCiviModified = ['lead_post_save_new', 'lead_post_save_update'];
    if (in_array($eventTrigger, $ignoreTriggersIfCiviModified)) {
      $connectedUserId = CRM_Utils_Array::value('id', MC::singleton()->getConnectedUser());
      if ($connectedUserId == $contact->id || $connectedUserId == $modifiedBy) {
        U::checkDebug("WebHook: " . $eventTrigger ." - Mautic Contact last modified by CiviCRM - no further processing required." );
        return;
      }
    }
    
    if (!empty($contact->fields->core->civicrm_contact_id->value)) {
      $civicrmContactId = $contact->fields->core->civicrm_contact_id->value;
    }
    
    if (!$civicrmContactId) {
      $civicrmContactId = $this->identifyContact($contact);
    }
    // We have extracted enough information for an action. 
    if ($civicrmContactId) {
      // Create an activity for this contact.
      // @todo Should this be a setting? 
      // Then we let users choose whether to create one by default or in a civi rule.
      // 
      $activityId = $this->createActivity($eventTrigger, $contact, $civicrmContactId);
    }
    else {
      U::checkDebug("Webhook: no matching contact found for trigger: " . $trigger);
    }
    // Create a WebHook entity to store the data.
    // This may be processed by CiviRules.
    civicrm_api3('MauticWebHook', 'create', [
      'contact_id' => $civicrmContactId,
      'activity_id' => $activityId,
      'data' => json_encode($data),
      'webhook_trigger_type' => $eventTrigger,      
    ]);
  }
 
  /**
   * Process incoming webhook.
   * @param [] $data
   */
  public function process($data) {
    CRM_Core_Error::debug_log_message("Processing Mautic webhook.");
    CRM_Core_Error::debug_var('triggerdatakeys', array_keys((array)$data));
    $triggers = static::getEnabledTriggers();
    foreach ($triggers as $trigger) {
      CRM_Core_Error::debug_log_message("trying trigger" . $trigger);
      if (!empty($data->{$trigger})) {
        CRM_Core_Error::debug_log_message("found data at " . $trigger);
        // We may be processing a batch.
        if (is_array($data->{$trigger})) {
          foreach ($data->{$trigger} as $idx => $item) {
            $this->processEvent($trigger, $item);
          }
        }
      }
    }
    if (!$trigger) {
      CRM_Core_Error::debug_log_message("Mautic Webhook: Trigger not found.");
    }
  }
  
  public function createActivity($trigger, $mauticContact, $cid, $data=NULL) {
    // We expect this to be called only om WebHook url.
    // So won't bother catching exceptions.
    $fieldInfo = [];
    $fieldResult = civicrm_api3('CustomField', 'get', [
      'sequential' => 1,
      'custom_group_id' => "Mautic_Webhook_Data",
    ]);
    foreach ($fieldResult['values'] as $field) {
      $fieldInfo[$field['name']] = $field['id'];
    }
    $params = [
      'activity_type_id' => static::activityType,
      'subject' => E::ts('Webhook: %1', [1 => static::getTriggerLabel($trigger)]),
      'source_contact_id' => $cid,
      'custom_' . $fieldInfo['Trigger_Event'] => $trigger,
      'custom_' . $fieldInfo['Data'] => json_encode($mauticContact),
    ];
    U::checkDebug('Creating mautic webhook activity');
    $result =  civicrm_api3('Activity', 'create', $params); 
    return !empty($result['id']) ? $result['id'] : NULL;
  }
}
