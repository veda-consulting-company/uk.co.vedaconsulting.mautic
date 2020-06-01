<?php

use CRM_Mautic_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Mautic_Form_Civirules_Action_MauticWebHookCreateContact extends CRM_CivirulesActions_Form_Form {
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');
    
    // add form elements
    $this->addRadio(
      'if_matching_civicrm_contact', // field name
      'If a matching CiviCRM contact is found', // field label
      ['skip' => 'Skip', 'update' => 'Update Contact'], // list of options
      ['required' => TRUE],
      NULL,
      TRUE // is required
    );
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
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $data = [];
    foreach ($this->getRenderableElementNames() as $name) {
      $data[$name] = $this->_submitValues[$name];
    }
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }
  
  /**
   * 
   * {@inheritDoc}
   * @see CRM_CivirulesActions_Form_Form::setDefaultValues()
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $defaultValues += unserialize($this->ruleAction->action_params);
    return $defaultValues;
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
}
