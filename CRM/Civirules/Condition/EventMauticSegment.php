<?php
/**
 * Class for CiviRule Condition: Event is linked to a Mautic Segment.
 *
 */
class CRM_Civirules_Condition_EventMauticSegment extends CRM_Civirules_Condition {

  use CRM_Civirules_EventMauticTrait;

  private $conditionParams = [];

  public function getExtraDataInputUrl($ruleConditionId) {
    return FALSE;
  }

  /**
   * Method to check if the condition is valid.
   * Checks if an Event can be obtained from trigger data 
   *  and whether it maps to a Mautic Segment.
   * 
   * @param object CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $eventId = $this->getEventIdFromTriggerData($triggerData);
    return $eventId && $this->getMauticSegmentForEvent($eventId) > 0;
  }


  /**
   * Validate whether this condition works with the selected trigger.
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    foreach (['Event', 'Participant', 'Activity'] as $entity) {
      if ($trigger->doesProvideEntity($entity)) {
        return TRUE;
      }
    }
    return FALSE;
  }
}
