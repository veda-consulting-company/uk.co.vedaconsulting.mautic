<?php
use CRM_Mautic_ExtensionUtil as E;
/**
 * Class for CiviRule Condition Mautic Webhook is of type.
 *
 */

class CRM_Civirules_Condition_MauticContactFieldValue extends CRM_Civirules_Condition {

  private $conditionParams = array();

  public function getExtraDataInputUrl($ruleConditionId) {
    return CRM_Utils_System::url('civicrm/admin/mautic/civirules/condition/mautic_contact_field_value', 'rule_condition_id=' . $ruleConditionId);
  }

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
   * @access public
   */
  public function setRuleConditionData($ruleCondition) {
    parent::setRuleConditionData($ruleCondition);
    $this->conditionParams = [];
    if (!empty($this->ruleCondition['condition_params'])) {
      $this->conditionParams = unserialize($this->ruleCondition['condition_params']);
    }
  }

  /**
   *
   * @param object CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   * @access public
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $field_name = $this->conditionParams['field_name'];
    $searchValue = $this->conditionParams[$field_name];
    $webhook = $triggerData->getEntityData('MauticWebhook');
    if ($searchValue && $webhook) {
      $contact = CRM_Mautic_BAO_MauticWebhook::getProvidedData('contact', $webhook['data']);
      if (!empty($contact['fields']['core'][$field_name])) {
        return $contact['fields']['core'][$field_name]['value'] == $searchValue;
      }
    }
    return false;
  }

  /**
   * Returns a user friendly text explaining the condition params
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $params = $this->conditionParams;
    return E::ts("Field <em>%1</em> has value: <em>%2</em>.", [
      '%1' => $params['field_name'],
      '%2' => $params[$params['field_name']]
    ]);
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
    return $trigger->doesProvideEntity('MauticWebhook');
  }
}
