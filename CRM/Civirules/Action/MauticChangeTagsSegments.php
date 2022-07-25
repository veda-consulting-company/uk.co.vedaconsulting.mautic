<?php
/**
 *
 * CiviRules action to change Mautic Segment and/or Tags for a contact.
 */

use CRM_Mautic_ExtensionUtil as E;
use CRM_Mautic_Connection as MC;
use CRM_Mautic_Contact_Contact as MauticContact;
use CRM_Mautic_Utils as U;

class CRM_Civirules_Action_MauticChangeTagsSegments extends CRM_Civirules_Action {
 /**
  * Keep a log of items processed in this request.
  */ 
 private static $processedIds = [];

  protected $ruleAction = [];

  protected $action = [];

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    if (U::$skipUpdatesToMautic) {
      U::checkDebug('Skipping update to Mautic because contact has just been synced.');
      return;
    }
    $ruleId = $this->ruleAction['rule_id'] ?? 0;
    U::checkDebug(__CLASS__ . ' action triggerred', $this->ruleAction);
    $civicrmContactID = $triggerData->getContactId();

    // Skip if contact has already been processed for this rule in the current request.
    if (empty($civicrmContactID) || !empty(self::$processedIds[$ruleId][$civicrmContactID])) {
      return;
    }
    self::$processedIds[$ruleId][$civicrmContactID] = TRUE;

    $params = $this->getActionParameters();

    
    $addSegmentIds = $params['add_segment_ids'] ?? [];
    $removeSegmentIds = $params['remove_segment_ids'] ?? [];
    $addTags = $params['add_tags'];
    $removeTags = $params['remove_tags'];
    $contact = new MauticContact($civicrmContactID);
    $mauticContactId = $contact->pushToMautic(TRUE);
    if ($mauticContactId) {
      try {
        $segmentApi = MC::singleton()->newApi('segments');
        foreach ($removeSegmentIds as $segmentId) {
          $segmentApi->removeContact($segmentId, $mauticContactId);
        }
        foreach ($addSegmentIds as $segmentId) {
          $segmentApi->addContact($segmentId, $mauticContactId);
        }
        if ($addTags || $removeTags) {
          $tags = array_merge($addTags, array_map(function($t) { return '-' . $t;}, $removeTags));
          $contact->pushToMautic(FALSE, ['tags' => $tags]);
        }
      }
      catch (Exception $e) {
        CRM_Core_Error::debug_log_message($e->getMessage());
      }
    }
  }
  /**
   *
   */
  public function userFriendlyConditionParams() {
    $params = $this->getActionParameters();
    $labels = [
      'add_segment_ids' => E::ts('Add Segments'),
      'remove_segment_ids' => E::ts('Remove Segments'),
      'add_tags' => E::ts('Add Tags'),
      'remove_tags' => E::ts('Remove Tags'),
    ];
    $return = '';
    foreach ($labels  as $key => $label) {
      if ($params[$key]) {
        $val = $params[$key];
        if (strpos($key, 'segment_ids')) {
          $selections = array_intersect_key(U::getMauticSegmentOptions(FALSE), array_flip($val));
        }
        else {
          $selections = $val;
        }
        $return .= $label . ': ' .implode(', ', $selections) . '. <br />';
      }
    }
    return $return;
  }

 /**
  * @inherit
  */ 
  protected function getActionParameters() {
    $params = parent::getActionParameters();
    foreach (['add_segment_ids', 'remove_segment_ids', 'add_tags', 'remove_tags'] as $key) {
      $params[$key] = $this->arrVal($params[$key]);  
    }
    return $params;
  }

  public function arrVal($str) {
    return !is_array($str) ? array_filter(explode(',', $str)) : $str;
  }


  /**
   *
   * {@inheritDoc}
   * @see CRM_Civirules_Action::getExtraDataInputUrl()
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return CRM_Utils_System::url('civicrm/admin/mautic/civirules/action/mautic_tags_segment', 'rule_action_id=' . $ruleActionId);
  }
}
