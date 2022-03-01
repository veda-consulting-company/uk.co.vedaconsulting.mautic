<?php
/**
 * Common functionality for CiviRules components using Events with Mautic.
 */
trait CRM_Civirules_EventMauticTrait {
  /**
   * Get Event ID from trigger data containing Event, Participant or Activity.
   * 
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * 
   * @return int
   */
  protected function getEventIdFromTriggerData(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $event = $triggerData->getEntityData('Event');
    $eventId = $event['id'] ?? NULL;
    if (!$eventId) {
      $participant = $triggerData->getEntityData('Participant');
      if ($participant) {
        $eventId = $this->getEventIdForParticipant($participant['id']);
      }
      else {
        $activity = $triggerData->getEntityData('Activity');
        $eventId = $this->getEventIdForActivity($activity);
      }
    }
    return $eventId;
  }

  /**
   * Gets Event ID from a Registration Activity.
   * @param array $activity
   *
   * @return int|void
   */
  protected function getEventIdForActivity($activity) {
    // Activity Type is registration.
    $isRegActivity = !empty($activity['activity_type_id']) 
      && !empty($activity['source_record_id'])
      && $this->activityTypeIsRegistration($activity['activity_type_id']);
    if ($isRegActivity) {
      return $this->getEventIdForParticipant($activity['source_record_id']);
    }
  }

  /**
   * Get Event ID for Participant.
   * @param int $participantId
   * 
   * @return int
   */
  protected function getEventIdForParticipant($participantId) {
    return CRM_Core_DAO::getFieldValue('CRM_Event_DAO_Participant',
        $participantId,
        'event_id',
        'id'
    );
  }

  /**
   * Checks if Activity type ID is for an event registration.
   */
  protected function activityTypeIsRegistration($activityTypeId) {
    static $registrationActivityTypeId = NULL;
    if (!$registrationActivityTypeId) {
    $registrationActivityTypeId = CRM_Core_Pseudoconstant::getKey(
        'CRM_Activity_BAO_Activity',
        'activity_type_id',
        'Event Registration'
      );
    }
    return $registrationActivityTypeId == $activityTypeId;
  }

  /**
   * Get Mautic Segment ID linked to an event.
   */
  protected function getMauticSegmentForEvent($eventId) {
    return CRM_Mautic_Utils::getSegmentIdForEvent($eventId);
  }
}