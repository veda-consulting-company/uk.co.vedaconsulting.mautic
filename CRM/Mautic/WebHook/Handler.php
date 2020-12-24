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
   * @param [] $mauticContact
   *
   * @return int|NULL
   */
  public function identifyContact($mauticContact) {
    return CRM_Mautic_Contact_ContactMatch::getCiviFromMauticContact($mauticContact);
  }

  /**
   * @param [] $webhook
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function processEvent($webhook) {
    $data = json_decode($webhook['data'], TRUE);
    // Data may include lead and contact properties - they appear to be the same.
    $contact = !empty($data['contact']) ? $data['contact'] : NULL;
    U::checkDebug("webhook trigger", $webhook['webhook_trigger_type']);
    if (!$webhook['webhook_trigger_type'] || !$contact) {
      U::checkDebug("Processing Mautic webhook: trigger or contact not found exiting.");
      return;
    }

    // Don't act on updates being pushed from Civi.
    // We detect this from the connected user, which should be reserved by the extension.
    $modifiedBy = !empty($contact['modifiedBy']) ? $contact['modifiedBy'] : $contact['createdBy'];
    $ignoreTriggersIfCiviModified = ['mautic.lead_post_save_new', 'mautic.lead_post_save_update'];
    if (in_array($webhook['webhook_trigger_type'], $ignoreTriggersIfCiviModified)) {
      $connectedUserId = MC::singleton()->getConnectedUser()['id'] ?? NULL;
      if ($connectedUserId == $contact->id || $connectedUserId == $modifiedBy) {
        U::checkDebug("WebHook: " . $webhook['webhook_trigger_type'] ." - Mautic Contact last modified by CiviCRM - no further processing required." );
        return;
      }
    }

    $civicrmContactID = $this->identifyContact($contact);
    $activityId = NULL;
    // We have extracted enough information for an action.
    if ($civicrmContactID) {
      // Create an activity for this contact.
      // @todo Should this be a setting?
      // Then we let users choose whether to create one by default or in a civi rule.
      //
      $activityId = $this->createActivity($webhook['webhook_trigger_type'], $civicrmContactID);
    }
    else {
      U::checkDebug("Webhook: no matching contact found for trigger: " . $webhook['webhook_trigger_type']);
    }
    $params = [
      'id' => $webhook['id'],
      'contact_id' => $civicrmContactID,
      'activity_id' => $activityId,
      'processed_date' => date('YmdHis'),
    ];
    // Update the WebHook entity to store the data.
    // This may be processed by CiviRules.
    civicrm_api3('MauticWebHook', 'create', $params);
  }

  /**
   * Process incoming webhook.
   * @param [] $data
   */
  public function process($rawData) {
    $data = json_decode($rawData, TRUE);
    $triggers = static::getEnabledTriggers();
    foreach ($triggers as $trigger) {
      if (!empty($data[$trigger])) {
        // We received one of the webhook triggers that we have enabled.
        if (!is_array($data[$trigger])) {
          \Civi::log()->error("Mautic: {$trigger} is not an array");
          continue;
        }

        foreach ($data[$trigger] as $item) {
          // Don't process webhooks immediately because Mautic waits for the webhook to complete before continuing
          //   and this makes most batch actions hang quickly.
          civicrm_api3('MauticWebHook', 'create', [
            'data' => json_encode($item),
            'webhook_trigger_type' => $trigger,
            'processed' => 0,
          ]);
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
      'custom_' . $fieldInfo['Trigger_Event'] => str_replace('mautic.', '', $trigger),
      // No need to store Payload data, it's saved to MauticWebhook entity.
      'custom_' . $fieldInfo['Data'] => '', //json_encode($mauticContact),
    ];
    $result = civicrm_api3('Activity', 'create', $params);
    U::checkDebug('Created mautic webhook activity');
    return !empty($result['id']) ? $result['id'] : NULL;
  }
}
