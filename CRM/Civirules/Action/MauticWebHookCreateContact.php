<?php
/**
 * CiviRules action to create contact from Mautic Webhook.
 */

class CRM_Civirules_Action_MauticWebHookCreateContact extends CRM_Civirules_Action {

  protected $ruleAction = array();

  protected $action = array();

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $webhook = $triggerData->getEntityData('mauticwebhook');
    $params = $this->getActionParameters();
    $updateContact = $params['if_matching_civicrm_contact'] == 'update';
    $contactParams = [
      'contact_type' => 'Individual',
    ];
    if (!empty($webhook['contact_id'])) { 
      if ($updateContact) {
        // Update with the ID.
        $contactParams['id'] = $webhook['contact_id'];
      }
      else {
        // Skip. Do nothing.
        CRM_Core_Error::debug_log_message('MauticWebHookCreateContact not updating from rule.');
        return;
      }
    }
    else {
      // If a new contact, set the source.
      $contactParams['source'] = 'Mautic: ' .  CRM_Mautic_WebHook::getTriggerLabel($webhook['webhook_trigger_type']);
    }
    
    // Get the contact data from the webhook.
    $mauticData = CRM_Mautic_BAO_MauticWebHook::unpackData($webhook);
    $mauticContact = !empty($mauticData->contact) ? $mauticData->contact : $mauticData->lead;
    if (!$mauticContact) {
      CRM_Core_Error::debug_log_message('MauticWebHookCreateContact contact data not in payload.');
      return;
    }
    
    // Convert from Mautic to Civi contact fields.
    $convertedData = CRM_Mautic_Contact_FieldMapping::convertToCiviContact($mauticContact);
    if ($convertedData) {
      $contactParams += $convertedData;
    }
    else {
      return;
    }
    try {
      CRM_Core_Error::debug_var('MauticWebHookCreateContact createContact', $contactParams);
      civicrm_api3('Contact', 'create', $contactParams);
    }
    catch(Exception $e) {
      CRM_Core_Error::debug_var('MauticWebHookCreateContact Error::', $e->getMessage());
    }
  }
  
  public function getExtraDataInputUrl($ruleActionId) {
    // @todo: implement input form.
    return CRM_Utils_System::url('civicrm/admin/mautic/civirules/action/mauticwebhookcreatecontact', 'rule_action_id=' . $ruleActionId); 
  }
}
