<?php 
use CRM_Mautic_Connection as MC;
use CRM_Mautic_ExtensionUtil as E;
use CRM_Mautic_Utils as U;

/**
 * @class
 * /
 * Functionality for processing Mautic Webhooks.
 * 
 */
class CRM_Mautic_WebHook_Handler extends CRM_Mautic_WebHook {
  
  
  public function identifyContact($contact) {
   // @todo: Add setting to select dedupe rule to identify
   // incoming contact data.
      
    $cid = NULL;
    // $contact is a Mautic contact in a std object.
    if (!$contact) {
      return;
    }
  }
  
  protected function processEvent($trigger, $data) {
    
    $civicrmContactId = NULL;
    $eventTrigger = str_replace('mautic.', '', $trigger);
    // Data may include lead and contact properties - they appear to be the same.
    $contact = !empty($data->contact) ? $data->contact : NULL;
    CRM_Core_Error::debug_var("webhook trigger", $eventTrigger);
    if (!$eventTrigger || !$contact ) {
     CRM_Core_Error::debug_log_message("Processing Mautic webhook: no contact data, exiting.");
      return;
    } 
    
    if (!empty($contact->fields->core->civicrm_contact_id->value)) {
      $civicrmContactId = $contact->fields->core->civicrm_contact_id->value;
    }
    // It would be good here to use a wrapper to normalize access to contact fields.
    
    if ($civicrmContactId && $eventTrigger == 'lead_post_save_new') {
      // If this is a new contact then it was created by Civi.
      // Ignore, it may be from a bulk sync.
      return;
    }
    CRM_Core_Error::debug_var("Processing Mautic webhook item:", ['trigger' => $eventTrigger, 
      'contact_id' => $civicrmContactId, 
      'mauticcontactid' => $contact->id]
        );
    
    
    if (!$civicrmContactId) {
      $civicrmContactId = $this->identifyContact($contact);
    }
    // We have extracted enough information for an action. 
    if ($civicrmContactId) {
      // Create an activity for this contact.
      $this->createActivity($eventTrigger, $contact, $civicrmContactId);
    }
    else {
      // @todo: setting to determine what to do here.
      // Could create a new contact?
      // Possibly expose in CiviRules conditions.
      U::checkDebug("Webhook: no matching contact found for trigger: " . $trigger);
      return;
    }
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
    CRM_Core_Error::debug_log_message("Creating Mautic Webhook Activity");
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
    CRM_Core_Error::debug_var('creating activity', $params);
    civicrm_api3('Activity', 'create', $params); 
  }
}
