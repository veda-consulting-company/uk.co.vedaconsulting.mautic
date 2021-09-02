<?php
/**
 *
 * CiviRules action to create contact from Mautic Webhook.
 */

use Civi\Api4\Contact;
use CRM_Mautic_Utils as U;
use CRM_Mautic_Connection as MC;

class CRM_Civirules_Action_ContactSyncToMautic extends CRM_Civirules_Action {

  protected $ruleAction = [];

  protected $action = [];

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    if (U::$skipUpdatesToMautic) {
      U::checkDebug('Skipping update to Mautic because contact has just been synced.');
      return;
    }
    // The civi api gives more data compared to $triggerData::getEntityData().
    $civicrmContactID = $triggerData->getContactId();
    if (empty($civicrmContactID)) {
      return;
    }

    $fields = CRM_Mautic_Contact_FieldMapping::getMapping();
    unset($fields['civicrm_contact_id']);
    $fields = array_merge(array_keys($fields), CRM_Mautic_Contact_FieldMapping::getCommsPrefsFields());
    $civicrmContact = Contact::get(FALSE)
      ->addSelect(...$fields)
      ->addWhere('id', '=', $civicrmContactID)
      ->execute()
      ->first();

    $commsPrefsChanged = CRM_Mautic_Contact_FieldMapping::hasCiviContactCommunicationPreferencesChanged(
      $civicrmContact, $triggerData->getOriginalData()
    );
    $mauticContact = CRM_Mautic_Contact_FieldMapping::convertToMauticContact($civicrmContact, TRUE);
    $mauticContactId = CRM_Mautic_Contact_ContactMatch::getMauticContactIDFromCiviContact($civicrmContact);

    if ($mauticContact) {
      /** @var \Mautic\Api\Contacts $api */
      $api = MC::singleton()->newApi('contacts');
      if ($mauticContactId) {
        U::checkDebug('Updating mautic contact: ', $mauticContact);
        $response = $api->edit($mauticContactId, $mauticContact);
        if ($commsPrefsChanged) {
          CRM_Mautic_Contact_FieldMapping::pushCommsPrefsToMautic($api, $mauticContactId, $civicrmContact);
        }
      }
      else {
        U::checkDebug('Creating mautic contact: ', $mauticContact);
        $response = $api->create($mauticContact);
      }
      if (!$mauticContactId && !empty($response['contact']['id'])) {
        $mauticContactId = $response['contact']['id'];
      }
      // Save mautic id to custom field if it is not stored already.
      U::saveMauticIDCustomField($civicrmContact, $mauticContactId);
      // Sync segments from Civi Groups.
      // For this to be effective with smart groups the rule should have a delay
      // greater than smartgroup cache timeout.
      U::syncContactSegmentsFromGroups($civicrmContactID, $mauticContactId);
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
