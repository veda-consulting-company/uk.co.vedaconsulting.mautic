<?php
/**
 * Class for CiviRule Condition Mautic WebHook is of type.
 *
 */

class CRM_Civirules_Condition_MauticWebHookType extends CRM_Civirules_Condition {

  private $conditionParams = array();

  public function getExtraDataInputUrl($ruleConditionId) {
    return CRM_Utils_System::url('civicrm/admin/mautic/civirules/condition/mauticwebhooktype', 'rule_condition_id=' . $ruleConditionId);
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
   * Method to check if the condition is valid, will check if the WebHook
   * is of the selected type
   *
   * @param object CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   * @access public
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $webhook = $triggerData->getEntityData('MauticWebHook');
    $negate = $this->conditionParams['operator'];
    $type = str_replace('mautic.', '', CRM_Utils_Array::value('webhook_trigger_type', $webhook, ''));
    // If no type can be found, return early.
    if (!$type) {
      CRM_Mautic_Utils::checkDebug("Cannot find Type in MauticWebHook trigger data.", $webhook);
      return FALSE;
    }
    CRM_Mautic_Utils::checkDebug("Checking for {$type} against", $this->getSelectedTypes());
    $isType = $type && in_array($type, $this->getSelectedTypes());
    return $negate ? !$isType : $isType;
  }

  /**
   * Returns a user friendly text explaining the condition params
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    // Operator negates the condition.
    if ($this->conditionParams['operator'] == 0) {
      $operator = 'is one of';
    }
    if ($this->conditionParams['operator'] == 1) {
      $operator = 'is not one of';
    }
    $labels = CRM_Mautic_WebHook::getAllTriggerOptions();
    $typeLabels = [];
    foreach ($this->getSelectedTypes() as $type) {
      $typeLabels[] = $labels['mautic.' . $type];
    }
    return "Type " . $operator . ": " . implode(', ', $typeLabels);
  }

  protected function getSelectedTypes() {
    return explode(',', $this->conditionParams['mautic_webhook_type']);
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
