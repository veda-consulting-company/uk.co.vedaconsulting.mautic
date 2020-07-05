<?php
use CRM_Mautic_Utils as U;

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
        return;
      }
    }
    else {
      // This is a new contact.
    }
    
    // Get the contact data from the webhook.
    $mauticData = CRM_Mautic_BAO_MauticWebHook::unpackData($webhook);
    $mauticContact = !empty($mauticData->contact) ? $mauticData->contact : $mauticData->lead;
    
    if (!$mauticContact) {
      U::checkDebug('MauticWebHookCreateContact contact data not in payload.');
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
      $result = civicrm_api3('Contact', 'create', $contactParams);
      
      // Set the contact id for other rule actions.
      if (!empty($result['id']) && !$triggerData->getContactId()) {
        $triggerData->setContactId($result['id']);
      }
      // Update the Mautic Contact with a reference to the CiviCRM Contact.
      $contactId = !empty($result['id']) ? $result['id'] : NULL;
      
      if ($contactId && $contactId != CRM_Mautic_Contact_ContactMatch::getContactReferenceFromMautic($mauticContact)) {
         $mautic = CRM_Mautic_Connection::singleton()->newApi('contacts');
         $editParams = [CRM_Mautic_Contact_ContactMatch::MAUTIC_ID_FIELD_ALIAS => $contactId];
         $mautic->edit($mauticContact->id, $editParams, FALSE);
         U::checkDebug("Updating Mautic Contact  with CiviCRM Contact id", [$mauticContact->id, $editParams]);
      }
    }
    catch(Exception $e) {
      U::checkDebug('MauticWebHookCreateContact Error::', $e->getMessage());
    }
  }
  
  public function getExtraDataInputUrl($ruleActionId) {
    return CRM_Utils_System::url('civicrm/admin/mautic/civirules/action/mauticwebhookcreatecontact', 'rule_action_id=' . $ruleActionId); 
  }
}
