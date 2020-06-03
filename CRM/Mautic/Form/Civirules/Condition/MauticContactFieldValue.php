<?php

use CRM_Mautic_ExtensionUtil as E;
use CRM_Mautic_Connection as MC;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Mautic_Form_Civirules_Condition_MauticContactFieldValue extends CRM_CivirulesConditions_Form_Form {

  public function getFields() {
    $exclude = ['civicrm_contact_id'];
    $types = ['text', 'email', 'url', 'number', 'lookup'];
    $api = MC::singleton()->newApi('contactFields');
    $fields = $api->getList();
    $fields =  $fields[$api->listName()];
    $fields = array_filter($fields, function($v) use($exclude, $types) {
      return !in_array($v['alias'], $exclude) && in_array($v['type'], $types);
    });
    return $fields;
  }
  
  
  
  public function addElementForField($field) {
    switch ($field['type']) {
      case 'text':
      case 'email':
      case 'url':
      case 'number':
        $this->add('text', $field['alias'], $field['label'],['class' => 'value']);
        break;
      case 'lookup':
        $opts = explode('|', $field['properties']['list']);
        $opts = array_merge(['' => ''], array_combine($opts, $opts));
        $this->add('select', $field['alias'], $field['label'], $opts, false, ['class' => 'value']);
        break;
    }
  }
  
  
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');
    $fields = $this->getFields();
    $fieldOpts = ['' => 'Select a field'];
    foreach ($fields as $field) {
      $fieldOpts[$field['alias']] = $field['label']; 
    }
    $this->add('select', 'field_name', 'Field', $fieldOpts, true);
    foreach ($fields as $field) {
      $this->addElementForField($field);  
    }
    // add form elements
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   * @access public
   */
  public function postProcess() {
    foreach ($this->getRenderableElementNames() as $name) {
      if (!empty($this->_submitValues[$name])) {
        $data[$name] = $this->_submitValues[$name];
      }
    }
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
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
    if ($data) {
      $defaultValues += $data;
    }
    return $defaultValues;
  }
  

}
