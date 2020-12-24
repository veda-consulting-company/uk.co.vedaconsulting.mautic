<?php
/**
 * Class for CiviRule Condition Mautic WebHook is of type.
 *
 */

class CRM_Civirules_Condition_MauticContactHasTag extends CRM_Civirules_Condition {

  private $conditionParams = array();

  public function getExtraDataInputUrl($ruleConditionId) {
    return CRM_Utils_System::url('civicrm/admin/mautic/civirules/condition/mautic_contact_has_tag', 'rule_condition_id=' . $ruleConditionId);
  }

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
   * @access public
   */
  public function setRuleConditionData($ruleCondition) {
    parent::setRuleConditionData($ruleCondition);
    $this->conditionParams = array();
    if (!empty($this->ruleCondition['condition_params'])) {
      $this->conditionParams = unserialize($this->ruleCondition['condition_params']);
    }
  }

  /**
   * Method to check if the condition is valid.
   *
   * @param object CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   * @access public
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $webHook = $triggerData->getEntityData('mauticwebhook');
    $mauticContact = CRM_Mautic_BAO_MauticWebHook::getProvidedData('contact', $webHook);
    if (empty($mauticContact['tags'])) {
      return FALSE;
    }
    $searchTags = $this->getSelectedTags();
    $op = $this->conditionParams['operator'];
    // Normalize to just an array of tag names. We don't match IDs.
    $contactTags = array_map(function($val) {
      return $val['tag'];
    }, $mauticContact['tags']);
    return $op == 'all' ? empty(array_diff($searchTags, $contactTags)) : !empty(array_intersect($searchTags, $contactTags));
  }

  /**
   * Returns a user friendly text explaining the condition params
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    foreach ($this->getSelectedTags() as $tag) {
      $typeLabels[] = $tag;
    }
    $op = $this->conditionParams['operator'] == 'all' ? ts('all of') : ts('one of');
    return ts("Has %1 tags: ", ['%1' => $op]) . implode(', ', $this->getSelectedTags());
  }

  protected function getSelectedTags() {
    $tags = !empty($this->conditionParams['mautic_tags']) ? $this->conditionParams['mautic_tags'] : '';
    return explode(',', $tags);
  }

  /**
   * This function validates whether this condition works with the selected trigger.
   *
   * This function could be overriden in child classes to provide additional validation
   * whether a condition is possible in the current setup. E.g. we could have a condition
   * which works on contribution or on contributionRecur then this function could do
   * this kind of validation and return false/true
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    return $trigger->doesProvideEntity('MauticWebHook');
  }
}
