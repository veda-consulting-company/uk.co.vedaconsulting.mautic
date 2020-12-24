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
 */
class CRM_Mautic_WebHook_Handler extends CRM_Mautic_WebHook {

  /**
   * Get corresponding CiviCRM contact from Mautic contact.
   *
   * @param Object $mauticContact
   */
  public function identifyContact($mauticContact) {
    return CRM_Mautic_Contact_ContactMatch::getCiviFromMauticContact($mauticContact);
  }

  /**
   * @param string $eventTrigger
   * @param Object $data
   *
   * @throws \CiviCRM_API3_Exception
   */
  protected function processEvent($eventTrigger, $data) {
    // Data may include lead and contact properties - they appear to be the same.
    $contact = !empty($data->contact) ? $data->contact : NULL;
    U::checkDebug("webhook trigger", $eventTrigger);
    if (!$eventTrigger || !$contact) {
      U::checkDebug("Processing Mautic webhook: trigger or contact not found exiting.");
      return;
    }

    // Don't act on updates being pushed from Civi.
    // We detect this from the connected user, which should be reserved by the extension.
    $modifiedBy = !empty($contact->modifiedBy) ? $contact->modifiedBy : $contact->createdBy;
    $ignoreTriggersIfCiviModified = ['mautic.lead_post_save_new', 'mautic.lead_post_save_update'];
    if (in_array($eventTrigger, $ignoreTriggersIfCiviModified)) {
      $connectedUserId = MC::singleton()->getConnectedUser()['id'] ?? NULL;
      if ($connectedUserId == $contact->id || $connectedUserId == $modifiedBy) {
        U::checkDebug("WebHook: " . $eventTrigger ." - Mautic Contact last modified by CiviCRM - no further processing required." );
        return;
      }
    }

    $civicrmContactId = $this->identifyContact($contact);
    $activityId = NULL;
    // We have extracted enough information for an action.
    if ($civicrmContactId) {
      // Create an activity for this contact.
      // @todo Should this be a setting?
      // Then we let users choose whether to create one by default or in a civi rule.
      //
      $activityId = $this->createActivity($eventTrigger, $civicrmContactId);
    }
    else {
      U::checkDebug("Webhook: no matching contact found for trigger: " . $eventTrigger);
    }
    $params = [
      'contact_id' => $civicrmContactId,
      'activity_id' => $activityId,
      'data' => json_encode($data),
      'webhook_trigger_type' => $eventTrigger,
    ];
    U::checkDebug('Creating MauticWebHook entity :', $params);
    // Create a WebHook entity to store the data.
    // This may be processed by CiviRules.
    civicrm_api3('MauticWebHook', 'create', $params);
    U::checkDebug('Created MauticWebHook entity for Contact ', $civicrmContactId);
  }

  /**
   * Process incoming webhook.
   * @param [] $data
   */
  public function process($data) {
    U::checkDebug("Processing Mautic webhook.", array_keys((array)$data));
    $triggers = static::getEnabledTriggers();
    foreach ($triggers as $trigger) {
      if (!empty($data->{$trigger})) {
        // We may be processing a batch.
        if (is_array($data->{$trigger})) {
          foreach ($data->{$trigger} as $idx => $item) {
            $this->processEvent($trigger, $item);
          }
        }
      }
    }
  }

  /**
   * @param $trigger
   * @param $mauticContact
   * @param $cid
   * @param null $data
   *
   * @return mixed|null
   * @throws \CiviCRM_API3_Exception
   */
  public function createActivity($trigger, $cid) {
    // We expect this to be called only om WebHook url.
    $fieldInfo = [];
    $fieldResult = civicrm_api3('CustomField', 'get', [
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
      // No need to store Payload data, it's saved to MauticWebhook entity.
      'custom_' . $fieldInfo['Data'] => '', //json_encode($mauticContact),
    ];
    $result =  civicrm_api3('Activity', 'create', $params);
    U::checkDebug('Created mautic webhook activity');
    return !empty($result['id']) ? $result['id'] : NULL;
  }
}
