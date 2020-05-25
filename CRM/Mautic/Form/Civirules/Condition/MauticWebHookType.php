<?php

use CRM_Mautic_ExtensionUtil as E;

/**
 * Class for CiviRules Condition Mautic WebHook Type Form
 */

class CRM_Mautic_Form_Civirules_Condition_MauticWebHookType extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');
    $types = CRM_Mautic_WebHook::getAllTriggerOptions();
    $options = [];
    foreach ($types as $key => $label) {
      $options[] = ['id' => str_replace('mautic.', '', $key), 'text' => $label];
    }
    // $types[0] = ts('- select -');
    $this->add('select2', 'mautic_webhook_type', ts('WebHook Type'), $options, true, ['multiple' => TRUE]);
    $this->add('select', 'operator', ts('Operator'), array('is one of', 'is not one of'), true);

    $this->addButtons(array(
      array('type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => ts('Cancel'))));
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = unserialize($this->ruleCondition->condition_params);
    if (!empty($data['mautic_webhook_type'])) {
      $defaultValues['mautic_webhook_type'] = $data['mautic_webhook_type'];
    }
    if (!empty($data['operator'])) {
      $defaultValues['operator'] = $data['operator'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   * @access public
   */
  public function postProcess() {
    $data['mautic_webhook_type'] = $this->_submitValues['mautic_webhook_type'];
    $data['operator'] = $this->_submitValues['operator'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }
}