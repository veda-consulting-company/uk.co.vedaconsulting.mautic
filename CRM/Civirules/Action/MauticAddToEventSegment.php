<?php
/**
 *
 * CiviRules action to add Event Participant to a Segment based on Event Segment settings.
 */

use Civi\Api4\Contact;
use CRM_Mautic_Utils as U;
use CRM_Mautic_Connection as MC;

class CRM_Civirules_Action_MauticAddToEventSegment extends CRM_Civirules_Action {

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
    CRM_Core_Error::debug_var(__CLASS__ . __FUNCTION__, ['START']);
    // The civi api gives more data compared to $triggerData::getEntityData().
    $civicrmContactID = $triggerData->getContactId();
    if (empty($civicrmContactID)) {
      return;
    }
    $activity = $triggerData->getEntityData('Activity');
    if (!empty($activity['id']) && !empty($activity['source_record_id'])) {
      // Activity Type should be Event Registration. I
      $registrationActivityTypeId = CRM_Core_Pseudoconstant::getKey(
        'CRM_Activity_BAO_Activity',
        'activity_type_id',
        'Event Registration'
      );
      if ($registrationActivityTypeId != $activity['activity_type_id']) {
        // Not a registration activity.
        return;
      }
      $participantId = $activity['source_record_id'];
      $eventId = CRM_Core_DAO::getFieldValue('CRM_Event_DAO_Participant',
        $participantId,
        'event_id',
        'id'
      );
    }
    elseif ($participant = $triggerData->getEntityData('Participant')) {
      $eventId = $participant['event_id'];
    }
     
    if (!$eventId) {
      // Nothing to do.
      return;
    }
    $segmentId = CRM_Mautic_Utils::getSegmentIdForEvent($eventId);
    $fields = CRM_Mautic_Contact_FieldMapping::getMapping();
    unset($fields['civicrm_contact_id']);
    $fields = array_merge(array_keys($fields), CRM_Mautic_Contact_FieldMapping::getCommsPrefsFields());
    $civicrmContact = Contact::get(FALSE)
      ->addSelect(...$fields)
      ->addWhere('id', '=', $civicrmContactID)
      ->execute()
      ->first();
    $mauticContactId = CRM_Mautic_Contact_ContactMatch::getMauticContactIDFromCiviContact($civicrmContact, TRUE);
    if ($segmentId && $mauticContactId) {
      try {
        $segmentApi = MC::singleton()->newApi('segments');
        $segmentApi->addContact($segmentId, $mauticContactId);
      }
      catch (Exception $e) {
        CRM_Core_Error::debug_log_message($e->getMessage());
      }
    }
    else {
      U::checkDebug(__CLASS__ . ' Trigger data does not map to segment and contact.', [
        'mauticContactId' => $mauticContactId,
        'activityType' => $registrationActivityTypeId,
        'contactId' => $civicrmContactID,
        'participantId' => $participantId,
        'eventId' => $eventId,
        'segmentId' => $segmentId,
        'activityId' => $activity,
      ]); 
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
