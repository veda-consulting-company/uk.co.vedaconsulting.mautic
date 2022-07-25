<?php

use CRM_Mautic_ExtensionUtil as E;
use  CRM_Mautic_Utils as U;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Mautic_Form_Civirules_Action_MauticChangeTagsSegments extends CRM_CivirulesActions_Form_Form {
  public function buildQuickForm() {
    $segments = U::getMauticSegmentOptions(TRUE);
    $tags = U::getMauticTagOptions();
    $this->add('hidden', 'rule_action_id');
    $this->add(
      'select2', 
      'add_segment_ids',
      E::ts('Add Segments'),
      $segments,
      FALSE,
      ['multiple' => TRUE]
    );
    $this->add(
      'select2', 
      'remove_segment_ids',
      E::ts('Remove Segments'),
      $segments,
      FALSE,
      ['multiple' => TRUE]
    );
    $this->add(
      'select2', 
      'add_tags',
      E::ts('Add Tags'),
      $tags,
      FALSE,
      ['multiple' => TRUE]
    );
    $this->add(
      'select2', 
      'remove_tags',
      E::ts('Remove Tags'),
      $tags,
      FALSE,
      ['multiple' => TRUE]
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

  public function addRules() {
    $this->addFormRule([__CLASS__, 'validateForm']);
  }

  public static function arrVal($str) {
    return array_filter(explode(',', $str));
  }

  /**
   * Validation callback.
   */
  public static function validateForm($values) {
    $errors = [];
    $addTags = self::arrVal($values['add_tags']);
    $removeTags = self::arrVal($values['remove_tags']);
    $addSegment = self::arrVal($values['add_segment_ids']);
    $removeSegment = self::arrVal($values['remove_segment_ids']);
    if (!$addTags && !$removeTags && !$addSegment && !$removeSegment) {
      $errors[] = E::ts('Please specify an action.');
    }
    if (array_intersect($addSegment, $removeSegment)) {
      $errors['remove_segment_ids'] = E::ts('You cannot add and remove the same segment.');
    }
    if (array_intersect($addTags, $removeTags)) {
      $errors['remove_tags'] = E::ts('You cannot add and remove the same tag.');
    }
    return empty($errors) ? TRUE : $errors;
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
    $params = !empty($this->ruleAction->action_params) ? unserialize($this->ruleAction->action_params) : []; 
    $defaultValues += $params;
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
