<?php

use Civi\Api4\Contact;
use Civi\Api4\Email;
use CRM_Mautic_Utils as U;
use CRM_Mautic_ExtensionUtil as E;

/**
 * CiviRules action to create contact from Mautic Webhook.
 */
class CRM_Civirules_Action_MauticWebhookCreateContact extends CRM_Civirules_Action {

  protected $ruleAction = [];

  protected $action = [];

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    // Prevent triggering any syncs back to Mautic in this request.
    U::$skipUpdatesToMautic = TRUE;
    U::checkDebug(__CLASS__ . '::' . __FUNCTION__);
    $webhook = $triggerData->getEntityData('MauticWebhook');
    $params = $this->getActionParameters();
    $updateContact = ($params['if_matching_civicrm_contact'] === 'update');
    $contactParams = [
      'contact_type' => 'Individual',
    ];
    if (!empty($triggerData->getContactId())) {
      // This is an existing contact.
      if ($updateContact) {
        // Update with the ID.
        $contactParams['id'] = $triggerData->getContactId();
      }
      else {
        // Skip. Do nothing.
        return;
      }
    }

    // Get the contact data from the webhook.
    $mauticData = $webhook['data'];
    $mauticContact = $mauticData['contact'] ?? $mauticData['lead'];
    // If payload is from a subscription change event, copy data to the contact.
    // Then we can let the fieldMapping class handle how this can be converted.
    if ($mauticContact && !empty($mauticData['channel'])) {
      foreach (['channel', 'old_status', 'new_status'] as $commsPrefField) {
        if (isset($mauticData[$commsPrefField])) {
          $mauticContact[$commsPrefField] = $mauticData[$commsPrefField];
        }
      }

    }

    if (!$mauticContact) {
      U::checkDebug('MauticWebhookCreateContact contact data not in payload.');
      return;
    }
    // Does the Webhook payload provide only a partial contact eg. from a subscription change trigger event?
    $isPartialContact = empty($mauticContact['fields']);

    // Convert from Mautic to Civi contact fields.
    $convertedData = CRM_Mautic_Contact_FieldMapping::convertToCiviContact($mauticContact, FALSE, TRUE);
    if ($convertedData) {
      $contactParams += $convertedData;
    }
    else {
      return;
    }
    try {
      $contactParams = array_filter($contactParams, function($val) { return !is_null($val);});

      if (!empty($contactParams['id'])) {
        $existingContact = Contact::get(FALSE)
          ->addSelect(...CRM_Mautic_Contact_FieldMapping::getCommsPrefsFields())
          ->addWhere('id', '=', $contactParams['id'])
          ->execute()
          ->first();
        $commsPrefsChanged = CRM_Mautic_Contact_FieldMapping::hasCiviContactCommunicationPreferencesChanged($contactParams, $existingContact);
        $updatedContact = Contact::update(FALSE)
          ->addWhere('id', '=', $contactParams['id'])
          ->setValues($contactParams)
          ->execute()
          ->first();
      }
      else {
        $commsPrefsChanged = TRUE;
        $updatedContact = Contact::create(FALSE)
          ->setValues($contactParams)
          ->addValue('source', 'Mautic')
          ->execute()
          ->first();
      }

      // Add contact email
      if (!empty($contactParams['email'])) {
        $email = Email::get(FALSE)
          ->addWhere('contact_id', '=', $updatedContact['id'])
          ->addWhere('is_primary', '=', TRUE)
          ->execute()
          ->first();
        if (!$email) {
          Email::create(FALSE)
            ->addValue('contact_id', $updatedContact['id'])
            ->addValue('email', $contactParams['email'])
            ->addValue('is_primary', TRUE)
            ->execute()
            ->first();
        }
        else {
          Email::update(FALSE)
            ->addWhere('id', '=', $email['id'])
            ->addValue('email', $contactParams['email'])
            ->execute();
        }
      }

      // Add contact address
      CRM_Mautic_Contact_FieldMapping::saveMauticAddressToCiviContact($mauticContact, $updatedContact['id']);

      U::checkDebug($contactParams['id'] ? 'Update contact' : 'Create contact', $contactParams);
      // Create "Update Communication Preferences" activity if they changed
      if ($commsPrefsChanged) {
        CRM_Mautic_Contact_FieldMapping::createCommsPrefsActivity($updatedContact, $mauticContact);
      }

      $contactId = $updatedContact['id'];
      // Set the contact id for other rule actions.
      if (!empty($contactId) && !$triggerData->getContactId()) {
        $triggerData->setContactId($contactId);
      }

      // Update the contact tags.
      if (!$isPartialContact) {
        CRM_Mautic_Contact_FieldMapping::saveMauticTagsToCiviContact($mauticContact, $contactId);
      }
      // Update the Mautic Contact with a reference to the CiviCRM Contact.
      if (!$isPartialContact && $contactId && ($contactId !=
      CRM_Mautic_Contact_ContactMatch::getContactReferenceFromMautic($mauticContact))) {
        $mautic = CRM_Mautic_Connection::singleton()->newApi('contacts');
        $editParams = [CRM_Mautic_Contact_ContactMatch::MAUTIC_ID_FIELD_ALIAS => $contactId];
        $mautic->edit($mauticContact['id'], $editParams, FALSE);
        U::checkDebug("Updating Mautic Contact with CiviCRM Contact id", [$mauticContact['id'], $editParams]);
      }
    }
    catch(Exception $e) {
      U::checkDebug('MauticWebhookCreateContact Error::', $e->getMessage());
    }
  }

  public function getExtraDataInputUrl($ruleActionId) {
    return CRM_Utils_System::url('civicrm/admin/mautic/civirules/action/mauticwebhookcreatecontact', 'rule_action_id=' . $ruleActionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $params = $this->getActionParameters();
    return E::ts("If a matching CiviCRM contact is found: {$params['if_matching_civicrm_contact']}");
  }
}
