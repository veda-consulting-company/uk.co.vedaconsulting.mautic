<?php
/**
 *
 * CiviRules action to add Event Participant to a Segment based on Event Segment settings.
 */

use Civi\Api4\Contact;
use CRM_Mautic_Utils as U;
use CRM_Mautic_Connection as MC;

class CRM_Civirules_Action_MauticAddToEventSegment extends CRM_Civirules_Action {

  use CRM_Civirules_EventMauticTrait;

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    U::checkDebug(__CLASS__ . ' action triggerred', $this->ruleAction);
    // The civi api gives more data compared to $triggerData::getEntityData().
    $civicrmContactID = $triggerData->getContactId();
    if (empty($civicrmContactID)) {
      return;
    }
    $eventId = $this->getEventIdFromTriggerData($triggerData);
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
      U::checkDebug(__CLASS__ . ' Event does not map to a Mautic Segment.
      Consider adding a Condition to the rule.', [
        'mauticContactId' => $mauticContactId,
        'contactId' => $civicrmContactID,
        'eventId' => $eventId,
        'segmentId' => $segmentId,
        'rule_id' => $this->ruleAction['rule_id'],
      ]);
    }
  }

  /**
   *
   * {@inheritDoc}
   * @see CRM_Civirules_Action::getExtraDataInputUrl()
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return FALSE;
  }
}
